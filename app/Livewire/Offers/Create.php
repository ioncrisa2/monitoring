<?php

namespace App\Livewire\Offers;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Services\AuditLogService;
use App\Services\OfferNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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
            if ($branch && !is_null($branch->number_code)) {
                $this->offer_no = OfferNumberService::build((int) $this->sequence_no, (int) $branch->number_code, Carbon::parse($this->offer_date));
                return;
            }
        }

        $this->offer_no = '';
    }

    public function save(): void
    {
        $this->authorize('menu.offers');
        $validated = $this->validate([
            'sequence_no' => 'required|integer|min:1',
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
        if (is_null($branch->number_code)) {
            $this->addError('branch_id', 'Cabang ini belum memiliki Kode Angka. Atur terlebih dahulu di menu Master Cabang.');
            return;
        }

        $offerDate = Carbon::parse($validated['offer_date']);
        $year = (int) $offerDate->year;

        $duplicate = Offer::where('sequence_no', $validated['sequence_no'])
            ->whereYear('offer_date', $year)
            ->exists();

        if ($duplicate) {
            $this->addError('sequence_no', "Nomor urut {$validated['sequence_no']} sudah digunakan untuk tahun {$year}. Saran nomor berikutnya: " . OfferNumberService::nextSequence($year) . '.');
            return;
        }

        $validated['offer_no'] = OfferNumberService::build((int) $validated['sequence_no'], (int) $branch->number_code, $offerDate);
        $validated['created_by'] = Auth::id();

        $offer = Offer::create($validated);
        AuditLogService::record('CREATE', "Membuat penawaran baru {$offer->offer_no} (Fee: Rp " . number_format($offer->fee, 0, ',', '.') . ')', 'Offer', $offer->id);

        session()->flash('message', 'Penawaran baru berhasil dibuat.');
        $this->redirect(route('offers.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.offers.create', [
            'branches' => Branch::where('active', true)->get(),
            'debtors' => Debtor::all(),
            'clients' => Organization::all(),
        ])->layout('layouts.app');
    }
}
