<?php

namespace App\Livewire\Offers;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\StatusHistory;
use App\Models\WorkOrder;
use App\Services\AuditLogService;
use App\Services\OfferNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterOutcome = '';

    public ?int $filterBranchId = null;

    // Offer Modal State
    public bool $showModal = false;

    public ?int $editingId = null;

    public bool $numberLocked = false;

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

    // Convert Modal State
    public bool $showConvertModal = false;

    public ?Offer $convertingOffer = null;

    public bool $survey_required = true;

    public string $sla_date = '';

    public function mount(): void
    {
        $this->offer_date = Carbon::today()->format('Y-m-d');
        $this->sla_date = Carbon::today()->addDays(7)->format('Y-m-d');
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $this->authorize('menu.offers');
        $offer = $this->findAccessibleOffer($id);
        $this->editingId = $offer->id;
        $this->offer_no = $offer->offer_no;
        $this->sequence_no = $offer->sequence_no;
        $this->offer_date = $offer->offer_date->format('Y-m-d');
        $this->branch_id = $offer->branch_id;
        $this->debtor_id = $offer->debtor_id;
        $this->client_id = $offer->client_id;
        $this->report_user_id = $offer->report_user_id;
        $this->fee = (float) $offer->fee;
        $this->ta = (float) $offer->ta;
        $this->dpp = (float) $offer->dpp;
        $this->ppn = (float) $offer->ppn;
        $this->pph = (float) $offer->pph;
        $this->outcome = $offer->outcome;
        $this->note = $offer->note ?? '';
        $this->numberLocked = $offer->current_number_allocation_id !== null;

        if (! $this->numberLocked && $this->sequence_no) {
            $this->syncOfferNo();
        }

        $this->showModal = true;
    }

    public function updatedSequenceNo(): void
    {
        if ($this->restoreAllocatedNumber()) {
            return;
        }

        $this->syncOfferNo();
    }

    public function updatedBranchId(): void
    {
        if ($this->restoreAllocatedNumber()) {
            return;
        }

        $this->syncOfferNo();
    }

    public function updatedOfferDate(): void
    {
        if ($this->restoreAllocatedNumber()) {
            return;
        }

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

    public function save(): void
    {
        $this->authorize('menu.offers');
        $offer = $this->findAccessibleOffer((int) $this->editingId);
        $numberLocked = $offer->current_number_allocation_id !== null;
        $this->numberLocked = $numberLocked;

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

        if ($numberLocked && $this->allocatedNumberWasChanged($offer, $validated)) {
            $this->setNumberIdentityFrom($offer);
            $this->addError('sequence_no', 'Nomor, tanggal, dan cabang penerbit sudah dialokasikan dan tidak dapat diubah.');

            return;
        }

        if ($numberLocked) {
            unset($validated['sequence_no'], $validated['offer_date'], $validated['branch_id']);

            $offer->update($validated);
            AuditLogService::record('UPDATE', "Memperbarui data penawaran {$offer->offer_no} (Fee: Rp ".number_format($offer->fee, 0, ',', '.').')', 'Offer', $offer->id);
            session()->flash('message', 'Data penawaran berhasil diperbarui.');

            $this->showModal = false;

            return;
        }

        $branch = Branch::findOrFail($validated['branch_id']);
        abort_unless($this->canAccessBranch($branch), 403);

        if (is_null($branch->number_code)) {
            $this->addError('branch_id', 'Cabang ini belum memiliki Kode Angka. Atur terlebih dahulu di menu Master Cabang.');

            return;
        }

        $offerDate = Carbon::parse($validated['offer_date']);
        $year = (int) $offerDate->year;

        $duplicate = Offer::where('sequence_no', $validated['sequence_no'])
            ->whereYear('offer_date', $year)
            ->where('id', '!=', $this->editingId)
            ->exists();

        if ($duplicate) {
            $this->addError('sequence_no', "Nomor urut {$validated['sequence_no']} sudah digunakan untuk tahun {$year}. Saran nomor berikutnya: ".OfferNumberService::nextSequence($year).'.');

            return;
        }

        $validated['offer_no'] = OfferNumberService::build((int) $validated['sequence_no'], (int) $branch->number_code, $offerDate);
        $offer->update($validated);
        AuditLogService::record('UPDATE', "Memperbarui data penawaran {$offer->offer_no} (Fee: Rp ".number_format($offer->fee, 0, ',', '.').')', 'Offer', $offer->id);
        session()->flash('message', 'Data penawaran berhasil diperbarui.');

        $this->showModal = false;
    }

    public function prepareConvert(int $id): void
    {
        $this->authorize('menu.offers');
        $this->convertingOffer = $this->findAccessibleOffer($id)
            ->load(['debtor', 'client']);
        $this->survey_required = true;
        $this->sla_date = Carbon::today()->addDays(7)->format('Y-m-d');
        $this->showConvertModal = true;
    }

    public function convertToJob(): void
    {
        $this->authorize('menu.offers');
        if (! $this->convertingOffer) {
            return;
        }

        $this->convertingOffer = $this->findAccessibleOffer($this->convertingOffer->id);

        $this->validate([
            'sla_date' => 'required|date|after_or_equal:today',
            'survey_required' => 'boolean',
        ]);

        // 1. Update Offer outcome to DITERIMA
        $this->convertingOffer->update(['outcome' => 'DITERIMA']);

        // 2. Create WorkOrder using offer_no as contract_no (Per locked decision #1)
        $workOrder = WorkOrder::create([
            'offer_id' => $this->convertingOffer->id,
            'contract_no' => $this->convertingOffer->offer_no,
            'contract_date' => Carbon::today(),
            'survey_required' => $this->survey_required,
            'sla_date' => $this->sla_date,
            'current_status' => 'PERSIAPAN',
            'started_at' => Carbon::now(),
        ]);

        // 3. Create initial status history
        StatusHistory::create([
            'work_order_id' => $workOrder->id,
            'from_status' => null,
            'to_status' => 'PERSIAPAN',
            'changed_by' => Auth::id(),
            'note' => 'Konversi dari Penawaran '.$this->convertingOffer->offer_no,
        ]);

        AuditLogService::record('CONVERT', "Mengonversi Penawaran {$this->convertingOffer->offer_no} menjadi Pekerjaan Aktif ({$workOrder->contract_no})", 'WorkOrder', $workOrder->id);

        session()->flash('message', 'Penawaran berhasil dikonversi menjadi Pekerjaan Aktif ('.$workOrder->contract_no.').');
        $this->showConvertModal = false;
        $this->convertingOffer = null;
    }

    public function render()
    {
        $offers = $this->accessibleOffers()
            ->with(['branch', 'debtor', 'client', 'reportUser', 'workOrder'])
            ->when($this->search, function ($query) {
                $query->where(function ($searchQuery) {
                    $searchQuery->where('offer_no', 'like', '%'.$this->search.'%')
                        ->orWhereHas('debtor', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%');
                        })
                        ->orWhereHas('client', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->filterOutcome, function ($query) {
                $query->where('outcome', $this->filterOutcome);
            })
            ->when($this->filterBranchId, function ($query) {
                $query->where('branch_id', $this->filterBranchId);
            })
            ->latest()
            ->paginate(10);

        $branches = Branch::query()
            ->where('active', true)
            ->when(! $this->canAccessAllBranches(), fn ($query) => $query->whereKey(Auth::user()?->branch_id ?? 0))
            ->get();
        $debtors = Debtor::all();
        $clients = Organization::all();

        return view('livewire.offers.index', [
            'offers' => $offers,
            'branches' => $branches,
            'debtors' => $debtors,
            'clients' => $clients,
        ])->layout('layouts.app');
    }

    private function accessibleOffers()
    {
        return Offer::query()
            ->when(
                ! $this->canAccessAllBranches(),
                fn ($query) => $query->where('branch_id', Auth::user()?->branch_id ?? 0),
            );
    }

    private function findAccessibleOffer(int $id): Offer
    {
        $offer = $this->accessibleOffers()->find($id);
        abort_unless($offer instanceof Offer, 403);

        return $offer;
    }

    /**
     * Restore immutable identity fields when a stale or tampered client tries
     * to change an offer whose number has already been allocated.
     */
    private function restoreAllocatedNumber(): bool
    {
        if ($this->editingId === null) {
            return false;
        }

        $offer = $this->accessibleOffers()->find($this->editingId);

        if (! $offer instanceof Offer || $offer->current_number_allocation_id === null) {
            $this->numberLocked = false;

            return false;
        }

        $this->numberLocked = true;
        $this->setNumberIdentityFrom($offer);

        return true;
    }

    private function allocatedNumberWasChanged(Offer $offer, array $validated): bool
    {
        return (int) $validated['sequence_no'] !== (int) $offer->sequence_no
            || Carbon::parse($validated['offer_date'])->toDateString() !== $offer->offer_date->toDateString()
            || (int) $validated['branch_id'] !== (int) $offer->branch_id
            || $this->offer_no !== $offer->offer_no;
    }

    private function setNumberIdentityFrom(Offer $offer): void
    {
        $this->offer_no = $offer->offer_no;
        $this->sequence_no = $offer->sequence_no;
        $this->offer_date = $offer->offer_date->format('Y-m-d');
        $this->branch_id = $offer->branch_id;
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
