<?php

namespace Tests\Feature;

use App\Livewire\WorkOrders\Show;
use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkOrderDetailViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_detail_tabs_and_modal_entry_points_render_without_changing_their_livewire_contract(): void
    {
        $user = User::factory()->create(['role' => 'sysadmin']);
        $workOrder = $this->createWorkOrder($user);

        Livewire::actingAs($user)
            ->test(Show::class, ['id' => $workOrder->id])
            ->assertSet('activeTab', 'info')
            ->assertSee($workOrder->contract_no)
            ->assertSeeHtml('role="tablist"')
            ->set('activeTab', 'assets')
            ->assertSee('Objek aset penilaian')
            ->set('activeTab', 'reports')
            ->assertSee('Laporan resmi')
            ->set('activeTab', 'documents')
            ->assertSee('Arsip dokumen')
            ->call('createAsset')
            ->assertSet('showAssetModal', true)
            ->assertSee('Tambah Objek Aset Baru')
            ->set('showAssetModal', false)
            ->call('createReport')
            ->assertSet('showReportModal', true)
            ->assertSee('Terbitkan Laporan Resmi Baru')
            ->set('showReportModal', false)
            ->set('showDeliveryModal', true)
            ->assertSee('Status & resi pengiriman laporan', escape: false)
            ->set('showDeliveryModal', false)
            ->call('openDocumentModal')
            ->assertSet('showDocumentModal', true)
            ->assertSee('Unggah dokumen ke arsip')
            ->set('showDocumentModal', false)
            ->call('openStatusModal', 'REVIEW')
            ->assertSet('showStatusModal', true)
            ->assertSet('next_status', 'REVIEW')
            ->assertSee('Ubah status pekerjaan')
            ->set('showStatusModal', false)
            ->set('showAssignModal', true)
            ->assertSee('Penugasan PIC')
            ->set('showAssignModal', false)
            ->set('showSlaModal', true)
            ->assertSee('Atur SLA & survey', escape: false);
    }

    public function test_surveyor_only_sees_survey_actions_not_sla_pic_status_review_or_asset_management_controls(): void
    {
        $surveyor = User::factory()->create(['role' => 'surveyor']);
        $workOrder = $this->createWorkOrder($surveyor);

        $component = Livewire::actingAs($surveyor)
            ->test(Show::class, ['id' => $workOrder->id])
            ->assertDontSee('Atur SLA & survey', escape: false)
            ->assertDontSee('Atur PIC')
            ->set('activeTab', 'assets')
            ->assertDontSeeHtml('wire:click="createAsset"')
            ->assertSee('hanya dapat ditambah atau diubah oleh admin')
            ->set('activeTab', 'reports')
            ->assertDontSeeHtml('wire:click="createReport"')
            ->set('activeTab', 'documents')
            ->assertDontSeeHtml('wire:click="openDocumentModal"');

        // The "Ubah" shortcuts and status stepper must not open forms the surveyor cannot submit.
        $component->set('showSlaModal', true)->assertDontSee('Membutuhkan survey lapangan');
        $component->set('showAssignModal', true)->assertDontSee('Pilih surveyor');
        $component->set('showStatusModal', true)->assertDontSee('Status baru');

        $this->assertFalse($surveyor->can('work-orders.edit-sla'));
        $this->assertFalse($surveyor->can('work-orders.assign-pic'));
        $this->assertFalse($surveyor->can('work-orders.change-status'));
        $this->assertFalse($surveyor->can('work-orders.review'));
        $this->assertFalse($surveyor->can('work-orders.manage-assets'));
        $this->assertTrue($surveyor->can('work-orders.survey'));
    }

    public function test_reviewer_cannot_manage_assets_either(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $workOrder = $this->createWorkOrder($reviewer);

        Livewire::actingAs($reviewer)
            ->test(Show::class, ['id' => $workOrder->id])
            ->set('activeTab', 'assets')
            ->assertDontSeeHtml('wire:click="createAsset"')
            ->assertSee('hanya dapat ditambah atau diubah oleh admin')
            ->call('saveAsset')
            ->assertForbidden();

        $this->assertFalse($reviewer->can('work-orders.manage-assets'));
    }

    public function test_surveyor_sees_asset_data_read_only_in_an_accordion(): void
    {
        $surveyor = User::factory()->create(['role' => 'surveyor']);
        $workOrder = $this->createWorkOrder($surveyor);
        $workOrder->assets()->create([
            'asset_type' => 'tanah_bangunan',
            'address' => 'Jl. Contoh No. 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'description' => 'Rumah tinggal dua lantai',
        ]);

        Livewire::actingAs($surveyor)
            ->test(Show::class, ['id' => $workOrder->id])
            ->set('activeTab', 'assets')
            ->assertSeeHtml('<details')
            ->assertSee('Jl. Contoh No. 1')
            ->assertSee('Rumah tinggal dua lantai')
            ->assertDontSeeHtml('wire:click="editAsset(');
    }

    public function test_supervisor_still_sees_all_management_controls(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $workOrder = $this->createWorkOrder($supervisor);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['id' => $workOrder->id])
            ->assertSee('Atur SLA & survey', escape: false)
            ->assertSee('Atur PIC')
            ->set('activeTab', 'assets')
            ->assertSee('Tambah objek aset')
            ->set('activeTab', 'reports')
            ->assertSee('Terbitkan laporan')
            ->set('activeTab', 'documents')
            ->assertSee('Unggah dokumen');
    }

    public function test_admin_who_creates_offers_can_manage_assets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workOrder = $this->createWorkOrder($admin);

        Livewire::actingAs($admin)
            ->test(Show::class, ['id' => $workOrder->id])
            ->set('activeTab', 'assets')
            ->assertSee('Tambah objek aset');

        $this->assertTrue($admin->can('work-orders.manage-assets'));
    }

    public function test_surveyor_can_mark_survey_complete_and_advance_to_pengerjaan(): void
    {
        $surveyor = User::factory()->create(['role' => 'surveyor']);
        $workOrder = $this->createWorkOrder($surveyor, 'SURVEY');

        Livewire::actingAs($surveyor)
            ->test(Show::class, ['id' => $workOrder->id])
            ->assertSeeHtml('wire:click="markSurveyComplete"')
            ->call('markSurveyComplete')
            ->assertSet('workOrder.current_status', 'PENGERJAAN');

        $this->assertSame('PENGERJAAN', $workOrder->fresh()->current_status);
        $this->assertDatabaseHas('status_histories', [
            'work_order_id' => $workOrder->id,
            'from_status' => 'SURVEY',
            'to_status' => 'PENGERJAAN',
            'changed_by' => $surveyor->id,
        ]);
    }

    public function test_reviewer_can_mark_review_complete_and_advance_to_cetak(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $workOrder = $this->createWorkOrder($reviewer, 'REVIEW');

        Livewire::actingAs($reviewer)
            ->test(Show::class, ['id' => $workOrder->id])
            ->assertSeeHtml('wire:click="markReviewComplete"')
            ->call('markReviewComplete')
            ->assertSet('workOrder.current_status', 'CETAK');

        $this->assertSame('CETAK', $workOrder->fresh()->current_status);
        $this->assertDatabaseHas('status_histories', [
            'work_order_id' => $workOrder->id,
            'from_status' => 'REVIEW',
            'to_status' => 'CETAK',
            'changed_by' => $reviewer->id,
        ]);
    }

    public function test_mark_complete_buttons_are_scoped_to_the_matching_role_and_stage(): void
    {
        $surveyor = User::factory()->create(['role' => 'surveyor']);
        $reviewer = User::factory()->create(['role' => 'reviewer']);

        // Surveyor lacks work-orders.review, so they cannot mark a review-stage job complete.
        $reviewStageOrder = $this->createWorkOrder($surveyor, 'REVIEW');
        Livewire::actingAs($surveyor)
            ->test(Show::class, ['id' => $reviewStageOrder->id])
            ->assertDontSeeHtml('wire:click="markReviewComplete"')
            ->call('markReviewComplete')
            ->assertForbidden();

        // Reviewer lacks work-orders.survey, so they cannot mark a survey-stage job complete.
        $surveyStageOrder = $this->createWorkOrder($reviewer, 'SURVEY');
        Livewire::actingAs($reviewer)
            ->test(Show::class, ['id' => $surveyStageOrder->id])
            ->assertDontSeeHtml('wire:click="markSurveyComplete"')
            ->call('markSurveyComplete')
            ->assertForbidden();

        // The surveyor button only appears while the job is actually at the Survey stage.
        $persiapanOrder = $this->createWorkOrder($surveyor, 'PERSIAPAN');
        Livewire::actingAs($surveyor)
            ->test(Show::class, ['id' => $persiapanOrder->id])
            ->assertDontSeeHtml('wire:click="markSurveyComplete"');
    }

    private static int $workOrderSequence = 0;

    private function createWorkOrder(User $user, string $status = 'PERSIAPAN'): WorkOrder
    {
        $sequence = ++static::$workOrderSequence;

        $branch = Branch::create([
            'code' => 'JKT'.$sequence,
            'name' => 'Kantor Pusat Jakarta',
            'active' => true,
        ]);

        $client = Organization::create([
            'name' => 'PT Bank Nusantara',
            'type' => 'pemberi_tugas',
        ]);

        $debtor = Debtor::create([
            'name' => 'PT Surya Properti',
        ]);

        $offer = Offer::create([
            'offer_no' => "{$sequence}/S.Kontrak/KJPP-HJA/R/0/VIII/2026",
            'sequence_no' => $sequence,
            'offer_date' => '2026-08-12',
            'branch_id' => $branch->id,
            'debtor_id' => $debtor->id,
            'client_id' => $client->id,
            'fee' => 32000000,
            'dpp' => 28000000,
            'ppn' => 3080000,
            'pph' => 560000,
            'outcome' => 'DITERIMA',
            'created_by' => $user->id,
        ]);

        return WorkOrder::create([
            'offer_id' => $offer->id,
            'contract_no' => $offer->offer_no,
            'contract_date' => '2026-08-12',
            'survey_required' => true,
            'sla_date' => '2026-08-26',
            'current_status' => $status,
            'started_at' => now(),
        ]);
    }
}
