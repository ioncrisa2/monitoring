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
        $activeOffersCount = Offer::whereIn('outcome', ['DRAFT', 'DIKIRIM'])
            ->where($branchQuery)
            ->count();

        $activeWorkOrdersCount = WorkOrder::whereNotIn('current_status', ['SELESAI', 'BATAL'])
            ->where($offerBranchQuery)
            ->count();

        $overdueCount = WorkOrder::whereNotNull('sla_date')
            ->whereDate('sla_date', '<', Carbon::today())
            ->whereNotIn('current_status', ['SELESAI', 'BATAL'])
            ->where($offerBranchQuery)
            ->count();

        $pendingSurveyCount = WorkOrder::where('current_status', 'SURVEY')
            ->where('survey_required', true)
            ->where($offerBranchQuery)
            ->count();

        $reviewQueueCount = WorkOrder::where('current_status', 'REVIEW')
            ->where($offerBranchQuery)
            ->count();

        $printQueueCount = WorkOrder::where('current_status', 'CETAK')
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
        $activeJobFee = WorkOrder::whereNotIn('current_status', ['SELESAI', 'BATAL'])->where($offerBranchQuery)->get()->sum(fn($w) => $w->offer?->fee ?? 0);
        $completedJobFee = WorkOrder::where('current_status', 'SELESAI')->where($offerBranchQuery)->get()->sum(fn($w) => $w->offer?->fee ?? 0);

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

        $branches = Branch::where('active', true)->get();

        return view('livewire.dashboard', [
            'activeOffersCount' => $activeOffersCount,
            'activeWorkOrdersCount' => $activeWorkOrdersCount,
            'overdueCount' => $overdueCount,
            'pendingSurveyCount' => $pendingSurveyCount,
            'reviewQueueCount' => $reviewQueueCount,
            'printQueueCount' => $printQueueCount,
            'completedThisMonthCount' => $completedThisMonthCount,
            'slaComplianceRate' => $slaComplianceRate,
            'totalOfferFee' => $totalOfferFee,
            'activeJobFee' => $activeJobFee,
            'completedJobFee' => $completedJobFee,
            'funnelStatuses' => $funnelStatuses,
            'bottleneckJobs' => $bottleneckJobs,
            'branches' => $branches,
        ])->layout('layouts.app');
    }
}
