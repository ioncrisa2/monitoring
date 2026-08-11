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

    private function createWorkOrder(User $user): WorkOrder
    {
        $branch = Branch::create([
            'code' => 'JKT',
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
            'offer_no' => '1/S.Kontrak/KJPP-HJA/R/0/VIII/2026',
            'sequence_no' => 1,
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
            'current_status' => 'PERSIAPAN',
            'started_at' => now(),
        ]);
    }
}
