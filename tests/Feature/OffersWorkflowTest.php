<?php

namespace Tests\Feature;

use App\Livewire\Offers\Create;
use App\Livewire\Offers\Index;
use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\StatusHistory;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OffersWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $jakarta;

    private Branch $surabaya;

    private Debtor $debtor;

    private Organization $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-12 10:00:00', 'Asia/Jakarta'));
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['role' => 'sysadmin']);
        $this->user->syncRoles(['sysadmin']);

        $this->jakarta = $this->createBranch('JKT', 10, 'Jakarta');
        $this->surabaya = $this->createBranch('SBY', 20, 'Surabaya');
        $this->debtor = Debtor::create(['name' => 'PT Surya Properti']);
        $this->client = Organization::create([
            'name' => 'PT Bank Nusantara',
            'type' => 'pemberi_tugas',
        ]);
    }

    public function test_create_reacts_to_number_and_tax_inputs_then_persists_the_calculated_values(): void
    {
        $this->createOffer(
            branch: $this->jakarta,
            sequence: 4,
            reference: "4/S.Kontrak/KJPP-HJA'R/10/VIII/2026",
        );

        Livewire::actingAs($this->user)
            ->test(Create::class)
            ->assertSet('sequence_no', 5)
            ->assertSet('offer_date', '2026-08-12')
            ->assertSeeHtml('wire:model.live="sequence_no"')
            ->assertSeeHtml('wire:model.live="branch_id"')
            ->assertSeeHtml('wire:model.live="fee"')
            ->assertSeeHtml('wire:model.live="ta"')
            ->assertSeeHtml('wire:submit="save"')
            ->set('branch_id', $this->jakarta->id)
            ->assertSet('offer_no', "5/S.Kontrak/KJPP-HJA'R/10/VIII/2026")
            ->set('fee', 1_250_000)
            ->set('ta', 250_000)
            ->assertSet('dpp', 1_000_000.0)
            ->assertSet('ppn', 110_000.0)
            ->assertSet('pph', 20_000.0)
            ->set('debtor_id', $this->debtor->id)
            ->set('client_id', $this->client->id)
            ->set('outcome', 'DIKIRIM')
            ->set('note', 'Dikirim melalui surel')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('offers.index'));

        $offer = Offer::query()->where('sequence_no', 5)->sole();

        $this->assertSame("5/S.Kontrak/KJPP-HJA'R/10/VIII/2026", $offer->offer_no);
        $this->assertSame($this->user->id, $offer->created_by);
        $this->assertSame($this->jakarta->id, $offer->branch_id);
        $this->assertSame($this->debtor->id, $offer->debtor_id);
        $this->assertSame($this->client->id, $offer->client_id);
        $this->assertSame(1_250_000.0, (float) $offer->fee);
        $this->assertSame(250_000.0, (float) $offer->ta);
        $this->assertSame(1_000_000.0, (float) $offer->dpp);
        $this->assertSame(110_000.0, (float) $offer->ppn);
        $this->assertSame(20_000.0, (float) $offer->pph);
        $this->assertSame('DIKIRIM', $offer->outcome);
        $this->assertSame('Dikirim melalui surel', $offer->note);
    }

    public function test_index_filters_and_edit_modal_keep_their_livewire_contract(): void
    {
        $alpha = $this->createOffer(
            branch: $this->jakarta,
            sequence: 1,
            reference: "1/S.Kontrak/KJPP-HJA'R/10/VIII/2026",
            outcome: 'DRAFT',
            note: 'Alpha prospect',
        );
        $rejected = $this->createOffer(
            branch: $this->jakarta,
            sequence: 2,
            reference: "2/S.Kontrak/KJPP-HJA'R/10/VIII/2026",
            outcome: 'DITOLAK',
        );
        $surabayaDraft = $this->createOffer(
            branch: $this->surabaya,
            sequence: 3,
            reference: "3/S.Kontrak/KJPP-HJA'R/20/VIII/2026",
            outcome: 'DRAFT',
        );

        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"')
            ->assertSeeHtml('wire:model.live="filterOutcome"')
            ->assertSeeHtml('wire:model.live="filterBranchId"')
            ->set('search', $alpha->offer_no)
            ->assertViewHas('offers', fn ($offers): bool => $offers->total() === 1
                && $offers->first()->is($alpha))
            ->set('search', '')
            ->set('filterOutcome', 'DITOLAK')
            ->assertViewHas('offers', fn ($offers): bool => $offers->total() === 1
                && $offers->first()->is($rejected))
            ->set('filterOutcome', '')
            ->set('filterBranchId', $this->surabaya->id)
            ->assertViewHas('offers', fn ($offers): bool => $offers->total() === 1
                && $offers->first()->is($surabayaDraft))
            ->set('filterBranchId', null)
            ->call('edit', $alpha->id)
            ->assertSet('editingId', $alpha->id)
            ->assertSet('showModal', true)
            ->assertSet('sequence_no', 1)
            ->assertSet('branch_id', $this->jakarta->id)
            ->assertSet('outcome', 'DRAFT')
            ->assertSeeHtml('wire:submit="save"')
            ->assertSeeHtml('wire:model.live="sequence_no"')
            ->assertSeeHtml('wire:model.live="fee"')
            ->set('sequence_no', 21)
            ->set('fee', 2_000_000)
            ->set('ta', 500_000)
            ->set('outcome', 'DIKIRIM')
            ->set('note', 'Penawaran diperbarui')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $alpha->refresh();

        $this->assertSame("21/S.Kontrak/KJPP-HJA'R/10/VIII/2026", $alpha->offer_no);
        $this->assertSame(21, $alpha->sequence_no);
        $this->assertSame(2_000_000.0, (float) $alpha->fee);
        $this->assertSame(500_000.0, (float) $alpha->ta);
        $this->assertSame(1_500_000.0, (float) $alpha->dpp);
        $this->assertSame(165_000.0, (float) $alpha->ppn);
        $this->assertSame(30_000.0, (float) $alpha->pph);
        $this->assertSame('DIKIRIM', $alpha->outcome);
        $this->assertSame('Penawaran diperbarui', $alpha->note);
    }

    public function test_prepare_convert_opens_the_modal_and_conversion_creates_one_job_with_one_initial_history(): void
    {
        $offer = $this->createOffer(
            branch: $this->jakarta,
            sequence: 8,
            reference: "8/S.Kontrak/KJPP-HJA'R/10/VIII/2026",
            outcome: 'DIKIRIM',
        );

        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->call('prepareConvert', $offer->id)
            ->assertSet('showConvertModal', true)
            ->assertSet('convertingOffer.id', $offer->id)
            ->assertSet('survey_required', true)
            ->assertSet('sla_date', '2026-08-19')
            ->assertSeeHtml('wire:submit="convertToJob"')
            ->assertSeeHtml('wire:model="sla_date"')
            ->assertSeeHtml('wire:model="survey_required"')
            ->set('sla_date', '2026-08-30')
            ->set('survey_required', false)
            ->call('convertToJob')
            ->assertHasNoErrors()
            ->assertSet('showConvertModal', false)
            ->assertSet('convertingOffer', null);

        $this->assertDatabaseCount('work_orders', 1);
        $this->assertDatabaseCount('status_histories', 1);

        $workOrder = WorkOrder::query()->sole();
        $history = StatusHistory::query()->sole();

        $this->assertSame($offer->id, $workOrder->offer_id);
        $this->assertSame($offer->offer_no, $workOrder->contract_no);
        $this->assertSame('2026-08-12', $workOrder->contract_date->format('Y-m-d'));
        $this->assertSame('2026-08-30', $workOrder->sla_date->format('Y-m-d'));
        $this->assertFalse($workOrder->survey_required);
        $this->assertSame('PERSIAPAN', $workOrder->current_status);
        $this->assertSame($workOrder->id, $history->work_order_id);
        $this->assertNull($history->from_status);
        $this->assertSame('PERSIAPAN', $history->to_status);
        $this->assertSame($this->user->id, $history->changed_by);
        $this->assertSame('Konversi dari Penawaran '.$offer->offer_no, $history->note);
        $this->assertSame('DITERIMA', $offer->fresh()->outcome);
    }

    private function createBranch(string $code, int $numberCode, string $name): Branch
    {
        return Branch::create([
            'code' => $code,
            'number_code' => $numberCode,
            'name' => $name,
            'active' => true,
        ]);
    }

    private function createOffer(
        Branch $branch,
        int $sequence,
        string $reference,
        string $outcome = 'DRAFT',
        string $note = '',
    ): Offer {
        return Offer::create([
            'offer_no' => $reference,
            'sequence_no' => $sequence,
            'offer_date' => '2026-08-12',
            'branch_id' => $branch->id,
            'debtor_id' => $this->debtor->id,
            'client_id' => $this->client->id,
            'fee' => 1_000_000,
            'ta' => 100_000,
            'dpp' => 900_000,
            'ppn' => 99_000,
            'pph' => 18_000,
            'outcome' => $outcome,
            'note' => $note,
            'created_by' => $this->user->id,
        ]);
    }
}
