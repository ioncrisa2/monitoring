<?php

namespace Tests\Feature;

use App\Livewire\Master\Organizations;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterOrganizationsTest extends TestCase
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

    public function test_route_requires_authentication_and_authenticated_user_can_render_organizations(): void
    {
        $this->get(route('master.organizations'))
            ->assertRedirect(route('login'));

        $this->actingAs($this->user)
            ->get(route('master.organizations'))
            ->assertOk()
            ->assertSee('Daftar organisasi');
    }

    public function test_search_and_type_filters_return_the_expected_organizations(): void
    {
        $namedOrganization = $this->createOrganization(
            'PT Mentari Valuasi',
            'pemberi_tugas',
            'Jalan Merdeka No. 1',
            '01.001.001.1-001.000',
            '021-555-0101',
        );
        $taxIdOrganization = $this->createOrganization(
            'PT Nusantara Sejahtera',
            'pengguna_laporan',
            'Jalan Sudirman No. 2',
            'NPWP-SBY-777',
            '031-555-0202',
        );
        $directClient = $this->createOrganization(
            'PT Klien Langsung',
            'klien',
            'Jalan Diponegoro No. 3',
            '03.003.003.3-003.000',
            '022-555-0303',
        );
        $this->createOrganization(
            'Yayasan Cakrawala',
            'lainnya',
            'Jalan Asia Afrika No. 4',
            '04.004.004.4-004.000',
            '022-555-0404',
        );

        Livewire::actingAs($this->user)
            ->test(Organizations::class)
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"')
            ->assertSeeHtml('wire:model.live="filterType"')
            ->set('search', 'Mentari')
            ->assertViewHas('organizations', fn ($organizations): bool => $organizations->total() === 1
                && $organizations->first()->is($namedOrganization))
            ->set('search', 'NPWP-SBY-777')
            ->assertViewHas('organizations', fn ($organizations): bool => $organizations->total() === 1
                && $organizations->first()->is($taxIdOrganization))
            ->set('search', '')
            ->set('filterType', 'klien')
            ->assertViewHas('organizations', fn ($organizations): bool => $organizations->total() === 1
                && $organizations->first()->is($directClient));
    }

    public function test_create_modal_has_expected_semantics_defaults_bindings_and_saves_an_organization(): void
    {
        Livewire::actingAs($this->user)
            ->test(Organizations::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('type', 'pemberi_tugas')
            ->assertSet('address', '')
            ->assertSet('tax_id', '')
            ->assertSet('phone', '')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-labelledby="organization-editor-title"')
            ->assertSeeHtml('wire:submit="save"')
            ->assertSeeHtml('wire:model="name"')
            ->assertSeeHtml('wire:model="type"')
            ->assertSeeHtml('wire:model="tax_id"')
            ->assertSeeHtml('wire:model="phone"')
            ->assertSeeHtml('wire:model="address"')
            ->set('name', 'PT Surya Penilai Indonesia')
            ->set('type', 'pengguna_laporan')
            ->set('address', 'Jalan Gatot Subroto No. 10, Jakarta')
            ->set('tax_id', '09.999.999.9-999.000')
            ->set('phone', '021-555-9090')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('organizations', [
            'name' => 'PT Surya Penilai Indonesia',
            'type' => 'pengguna_laporan',
            'address' => 'Jalan Gatot Subroto No. 10, Jakarta',
            'tax_id' => '09.999.999.9-999.000',
            'phone' => '021-555-9090',
        ]);
    }

    public function test_edit_prefills_the_modal_and_saves_the_changes(): void
    {
        $organization = $this->createOrganization(
            'PT Organisasi Lama',
            'pemberi_tugas',
            'Jalan Lama No. 5',
            '05.005.005.5-005.000',
            '021-555-0505',
        );

        Livewire::actingAs($this->user)
            ->test(Organizations::class)
            ->call('edit', $organization->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $organization->id)
            ->assertSet('name', 'PT Organisasi Lama')
            ->assertSet('type', 'pemberi_tugas')
            ->assertSet('address', 'Jalan Lama No. 5')
            ->assertSet('tax_id', '05.005.005.5-005.000')
            ->assertSet('phone', '021-555-0505')
            ->assertSee('Edit organisasi')
            ->set('name', 'PT Organisasi Baru')
            ->set('type', 'klien')
            ->set('address', 'Jalan Baru No. 15')
            ->set('tax_id', '15.015.015.5-015.000')
            ->set('phone', '021-555-1515')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $organization->refresh();

        $this->assertSame('PT Organisasi Baru', $organization->name);
        $this->assertSame('klien', $organization->type);
        $this->assertSame('Jalan Baru No. 15', $organization->address);
        $this->assertSame('15.015.015.5-015.000', $organization->tax_id);
        $this->assertSame('021-555-1515', $organization->phone);
    }

    public function test_delete_removes_the_organization(): void
    {
        $organization = $this->createOrganization(
            'PT Organisasi Dihapus',
            'lainnya',
            'Jalan Sementara No. 7',
            '07.007.007.7-007.000',
            '021-555-0707',
        );

        Livewire::actingAs($this->user)
            ->test(Organizations::class)
            ->call('delete', $organization->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('organizations', [
            'id' => $organization->id,
        ]);
    }

    private function createOrganization(
        string $name,
        string $type,
        string $address,
        string $taxId,
        string $phone,
    ): Organization {
        return Organization::create([
            'name' => $name,
            'type' => $type,
            'address' => $address,
            'tax_id' => $taxId,
            'phone' => $phone,
        ]);
    }
}
