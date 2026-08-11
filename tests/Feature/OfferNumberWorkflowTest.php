<?php

namespace Tests\Feature;

use App\Livewire\Offers\Create;
use App\Livewire\Offers\Index;
use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\OfferNumberCounter;
use App\Models\Organization;
use App\Models\User;
use App\Services\Offers\OfferNumberAllocator;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OfferNumberWorkflowTest extends TestCase
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
        $this->debtor = Debtor::create(['name' => 'PT Debitur Utama']);
        $this->client = Organization::create([
            'name' => 'PT Bank Pemberi Tugas',
            'type' => 'pemberi_tugas',
        ]);
    }

    public function test_stale_or_tampered_previews_receive_distinct_server_allocated_numbers(): void
    {
        $first = $this->validCreateComponent();
        $second = $this->validCreateComponent()
            ->set('branch_id', $this->surabaya->id)
            ->set('sequence_no', 99)
            ->assertSet('offer_no', "99/S.Kontrak/KJPP-HJA'R/20/VIII/2026");

        $first
            ->assertSet('sequence_no', 1)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('offers.index'));

        $second
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('offers.index'));

        $offers = Offer::query()->orderBy('sequence_no')->get();

        $this->assertSame([1, 2], $offers->pluck('sequence_no')->all());
        $this->assertSame([
            "1/S.Kontrak/KJPP-HJA'R/10/VIII/2026",
            "2/S.Kontrak/KJPP-HJA'R/20/VIII/2026",
        ], $offers->pluck('offer_no')->all());
        $this->assertNotNull($offers[0]->current_number_allocation_id);
        $this->assertNotNull($offers[1]->current_number_allocation_id);
        $this->assertSame([2026, 2026], $offers->pluck('sequence_year')->all());
        $this->assertDatabaseCount('offer_number_allocations', 2);
        $this->assertFalse(Offer::query()->where('offer_no', 'like', 'PENDING-%')->exists());
        $this->assertSame(
            2,
            OfferNumberCounter::query()
                ->where('scope_key', OfferNumberAllocator::GLOBAL_SCOPE)
                ->where('sequence_year', 2026)
                ->sole()
                ->last_sequence,
        );
    }

    public function test_offer_creation_rolls_back_placeholder_when_number_allocation_fails(): void
    {
        $allocator = Mockery::mock(OfferNumberAllocator::class);
        $allocator->shouldReceive('allocate')
            ->once()
            ->andThrow(new RuntimeException('Allocation failed.'));
        $this->app->instance(OfferNumberAllocator::class, $allocator);

        try {
            $this->validCreateComponent()->call('save');
            $this->fail('The allocator exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Allocation failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('offers', 0);
        $this->assertDatabaseCount('offer_number_allocations', 0);
    }

    public function test_allocated_number_identity_is_locked_while_business_fields_remain_editable(): void
    {
        $this->validCreateComponent()->call('save')->assertHasNoErrors();
        $offer = Offer::query()->sole();
        $originalIdentity = $offer->only(['offer_no', 'sequence_no', 'sequence_year', 'offer_date', 'branch_id']);

        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->call('edit', $offer->id)
            ->assertSet('numberLocked', true)
            ->assertSee('Nomor sudah dialokasikan dan tidak dapat diubah.')
            ->set('sequence_no', 77)
            ->assertSet('sequence_no', $offer->sequence_no)
            ->set('offer_date', '2027-01-03')
            ->assertSet('offer_date', $offer->offer_date->format('Y-m-d'))
            ->set('branch_id', $this->surabaya->id)
            ->assertSet('branch_id', $offer->branch_id)
            ->set('fee', 2_500_000)
            ->set('ta', 500_000)
            ->set('note', 'Data bisnis masih dapat diperbarui')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $offer->refresh();

        $this->assertSame($originalIdentity['offer_no'], $offer->offer_no);
        $this->assertSame($originalIdentity['sequence_no'], $offer->sequence_no);
        $this->assertSame($originalIdentity['sequence_year'], $offer->sequence_year);
        $this->assertSame($originalIdentity['offer_date']->format('Y-m-d'), $offer->offer_date->format('Y-m-d'));
        $this->assertSame($originalIdentity['branch_id'], $offer->branch_id);
        $this->assertSame(2_500_000.0, (float) $offer->fee);
        $this->assertSame(2_000_000.0, (float) $offer->dpp);
        $this->assertSame('Data bisnis masih dapat diperbarui', $offer->note);
    }

    public function test_server_rejects_forged_offer_number_even_if_client_lock_state_is_tampered(): void
    {
        $this->validCreateComponent()->call('save')->assertHasNoErrors();
        $offer = Offer::query()->sole();

        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->call('edit', $offer->id)
            ->set('numberLocked', false)
            ->set('offer_no', 'FORGED/NUMBER')
            ->call('save')
            ->assertHasErrors(['sequence_no'])
            ->assertSet('numberLocked', true)
            ->assertSet('offer_no', $offer->offer_no);

        $this->assertSame($offer->offer_no, $offer->fresh()->offer_no);
    }

    private function validCreateComponent()
    {
        return Livewire::actingAs($this->user)
            ->test(Create::class)
            ->set('branch_id', $this->jakarta->id)
            ->set('debtor_id', $this->debtor->id)
            ->set('client_id', $this->client->id)
            ->set('fee', 1_000_000)
            ->set('ta', 100_000)
            ->set('outcome', 'DRAFT');
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
}
