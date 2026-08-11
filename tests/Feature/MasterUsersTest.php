<?php

namespace Tests\Feature;

use App\Livewire\Master\Users;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MasterUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create([
            'name' => 'System Operator',
            'email' => 'operator@example.test',
            'role' => 'sysadmin',
        ]);
        $this->user->syncRoles(['sysadmin']);
    }

    public function test_master_users_page_requires_authentication_and_renders_for_an_authorized_user(): void
    {
        $this->get(route('master.users'))
            ->assertRedirect(route('login'));

        $this->actingAs($this->user)
            ->get(route('master.users'))
            ->assertOk()
            ->assertSee('Daftar pengguna');
    }

    public function test_users_can_be_searched_by_name_or_email(): void
    {
        $namedUser = User::factory()->create([
            'name' => 'Ayu Prameswari',
            'email' => 'ayu@example.test',
            'role' => 'admin',
        ]);
        $emailedUser = User::factory()->create([
            'name' => 'Bagus Santoso',
            'email' => 'finance-team@example.test',
            'role' => 'reviewer',
        ]);
        User::factory()->create([
            'name' => 'Citra Lestari',
            'email' => 'citra@example.test',
            'role' => 'surveyor',
        ]);

        Livewire::actingAs($this->user)
            ->test(Users::class)
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"')
            ->set('search', 'Prameswari')
            ->assertViewHas('users', fn ($users): bool => $users->total() === 1
                && $users->first()->is($namedUser))
            ->set('search', 'finance-team')
            ->assertViewHas('users', fn ($users): bool => $users->total() === 1
                && $users->first()->is($emailedUser));
    }

    public function test_users_can_be_filtered_by_role(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        User::factory()->create(['role' => 'surveyor']);

        Livewire::actingAs($this->user)
            ->test(Users::class)
            ->set('filterRole', 'reviewer')
            ->assertViewHas('users', fn ($users): bool => $users->total() === 1
                && $users->first()->is($reviewer));
    }

    public function test_users_can_be_filtered_by_branch(): void
    {
        $jakarta = $this->createBranch('JKT', 10, 'Jakarta');
        $surabaya = $this->createBranch('SBY', 20, 'Surabaya');
        $jakartaUser = User::factory()->create([
            'branch_id' => $jakarta->id,
            'role' => 'admin',
        ]);
        User::factory()->create([
            'branch_id' => $surabaya->id,
            'role' => 'admin',
        ]);

        Livewire::actingAs($this->user)
            ->test(Users::class)
            ->set('filterBranchId', $jakarta->id)
            ->assertViewHas('users', fn ($users): bool => $users->total() === 1
                && $users->first()->is($jakartaUser));
    }

    public function test_only_active_branches_are_available_in_the_user_form(): void
    {
        $activeBranch = $this->createBranch('SMG', 30, 'Semarang');
        $inactiveBranch = $this->createBranch('MKS', 40, 'Makassar', false);

        Livewire::actingAs($this->user)
            ->test(Users::class)
            ->call('create')
            ->assertViewHas('branches', fn ($branches): bool => $branches->count() === 1
                && $branches->first()->is($activeBranch))
            ->assertSeeHtml('id="user-branch"')
            ->assertSee($activeBranch->name)
            ->assertDontSee($inactiveBranch->name);
    }

    public function test_create_modal_has_expected_semantics_defaults_bindings_and_saves_a_user_with_its_role(): void
    {
        $branch = $this->createBranch('DPS', 50, 'Denpasar');

        Livewire::actingAs($this->user)
            ->test(Users::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('branch_id', null)
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('password', '')
            ->assertSet('role', 'admin')
            ->assertSet('phone', '')
            ->assertSet('active', true)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-labelledby="user-editor-title"')
            ->assertSeeHtml('wire:submit="save"')
            ->assertSeeHtml('wire:model="branch_id"')
            ->assertSeeHtml('wire:model="name"')
            ->assertSeeHtml('wire:model="email"')
            ->assertSeeHtml('wire:model="password"')
            ->assertSeeHtml('wire:model="role"')
            ->assertSeeHtml('wire:model="phone"')
            ->assertSeeHtml('wire:model="active"')
            ->set('branch_id', $branch->id)
            ->set('name', 'Dewi Anggraini')
            ->set('email', 'dewi@example.test')
            ->set('password', 'rahasia-baru')
            ->set('role', 'reviewer')
            ->set('phone', '081234567890')
            ->set('active', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $createdUser = User::where('email', 'dewi@example.test')->firstOrFail();

        $this->assertSame($branch->id, $createdUser->branch_id);
        $this->assertSame('Dewi Anggraini', $createdUser->name);
        $this->assertSame('reviewer', $createdUser->role);
        $this->assertSame('081234567890', $createdUser->phone);
        $this->assertFalse($createdUser->active);
        $this->assertTrue(Hash::check('rahasia-baru', $createdUser->password));
        $this->assertSame(['reviewer'], $createdUser->getRoleNames()->all());
    }

    public function test_edit_prefills_and_saves_changes_while_a_blank_password_is_preserved(): void
    {
        $originalBranch = $this->createBranch('BDG', 60, 'Bandung');
        $newBranch = $this->createBranch('PLB', 70, 'Palembang');
        $editedUser = User::factory()->create([
            'branch_id' => $originalBranch->id,
            'name' => 'Rina Wulandari',
            'email' => 'rina@example.test',
            'password' => Hash::make('password-lama'),
            'role' => 'admin',
            'phone' => '081111111111',
            'active' => false,
        ]);
        $editedUser->syncRoles(['admin']);
        $originalPassword = $editedUser->password;

        Livewire::actingAs($this->user)
            ->test(Users::class)
            ->call('edit', $editedUser->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $editedUser->id)
            ->assertSet('branch_id', $originalBranch->id)
            ->assertSet('name', 'Rina Wulandari')
            ->assertSet('email', 'rina@example.test')
            ->assertSet('password', '')
            ->assertSet('role', 'admin')
            ->assertSet('phone', '081111111111')
            ->assertSet('active', false)
            ->assertSee('Edit pengguna')
            ->set('branch_id', $newBranch->id)
            ->set('name', 'Rina Wulandari Utama')
            ->set('email', 'rina.utama@example.test')
            ->set('password', '')
            ->set('role', 'supervisor')
            ->set('phone', '082222222222')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $editedUser->refresh();

        $this->assertSame($newBranch->id, $editedUser->branch_id);
        $this->assertSame('Rina Wulandari Utama', $editedUser->name);
        $this->assertSame('rina.utama@example.test', $editedUser->email);
        $this->assertSame($originalPassword, $editedUser->password);
        $this->assertSame('supervisor', $editedUser->role);
        $this->assertSame('082222222222', $editedUser->phone);
        $this->assertTrue($editedUser->active);
        $this->assertSame(['supervisor'], $editedUser->getRoleNames()->all());
    }

    public function test_toggle_active_changes_the_user_status(): void
    {
        $managedUser = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(Users::class)
            ->call('toggleActive', $managedUser->id)
            ->assertHasNoErrors();

        $this->assertFalse($managedUser->fresh()->active);

        $component->call('toggleActive', $managedUser->id)->assertHasNoErrors();

        $this->assertTrue($managedUser->fresh()->active);
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
