<?php

namespace Tests\Feature;

use App\Livewire\Master\Branches;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterBranchesTest extends TestCase
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

    public function test_authenticated_user_can_render_and_search_branches_by_name_or_code(): void
    {
        $jakarta = $this->createBranch('JKT', 10, 'Jakarta Pusat');
        $surabaya = $this->createBranch('SBY', 20, 'Surabaya Timur');
        $this->createBranch('BDG', 30, 'Bandung');

        $this->actingAs($this->user)
            ->get(route('master.branches'))
            ->assertOk()
            ->assertSee('Daftar cabang');

        Livewire::actingAs($this->user)
            ->test(Branches::class)
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"')
            ->set('search', 'Jakarta')
            ->assertViewHas('branches', fn ($branches): bool => $branches->total() === 1
                && $branches->first()->is($jakarta))
            ->set('search', 'SBY')
            ->assertViewHas('branches', fn ($branches): bool => $branches->total() === 1
                && $branches->first()->is($surabaya));
    }

    public function test_create_modal_has_expected_defaults_bindings_and_saves_a_branch(): void
    {
        Livewire::actingAs($this->user)
            ->test(Branches::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('code', '')
            ->assertSet('number_code', null)
            ->assertSet('name', '')
            ->assertSet('active', true)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-labelledby="branch-editor-title"')
            ->assertSeeHtml('wire:submit="save"')
            ->assertSeeHtml('wire:model="code"')
            ->assertSeeHtml('wire:model="number_code"')
            ->assertSeeHtml('wire:model="name"')
            ->assertSeeHtml('wire:model="active"')
            ->set('code', 'SMG')
            ->set('number_code', 40)
            ->set('name', 'Semarang')
            ->set('active', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('branches', [
            'code' => 'SMG',
            'number_code' => 40,
            'name' => 'Semarang',
            'active' => false,
        ]);
    }

    public function test_edit_prefills_the_modal_and_saves_the_changes(): void
    {
        $branch = $this->createBranch('MKS', 50, 'Makassar', false);

        Livewire::actingAs($this->user)
            ->test(Branches::class)
            ->call('edit', $branch->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $branch->id)
            ->assertSet('code', 'MKS')
            ->assertSet('number_code', 50)
            ->assertSet('name', 'Makassar')
            ->assertSet('active', false)
            ->assertSee('Edit cabang')
            ->set('code', 'UPG')
            ->set('number_code', 51)
            ->set('name', 'Makassar Utama')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $branch->refresh();

        $this->assertSame('UPG', $branch->code);
        $this->assertSame(51, $branch->number_code);
        $this->assertSame('Makassar Utama', $branch->name);
        $this->assertTrue($branch->active);
    }

    public function test_toggle_active_changes_the_branch_status(): void
    {
        $branch = $this->createBranch('DPS', 60, 'Denpasar');

        $component = Livewire::actingAs($this->user)
            ->test(Branches::class)
            ->call('toggleActive', $branch->id)
            ->assertHasNoErrors();

        $this->assertFalse($branch->fresh()->active);

        $component->call('toggleActive', $branch->id)->assertHasNoErrors();

        $this->assertTrue($branch->fresh()->active);
    }

    private function createBranch(
        string $code,
        int $numberCode,
        string $name,
        bool $active = true,
    ): Branch {
        return Branch::create([
            'code' => $code,
            'number_code' => $numberCode,
            'name' => $name,
            'active' => $active,
        ]);
    }
}
