<?php

namespace Tests\Feature;

use App\Livewire\Master\RolesPermissions;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterRolesPermissionsTest extends TestCase
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

    public function test_page_requires_authentication_and_renders_for_an_authorized_user(): void
    {
        $this->get(route('master.roles-permissions'))
            ->assertRedirect(route('login'));

        $this->actingAs($this->user)
            ->get(route('master.roles-permissions'))
            ->assertOk()
            ->assertSee('Peran dan Hak Akses')
            ->assertSee('Daftar peran')
            ->assertSee('Hak akses');
    }

    public function test_initial_and_selected_role_state_matches_the_rendered_checkbox_bindings(): void
    {
        $initialRole = Role::query()->orderBy('id')->firstOrFail();
        $selectedRole = Role::findByName('surveyor');

        $component = Livewire::actingAs($this->user)
            ->test(RolesPermissions::class)
            ->assertSet('selectedRoleId', $initialRole->id)
            ->assertSet('selectedRoleName', $initialRole->name)
            ->assertSeeHtml('wire:model="selectedPermissions"')
            ->assertSeeHtml('value="menu.dashboard"')
            ->assertSeeHtml('value="work-orders.survey"');

        $this->assertEqualsCanonicalizing(
            $initialRole->permissions()->pluck('name')->all(),
            $component->get('selectedPermissions'),
        );

        $component
            ->call('selectRole', $selectedRole->id)
            ->assertHasNoErrors()
            ->assertSet('selectedRoleId', $selectedRole->id)
            ->assertSet('selectedRoleName', 'surveyor');

        $this->assertEqualsCanonicalizing(
            $selectedRole->permissions()->pluck('name')->all(),
            $component->get('selectedPermissions'),
        );
    }

    public function test_role_choices_and_create_dialog_entry_point_are_keyboard_semantic(): void
    {
        $role = Role::findByName('admin');
        $component = Livewire::actingAs($this->user)->test(RolesPermissions::class);
        $html = $component->html();

        $this->assertMatchesRegularExpression(
            '/<button\b(?=[^>]*\bwire:click="selectRole\('.$role->id.'\)")(?=[^>]*\btype="button")(?=[^>]*\baria-pressed="(?:true|false)")[^>]*>/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<button\b(?=[^>]*\bwire:click="\$set\(\'showCreateModal\', true\)")(?=[^>]*\btype="button")[^>]*>/',
            $html,
        );

        $component
            ->set('showCreateModal', true)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-labelledby="role-creator-title"');
    }

    public function test_save_permissions_synchronizes_the_exact_selected_permissions(): void
    {
        $role = Role::findByName('surveyor');
        $permissions = [
            'menu.dashboard',
            'menu.offers',
            'work-orders.review',
        ];

        Livewire::actingAs($this->user)
            ->test(RolesPermissions::class)
            ->call('selectRole', $role->id)
            ->set('selectedPermissions', $permissions)
            ->call('savePermissions')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            $permissions,
            $role->fresh()->permissions()->pluck('name')->all(),
        );
    }

    public function test_create_role_modal_has_expected_defaults_bindings_and_persists_and_selects_the_role(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(RolesPermissions::class)
            ->assertSet('showCreateModal', false)
            ->assertSet('newRoleName', '')
            ->set('showCreateModal', true)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-labelledby="role-creator-title"')
            ->assertSeeHtml('wire:submit="createRole"')
            ->assertSeeHtml('wire:model="newRoleName"')
            ->set('newRoleName', 'Quality Assurance')
            ->call('createRole')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false)
            ->assertSet('newRoleName', '')
            ->assertSet('selectedRoleName', 'quality_assurance')
            ->assertSet('selectedPermissions', []);

        $role = Role::findByName('quality_assurance');

        $this->assertSame('web', $role->guard_name);
        $this->assertSame($role->id, $component->get('selectedRoleId'));
        $this->assertCount(0, $role->permissions);
    }
}
