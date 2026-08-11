<?php

namespace App\Livewire\Offers;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OfferNumberService;
use App\Services\Offers\OfferNumberAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class Create extends Component
{
    public string $offer_no = '';

    public ?int $sequence_no = null;

    public string $offer_date = '';

    public ?int $branch_id = null;

    public ?int $debtor_id = null;

    public ?int $client_id = null;

    public ?int $report_user_id = null;

    public float $fee = 0;

    public float $ta = 0;

    public float $dpp = 0;

    public float $ppn = 0;

    public float $pph = 0;

    public string $outcome = 'DRAFT';

    public string $note = '';

    public function mount(): void
    {
        $this->authorize('menu.offers');
        $this->offer_date = Carbon::today()->format('Y-m-d');
        $this->sequence_no = OfferNumberService::nextSequence((int) Carbon::today()->year);
    }

    public function updatedFee(): void
    {
        $this->calculateTax();
    }

    public function updatedTa(): void
    {
        $this->calculateTax();
    }

    public function calculateTax(): void
    {
        $this->dpp = max(0, $this->fee - $this->ta);
        $this->ppn = round($this->dpp * 0.11, 2);
        $this->pph = round($this->dpp * 0.02, 2);
    }

    public function updatedSequenceNo(): void
    {
        $this->syncOfferNo();
    }

    public function updatedBranchId(): void
    {
        $this->syncOfferNo();
    }

    public function updatedOfferDate(): void
    {
        $this->sequence_no = OfferNumberService::nextSequence((int) Carbon::parse($this->offer_date)->year);
        $this->syncOfferNo();
    }

    public function lastSequenceForYear(): int
    {
        $year = $this->offer_date ? Carbon::parse($this->offer_date)->year : Carbon::today()->year;

        return OfferNumberService::lastSequence((int) $year);
    }

    private function syncOfferNo(): void
    {
        if ($this->sequence_no && $this->branch_id && $this->offer_date) {
            $branch = Branch::find($this->branch_id);
            if ($branch && ! is_null($branch->number_code)) {
                $this->offer_no = OfferNumberService::build((int) $this->sequence_no, (int) $branch->number_code, Carbon::parse($this->offer_date));

                return;
            }
        }

        $this->offer_no = '';
    }

    public function save(OfferNumberAllocator $numberAllocator): void
    {
        $this->authorize('menu.offers');
        $validated = $this->validate([
            'offer_date' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
            'debtor_id' => 'required|exists:debtors,id',
            'client_id' => 'required|exists:organizations,id',
            'report_user_id' => 'nullable|exists:organizations,id',
            'fee' => 'required|numeric|min:0',
            'ta' => 'required|numeric|min:0',
            'dpp' => 'required|numeric|min:0',
            'ppn' => 'required|numeric|min:0',
            'pph' => 'required|numeric|min:0',
            'outcome' => 'required|in:DRAFT,DIKIRIM,DITERIMA,TIDAK_LANJUT,DITOLAK',
            'note' => 'nullable|string',
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);
        abort_unless($this->canAccessBranch($branch), 403);

        if (is_null($branch->number_code)) {
            $this->addError('branch_id', 'Cabang ini belum memiliki Kode Angka. Atur terlebih dahulu di menu Master Cabang.');

            return;
        }

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $offer = DB::transaction(function () use ($actor, $numberAllocator, $validated): Offer {
            // offer_no wajib unik pada skema lama. Nilai ini hanya hidup di dalam
            // transaksi dan akan diganti allocator sebelum transaksi di-commit.
            $offer = Offer::create(array_merge($validated, [
                'offer_no' => 'PENDING-'.Str::uuid(),
                'sequence_no' => null,
                'sequence_year' => null,
                'number_suffix' => '',
                'current_number_allocation_id' => null,
                'created_by' => $actor->getKey(),
            ]));

            $numberAllocator->allocate($offer, $actor);
            $offer->refresh();

            AuditLogService::record('CREATE', "Membuat penawaran baru {$offer->offer_no} (Fee: Rp ".number_format($offer->fee, 0, ',', '.').')', 'Offer', $offer->id);

            return $offer;
        }, 5);

        session()->flash('message', "Penawaran baru {$offer->offer_no} berhasil dibuat.");
        $this->redirect(route('offers.index'), navigate: true);
    }

    public function render()
    {
        $branches = Branch::query()
            ->where('active', true)
            ->when(! $this->canAccessAllBranches(), fn ($query) => $query->whereKey(Auth::user()?->branch_id ?? 0))
            ->get();

        return view('livewire.offers.create', [
            'branches' => $branches,
            'debtors' => Debtor::all(),
            'clients' => Organization::all(),
        ])->layout('layouts.app');
    }

    private function canAccessBranch(Branch $branch): bool
    {
        return $this->canAccessAllBranches()
            || (Auth::user()?->branch_id !== null && Auth::user()?->branch_id === $branch->id);
    }

    private function canAccessAllBranches(): bool
    {
        return Auth::user()?->isSysAdmin() === true
            || Auth::user()?->can('offers.cross-branch') === true;
    }
}
