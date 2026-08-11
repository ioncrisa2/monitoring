<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\WorkOrder;
use App\Services\ProductionExportService;
use Livewire\Component;

class ProductionReport extends Component
{
    public ?int $selectedBranchId = null;
    public string $fromDate = '';
    public string $toDate = '';
    public string $selectedStatus = '';

    public function exportExcel(ProductionExportService $exportService)
    {
        return $exportService->exportCsv(
            $this->selectedBranchId ?: null,
            $this->fromDate ?: null,
            $this->toDate ?: null,
            $this->selectedStatus ?: null
        );
    }

    public function render()
    {
        $query = WorkOrder::with([
            'offer.branch',
            'offer.debtor',
            'offer.client',
            'surveyors.user',
            'reviewers.user',
            'reports.delivery',
        ]);

        if ($this->selectedBranchId) {
            $query->whereHas('offer', fn($q) => $q->where('branch_id', $this->selectedBranchId));
        }

        if ($this->fromDate) {
            $query->whereDate('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate) {
            $query->whereDate('created_at', '<=', $this->toDate);
        }

        if ($this->selectedStatus) {
            $query->where('current_status', $this->selectedStatus);
        }

        $workOrders = $query->latest()->paginate(15);
        $branches = Branch::where('active', true)->get();

        return view('livewire.reports.production-report', [
            'workOrders' => $workOrders,
            'branches' => $branches,
        ])->layout('layouts.app');
    }
}
