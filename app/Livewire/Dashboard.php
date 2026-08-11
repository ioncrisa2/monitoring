<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\Offer;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public ?int $selectedBranchId = null;

    private const PIPELINE_STAGES = ['PERSIAPAN', 'SURVEY', 'PENGERJAAN', 'REVIEW', 'CETAK'];

    public function render()
    {
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

        // KPI Counters
        $activeWorkOrdersCount = WorkOrder::whereNotIn('current_status', ['SELESAI', 'BATAL'])
            ->where($offerBranchQuery)
            ->count();

        $overdueCount = WorkOrder::whereNotNull('sla_date')
            ->whereDate('sla_date', '<', Carbon::today())
            ->whereNotIn('current_status', ['SELESAI', 'BATAL'])
            ->where($offerBranchQuery)
            ->count();

        $completedThisMonthCount = WorkOrder::where('current_status', 'SELESAI')
            ->whereMonth('completed_at', Carbon::now()->month)
            ->whereYear('completed_at', Carbon::now()->year)
            ->where($offerBranchQuery)
            ->count();

        // SLA Compliance Rate (%)
        $totalJobsCount = WorkOrder::where($offerBranchQuery)->count();
        $slaComplianceRate = $totalJobsCount > 0
            ? round((($totalJobsCount - $overdueCount) / $totalJobsCount) * 100, 1)
            : 100;

        // Financial Pipeline Metrics
        $totalOfferFee = Offer::whereIn('outcome', ['DRAFT', 'DIKIRIM'])->where($branchQuery)->sum('fee');
        $activeJobFee = WorkOrder::whereNotIn('current_status', ['SELESAI', 'BATAL'])->where($offerBranchQuery)->get()->sum(fn ($w) => $w->offer?->fee ?? 0);
        $completedJobFee = WorkOrder::where('current_status', 'SELESAI')->where($offerBranchQuery)->get()->sum(fn ($w) => $w->offer?->fee ?? 0);

        // Status Funnel Counts
        $funnelStatuses = [
            'PERSIAPAN' => WorkOrder::where('current_status', 'PERSIAPAN')->where($offerBranchQuery)->count(),
            'SURVEY' => WorkOrder::where('current_status', 'SURVEY')->where($offerBranchQuery)->count(),
            'PENGERJAAN' => WorkOrder::where('current_status', 'PENGERJAAN')->where($offerBranchQuery)->count(),
            'REVIEW' => WorkOrder::where('current_status', 'REVIEW')->where($offerBranchQuery)->count(),
            'CETAK' => WorkOrder::where('current_status', 'CETAK')->where($offerBranchQuery)->count(),
            'SELESAI' => WorkOrder::where('current_status', 'SELESAI')->where($offerBranchQuery)->count(),
        ];

        // Bottleneck Ranking (Top 8 oldest active work orders)
        $bottleneckJobs = WorkOrder::with(['offer.debtor', 'offer.client', 'offer.branch', 'surveyors.user', 'reviewers.user'])
            ->whereNotIn('current_status', ['SELESAI', 'BATAL'])
            ->where($offerBranchQuery)
            ->get()
            ->sortByDesc('aging_days')
            ->take(8);

        // Rata-rata Durasi per Tahap Pipeline (hanya transisi yang sudah selesai/berpindah, aging yang masih berjalan tidak dihitung)
        $workOrdersWithHistory = WorkOrder::with('statusHistories')
            ->where($offerBranchQuery)
            ->get();

        $stageDurations = array_fill_keys(self::PIPELINE_STAGES, []);
        foreach ($workOrdersWithHistory as $wo) {
            $histories = $wo->statusHistories->sortBy('created_at')->values();
            for ($i = 0; $i < $histories->count() - 1; $i++) {
                $current = $histories[$i];
                $next = $histories[$i + 1];
                if (in_array($current->to_status, self::PIPELINE_STAGES, true)) {
                    $days = $current->created_at->diffInHours($next->created_at) / 24;
                    $stageDurations[$current->to_status][] = $days;
                }
            }
        }

        $stageAverages = [];
        foreach (self::PIPELINE_STAGES as $stage) {
            $samples = $stageDurations[$stage];
            $stageAverages[$stage] = [
                'avg' => count($samples) > 0 ? round(array_sum($samples) / count($samples), 1) : 0,
                'count' => count($samples),
            ];
        }

        $branches = Branch::where('active', true)->get();

        return view('livewire.dashboard', [
            'activeWorkOrdersCount' => $activeWorkOrdersCount,
            'overdueCount' => $overdueCount,
            'completedThisMonthCount' => $completedThisMonthCount,
            'slaComplianceRate' => $slaComplianceRate,
            'totalOfferFee' => $totalOfferFee,
            'activeJobFee' => $activeJobFee,
            'completedJobFee' => $completedJobFee,
            'funnelStatuses' => $funnelStatuses,
            'bottleneckJobs' => $bottleneckJobs,
            'stageAverages' => $stageAverages,
            'branches' => $branches,
        ])->layout('layouts.app');
    }
}
