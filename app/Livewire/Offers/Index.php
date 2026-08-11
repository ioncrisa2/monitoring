<?php

namespace App\Livewire\Offers;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\StatusHistory;
use App\Models\WorkOrder;
use App\Services\AuditLogService;
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

    public string $offer_no = '';
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

    public function create(): void
    {
        $this->authorize('menu.offers');
        $this->reset(['editingId', 'offer_no', 'branch_id', 'debtor_id', 'client_id', 'report_user_id', 'fee', 'ta', 'dpp', 'ppn', 'pph', 'note']);
        $this->offer_date = Carbon::today()->format('Y-m-d');
        $this->outcome = 'DRAFT';
        
        // Default offer number suggestion
        $count = Offer::whereYear('created_at', date('Y'))->count() + 1;
        $this->offer_no = sprintf('PNW/%s/%04d', date('Y'), $count);

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('menu.offers');
        $offer = Offer::findOrFail($id);
        $this->editingId = $offer->id;
        $this->offer_no = $offer->offer_no;
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
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('menu.offers');
        $validated = $this->validate([
            'offer_no' => 'required|string|max:100|unique:offers,offer_no,' . $this->editingId,
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

        $validated['created_by'] = Auth::id();

        if ($this->editingId) {
            $offer = Offer::findOrFail($this->editingId);
            $offer->update($validated);
            AuditLogService::record('UPDATE', "Memperbarui data penawaran {$offer->offer_no} (Fee: Rp " . number_format($offer->fee, 0, ',', '.') . ")", 'Offer', $offer->id);
            session()->flash('message', 'Data penawaran berhasil diperbarui.');
        } else {
            $offer = Offer::create($validated);
            AuditLogService::record('CREATE', "Membuat penawaran baru {$offer->offer_no} (Fee: Rp " . number_format($offer->fee, 0, ',', '.') . ")", 'Offer', $offer->id);
            session()->flash('message', 'Penawaran baru berhasil dibuat.');
        }

        $this->showModal = false;
    }

    public function prepareConvert(int $id): void
    {
        $this->authorize('menu.offers');
        $this->convertingOffer = Offer::with(['debtor', 'client'])->findOrFail($id);
        $this->survey_required = true;
        $this->sla_date = Carbon::today()->addDays(7)->format('Y-m-d');
        $this->showConvertModal = true;
    }

    public function convertToJob(): void
    {
        $this->authorize('menu.offers');
        if (!$this->convertingOffer) {
            return;
        }

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
            'note' => 'Konversi dari Penawaran ' . $this->convertingOffer->offer_no,
        ]);

        AuditLogService::record('CONVERT', "Mengonversi Penawaran {$this->convertingOffer->offer_no} menjadi Pekerjaan Aktif ({$workOrder->contract_no})", 'WorkOrder', $workOrder->id);

        session()->flash('message', 'Penawaran berhasil dikonversi menjadi Pekerjaan Aktif (' . $workOrder->contract_no . ').');
        $this->showConvertModal = false;
        $this->convertingOffer = null;
    }

    public function render()
    {
        $offers = Offer::query()
            ->with(['branch', 'debtor', 'client', 'reportUser', 'workOrder'])
            ->when($this->search, function ($query) {
                $query->where('offer_no', 'like', '%' . $this->search . '%')
                    ->orWhereHas('debtor', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('client', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
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

        $branches = Branch::where('active', true)->get();
        $debtors = Debtor::all();
        $clients = Organization::all();

        return view('livewire.offers.index', [
            'offers' => $offers,
            'branches' => $branches,
            'debtors' => $debtors,
            'clients' => $clients,
        ])->layout('layouts.app');
    }
}
