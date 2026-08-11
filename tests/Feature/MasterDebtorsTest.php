<?php

namespace Tests\Feature;

use App\Livewire\Master\Debtors;
use App\Models\Debtor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDebtorsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['role' => 'sysadmin']);
        $this->user->syncRoles(['sysadmin']);
    }

    public function test_authenticated_user_can_render_and_search_debtors_by_name_or_identifier(): void
    {
        $namedDebtor = $this->createDebtor(
            'PT Mentari Properti',
            'DEB-MTR-001',
            'Jakarta Pusat',
        );
        $identifiedDebtor = $this->createDebtor(
            'PT Nusantara Sentosa',
            'DEB-SBY-002',
            'Surabaya Timur',
        );
        $this->createDebtor('PT Cakrawala Utama', 'DEB-BDG-003', 'Kawasan Mentari, Bandung');

        $this->actingAs($this->user)
            ->get(route('master.debtors'))
            ->assertOk()
            ->assertSee('Daftar debitur');

        Livewire::actingAs($this->user)
            ->test(Debtors::class)
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"')
            ->set('search', 'Mentari')
            ->assertViewHas('debtors', fn ($debtors): bool => $debtors->total() === 1
                && $debtors->first()->is($namedDebtor))
            ->set('search', 'DEB-SBY-002')
            ->assertViewHas('debtors', fn ($debtors): bool => $debtors->total() === 1
                && $debtors->first()->is($identifiedDebtor));
    }

    public function test_create_modal_has_expected_semantics_bindings_and_saves_a_debtor(): void
    {
        Livewire::actingAs($this->user)
            ->test(Debtors::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('identifier', '')
            ->assertSet('address', '')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-labelledby="debtor-editor-title"')
            ->assertSeeHtml('wire:submit="save"')
            ->assertSeeHtml('wire:model="name"')
            ->assertSeeHtml('wire:model="identifier"')
            ->assertSeeHtml('wire:model="address"')
            ->set('name', 'PT Surya Citra Kencana')
            ->set('identifier', 'DEB-2026-001')
            ->set('address', 'Jalan Merdeka No. 10, Jakarta')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('debtors', [
            'name' => 'PT Surya Citra Kencana',
            'identifier' => 'DEB-2026-001',
            'address' => 'Jalan Merdeka No. 10, Jakarta',
        ]);
    }

    public function test_edit_prefills_the_modal_and_saves_the_changes(): void
    {
        $debtor = $this->createDebtor(
            'PT Bumi Lama',
            'DEB-LAMA-001',
            'Jalan Lama No. 1',
        );

        Livewire::actingAs($this->user)
            ->test(Debtors::class)
            ->call('edit', $debtor->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $debtor->id)
            ->assertSet('name', 'PT Bumi Lama')
            ->assertSet('identifier', 'DEB-LAMA-001')
            ->assertSet('address', 'Jalan Lama No. 1')
            ->assertSee('Edit debitur')
            ->set('name', 'PT Bumi Baru')
            ->set('identifier', 'DEB-BARU-002')
            ->set('address', 'Jalan Baru No. 2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $debtor->refresh();

        $this->assertSame('PT Bumi Baru', $debtor->name);
        $this->assertSame('DEB-BARU-002', $debtor->identifier);
        $this->assertSame('Jalan Baru No. 2', $debtor->address);
    }

    public function test_delete_removes_the_debtor(): void
    {
        $debtor = $this->createDebtor(
            'PT Debitur Dihapus',
            'DEB-HAPUS-001',
            'Jalan Sementara No. 3',
        );

        Livewire::actingAs($this->user)
            ->test(Debtors::class)
            ->call('delete', $debtor->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('debtors', [
            'id' => $debtor->id,
        ]);
    }

    private function createDebtor(string $name, string $identifier, string $address): Debtor
    {
        return Debtor::create([
            'name' => $name,
            'identifier' => $identifier,
            'address' => $address,
        ]);
    }
}
