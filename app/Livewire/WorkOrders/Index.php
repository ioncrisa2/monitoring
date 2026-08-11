<?php

namespace App\Livewire\WorkOrders;

use App\Models\Branch;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public ?int $filterBranchId = null;
    public bool $filterOverdueOnly = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $workOrders = WorkOrder::query()
            ->with(['offer.debtor', 'offer.client', 'offer.branch', 'surveyors.user', 'reviewers.user', 'statusHistories'])
            ->when($this->search, function ($query) {
                $query->where('contract_no', 'like', '%' . $this->search . '%')
                    ->orWhereHas('offer.debtor', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('offer.client', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
                    });
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('current_status', $this->filterStatus);
            })
            ->when($this->filterBranchId, function ($query) {
                $query->whereHas('offer', function ($q) {
                    $q->where('branch_id', $this->filterBranchId);
                });
            })
            ->when($this->filterOverdueOnly, function ($query) {
                $query->whereNotNull('sla_date')
                    ->whereDate('sla_date', '<', Carbon::today())
                    ->whereNotIn('current_status', ['SELESAI', 'BATAL']);
            })
            ->latest()
            ->paginate(10);

        $branches = Branch::where('active', true)->get();

        return view('livewire.work-orders.index', [
            'workOrders' => $workOrders,
            'branches' => $branches,
        ])->layout('layouts.app');
    }
}
