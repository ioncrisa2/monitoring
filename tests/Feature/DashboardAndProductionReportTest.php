<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Reports\ProductionReport;
use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardAndProductionReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $client;

    private Debtor $debtor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-12 10:00:00', 'Asia/Jakarta'));
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['role' => 'sysadmin']);
        $this->user->syncRoles(['sysadmin']);
        $this->client = Organization::create([
            'name' => 'PT Bank Nusantara',
            'type' => 'pemberi_tugas',
        ]);
        $this->debtor = Debtor::create(['name' => 'PT Surya Properti']);
    }

    public function test_dashboard_metrics_and_pipeline_follow_the_selected_branch(): void
    {
        $jakarta = $this->createBranch('JKT', 'Jakarta');
        $surabaya = $this->createBranch('SBY', 'Surabaya');
        $inactive = $this->createBranch('BDG', 'Bandung', false);

        $this->createWorkOrder($jakarta, 'JKT-001', 100_000, 'DITERIMA', 'REVIEW', '2026-08-10');
        $this->createWorkOrder($jakarta, 'JKT-002', 150_000, 'DITERIMA', 'SURVEY', '2026-08-20');
        $this->createWorkOrder($jakarta, 'JKT-003', 200_000, 'DITERIMA', 'SELESAI', null, '2026-08-03');
        $this->createOffer($jakarta, 'JKT-004', 50_000, 'DRAFT');

        $this->createWorkOrder($surabaya, 'SBY-001', 1_000_000, 'DITERIMA', 'PERSIAPAN', '2026-08-01');
        $this->createWorkOrder($surabaya, 'SBY-002', 2_000_000, 'DITERIMA', 'SELESAI', null, '2026-08-04');
        $this->createOffer($surabaya, 'SBY-003', 500_000, 'DIKIRIM');

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSet('selectedBranchId', null)
            ->assertSeeHtml('wire:model.live="selectedBranchId"')
            ->assertViewHas('activeWorkOrdersCount', 3)
            ->assertViewHas('overdueCount', 2)
            ->set('selectedBranchId', $jakarta->id)
            ->assertViewHas('activeWorkOrdersCount', 2)
            ->assertViewHas('overdueCount', 1)
            ->assertViewHas('completedThisMonthCount', 1)
            ->assertViewHas('slaComplianceRate', 66.7)
            ->assertViewHas('totalOfferFee', 50_000)
            ->assertViewHas('activeJobFee', 250_000)
            ->assertViewHas('completedJobFee', 200_000)
            ->assertViewHas('funnelStatuses', fn (array $statuses): bool => $statuses === [
                'PERSIAPAN' => 0,
                'SURVEY' => 1,
                'PENGERJAAN' => 0,
                'REVIEW' => 1,
                'CETAK' => 0,
                'SELESAI' => 1,
            ])
            ->assertViewHas('bottleneckJobs', fn ($jobs): bool => $jobs->count() === 2
                && $jobs->every(fn (WorkOrder $job): bool => $job->offer->branch_id === $jakarta->id))
            ->assertViewHas('branches', fn ($branches): bool => $branches->pluck('id')->sort()->values()->all() === [
                $jakarta->id,
                $surabaya->id,
            ]
                && ! $branches->contains('id', $inactive->id));
    }

    public function test_production_report_revenue_views_are_branch_scoped_but_ignore_table_filters(): void
    {
        $jakarta = $this->createBranch('JKT', 'Jakarta');
        $surabaya = $this->createBranch('SBY', 'Surabaya');

        $this->createWorkOrder($jakarta, 'JKT-2025', 400_000, 'DITERIMA', 'SELESAI', null, '2025-12-20');
        $this->createWorkOrder($jakarta, 'JKT-JAN', 100_000, 'DITERIMA', 'SELESAI', null, '2026-01-15');
        $this->createWorkOrder($jakarta, 'JKT-MAR', 300_000, 'DITOLAK', 'SELESAI', null, '2026-03-21');
        $this->createWorkOrder($jakarta, 'JKT-ACTIVE', 75_000, 'DIKIRIM', 'PERSIAPAN', '2026-08-25');
        $this->createOffer($jakarta, 'JKT-DRAFT', 50_000, 'DRAFT');

        $this->createWorkOrder($surabaya, 'SBY-JAN', 1_000_000, 'DITERIMA', 'SELESAI', null, '2026-01-10');
        $this->createOffer($surabaya, 'SBY-REJECTED', 25_000, 'DITOLAK');

        $component = Livewire::actingAs($this->user)
            ->test(ProductionReport::class)
            ->assertSet('revenueView', 'monthly')
            ->assertSeeHtml('wire:model.live="selectedBranchId"')
            ->assertSeeHtml('wire:model.live="selectedStatus"')
            ->assertSeeHtml('wire:model.live="fromDate"')
            ->assertSeeHtml('wire:model.live="toDate"')
            ->set('selectedBranchId', $jakarta->id)
            ->assertViewHas('revenueLabels', ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'])
            ->assertViewHas('revenueValues', fn (array $values): bool => (float) $values[0] === 100_000.0
                && (float) $values[2] === 300_000.0
                && array_sum($values) === 400_000.0)
            ->assertViewHas('offerOutcomeCounts', [
                'DRAFT' => 1,
                'DIKIRIM' => 1,
                'DITERIMA' => 2,
                'TIDAK_LANJUT' => 0,
                'DITOLAK' => 1,
            ])
            ->assertViewHas('decidedCount', 3)
            ->assertViewHas('conversionRate', 66.7)
            ->set('selectedStatus', 'BATAL')
            ->set('fromDate', '2030-01-01')
            ->set('toDate', '2030-12-31')
            ->assertViewHas('workOrders', fn ($workOrders): bool => $workOrders->total() === 0)
            ->assertViewHas('revenueValues', fn (array $values): bool => array_sum($values) === 400_000.0)
            ->assertViewHas('offerOutcomeCounts', fn (array $counts): bool => array_sum($counts) === 5);

        $component
            ->set('revenueView', 'yearly')
            ->assertViewHas('revenueLabels', ['2025', '2026'])
            ->assertViewHas('revenueValues', fn (array $values): bool => array_map('floatval', $values) === [400_000.0, 400_000.0]);
    }

    public function test_dashboard_and_production_report_have_explicit_empty_states(): void
    {
        $this->createBranch('JKT', 'Jakarta');

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertViewHas('activeWorkOrdersCount', 0)
            ->assertViewHas('overdueCount', 0)
            ->assertViewHas('completedThisMonthCount', 0)
            ->assertViewHas('slaComplianceRate', 100)
            ->assertSee('Tidak ada pekerjaan aktif yang tertahan.');

        Livewire::actingAs($this->user)
            ->test(ProductionReport::class)
            ->assertViewHas('workOrders', fn ($workOrders): bool => $workOrders->total() === 0)
            ->assertViewHas('revenueValues', fn (array $values): bool => count($values) === 12 && array_sum($values) === 0.0)
            ->assertViewHas('offerOutcomeCounts', fn (array $counts): bool => array_sum($counts) === 0)
            ->assertViewHas('decidedCount', 0)
            ->assertViewHas('conversionRate', 0)
            ->assertSee('Belum ada pekerjaan selesai pada periode analitik ini.')
            ->assertSee('Tidak ada data produksi yang sesuai dengan filter.');
    }

    private function createBranch(string $code, string $name, bool $active = true): Branch
    {
        return Branch::create([
            'code' => $code,
            'name' => $name,
            'active' => $active,
        ]);
    }

    private function createOffer(
        Branch $branch,
        string $reference,
        int $fee,
        string $outcome,
    ): Offer {
        static $sequence = 0;

        $sequence++;

        return Offer::create([
            'offer_no' => "{$reference}/VIII/2026",
            'sequence_no' => $sequence,
            'offer_date' => '2026-08-01',
            'branch_id' => $branch->id,
            'debtor_id' => $this->debtor->id,
            'client_id' => $this->client->id,
            'fee' => $fee,
            'outcome' => $outcome,
            'created_by' => $this->user->id,
        ]);
    }

    private function createWorkOrder(
        Branch $branch,
        string $reference,
        int $fee,
        string $outcome,
        string $status,
        ?string $slaDate = null,
        ?string $completedAt = null,
    ): WorkOrder {
        $offer = $this->createOffer($branch, $reference, $fee, $outcome);

        return WorkOrder::create([
            'offer_id' => $offer->id,
            'contract_no' => "CONTRACT-{$reference}",
            'contract_date' => '2026-08-01',
            'survey_required' => true,
            'sla_date' => $slaDate,
            'current_status' => $status,
            'started_at' => '2026-08-01 08:00:00',
            'completed_at' => $completedAt,
        ]);
    }
}
