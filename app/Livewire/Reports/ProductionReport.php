<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\Offer;
use App\Models\WorkOrder;
use App\Services\ProductionExportService;
use Carbon\Carbon;
use Livewire\Component;

class ProductionReport extends Component
{
    public ?int $selectedBranchId = null;

    public string $fromDate = '';

    public string $toDate = '';

    public string $selectedStatus = '';

    public string $revenueView = 'monthly'; // monthly | yearly

    private const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function exportExcel(ProductionExportService $exportService)
    {
        return $exportService->exportXlsx(
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
            $query->whereHas('offer', fn ($q) => $q->where('branch_id', $this->selectedBranchId));
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

        // --- Analytics widgets (relocated from Dashboard, branch-scoped only) ---
        $branchQuery = function ($q) {
            if ($this->selectedBranchId) {
                $q->where('branch_id', $this->selectedBranchId);
            }
        };

        $offerBranchQuery = function ($q) {
            if ($this->selectedBranchId) {
                $q->whereHas('offer', function ($sub) {
                    $sub->where('branch_id', $this->selectedBranchId);
                });
            }
        };

        // Revenue Trend: fee pekerjaan SELESAI (realized), dikelompokkan per bulan (tahun berjalan) atau per tahun
        $completedWithFee = WorkOrder::with('offer')
            ->where('current_status', 'SELESAI')
            ->whereNotNull('completed_at')
            ->where($offerBranchQuery)
            ->get();

        if ($this->revenueView === 'yearly') {
            $revenueTrend = $completedWithFee
                ->groupBy(fn ($wo) => $wo->completed_at->year)
                ->map(fn ($group) => $group->sum(fn ($wo) => $wo->offer?->fee ?? 0))
                ->sortKeys();
            $revenueLabels = $revenueTrend->keys()->map(fn ($y) => (string) $y)->all();
            $revenueValues = $revenueTrend->values()->all();
        } else {
            $currentYear = Carbon::now()->year;
            $byMonth = $completedWithFee
                ->filter(fn ($wo) => $wo->completed_at->year === $currentYear)
                ->groupBy(fn ($wo) => $wo->completed_at->month)
                ->map(fn ($group) => $group->sum(fn ($wo) => $wo->offer?->fee ?? 0));
            $revenueLabels = self::MONTH_LABELS;
            $revenueValues = [];
            for ($m = 1; $m <= 12; $m++) {
                $revenueValues[] = (float) ($byMonth->get($m) ?? 0);
            }
        }

        // Funnel Konversi Penawaran
        $outcomeCounts = Offer::where($branchQuery)->get()->countBy('outcome');
        $outcomeOrder = ['DRAFT', 'DIKIRIM', 'DITERIMA', 'TIDAK_LANJUT', 'DITOLAK'];
        $offerOutcomeCounts = [];
        foreach ($outcomeOrder as $outcome) {
            $offerOutcomeCounts[$outcome] = $outcomeCounts->get($outcome, 0);
        }
        $decidedCount = $offerOutcomeCounts['DITERIMA'] + $offerOutcomeCounts['TIDAK_LANJUT'] + $offerOutcomeCounts['DITOLAK'];
        $conversionRate = $decidedCount > 0 ? round(($offerOutcomeCounts['DITERIMA'] / $decidedCount) * 100, 1) : 0;

        return view('livewire.reports.production-report', [
            'workOrders' => $workOrders,
            'branches' => $branches,
            'revenueLabels' => $revenueLabels,
            'revenueValues' => $revenueValues,
            'offerOutcomeCounts' => $offerOutcomeCounts,
            'conversionRate' => $conversionRate,
            'decidedCount' => $decidedCount,
        ])->layout('layouts.app');
    }
}
