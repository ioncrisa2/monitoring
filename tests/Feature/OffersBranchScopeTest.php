<?php

namespace Tests\Feature;

use App\Livewire\Offers\Create;
use App\Livewire\Offers\Index;
use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OffersBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $ownBranch;

    private Branch $foreignBranch;

    private User $admin;

    private Organization $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->ownBranch = Branch::create([
            'code' => 'OWN',
            'number_code' => 10,
            'name' => 'Cabang Sendiri',
            'active' => true,
        ]);
        $this->foreignBranch = Branch::create([
            'code' => 'OTH',
            'number_code' => 20,
            'name' => 'Cabang Lain',
            'active' => true,
        ]);
        $this->admin = User::factory()->create([
            'branch_id' => $this->ownBranch->id,
            'role' => 'admin',
        ]);
        $this->admin->syncRoles(['admin']);
        $this->client = Organization::create([
            'name' => 'PT Pemberi Tugas',
            'type' => 'pemberi_tugas',
        ]);
    }

    public function test_offer_index_and_search_remain_scoped_to_the_users_branch(): void
    {
        $ownDebtor = Debtor::create(['name' => 'Debitur Sendiri']);
        $foreignDebtor = Debtor::create(['name' => 'Rahasia Cabang Lain']);
        $ownOffer = $this->createOffer($this->ownBranch, $ownDebtor, 1);
        $foreignOffer = $this->createOffer($this->foreignBranch, $foreignDebtor, 2);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->assertViewHas('offers', fn ($offers): bool => $offers->total() === 1
                && $offers->first()->is($ownOffer))
            ->set('search', 'Rahasia Cabang Lain')
            ->assertViewHas('offers', fn ($offers): bool => $offers->total() === 0)
            ->call('edit', $foreignOffer->id)
            ->assertForbidden();

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('prepareConvert', $foreignOffer->id)
            ->assertForbidden();
    }

    public function test_offer_creation_rejects_a_foreign_branch_even_when_the_id_is_tampered(): void
    {
        $debtor = Debtor::create(['name' => 'Debitur Baru']);

        Livewire::actingAs($this->admin)
            ->test(Create::class)
            ->assertViewHas('branches', fn ($branches): bool => $branches->modelKeys() === [$this->ownBranch->id])
            ->set('sequence_no', 1)
            ->set('offer_date', '2026-08-12')
            ->set('branch_id', $this->foreignBranch->id)
            ->set('debtor_id', $debtor->id)
            ->set('client_id', $this->client->id)
            ->set('fee', 1_000_000)
            ->set('ta', 0)
            ->set('dpp', 1_000_000)
            ->set('ppn', 110_000)
            ->set('pph', 20_000)
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseCount('offers', 0);
    }

    public function test_cross_branch_permission_allows_explicit_cross_branch_access(): void
    {
        $ownDebtor = Debtor::create(['name' => 'Debitur Sendiri']);
        $foreignDebtor = Debtor::create(['name' => 'Debitur Cabang Lain']);
        $this->createOffer($this->ownBranch, $ownDebtor, 1);
        $foreignOffer = $this->createOffer($this->foreignBranch, $foreignDebtor, 2);
        $this->admin->givePermissionTo('offers.cross-branch');

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->assertViewHas('offers', fn ($offers): bool => $offers->total() === 2)
            ->call('edit', $foreignOffer->id)
            ->assertSet('editingId', $foreignOffer->id)
            ->assertSet('showModal', true);
    }

    private function createOffer(Branch $branch, Debtor $debtor, int $sequence): Offer
    {
        return Offer::create([
            'offer_no' => $sequence."/S.Kontrak/KJPP-HJA'R/{$branch->number_code}/VIII/2026",
            'sequence_no' => $sequence,
            'offer_date' => '2026-08-12',
            'branch_id' => $branch->id,
            'debtor_id' => $debtor->id,
            'client_id' => $this->client->id,
            'fee' => 1_000_000,
            'ta' => 0,
            'dpp' => 1_000_000,
            'ppn' => 110_000,
            'pph' => 20_000,
            'outcome' => 'DRAFT',
            'created_by' => $this->admin->id,
        ]);
    }
}
