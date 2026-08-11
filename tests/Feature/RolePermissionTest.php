<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sysadmin_can_access_master_users_page(): void
    {
        $sysadmin = User::factory()->create(['role' => 'sysadmin']);

        $response = $this->actingAs($sysadmin)->get('/master/users');

        $response->assertOk();
    }

    public function test_surveyor_cannot_access_master_users_page_returns_403(): void
    {
        $surveyor = User::factory()->create(['role' => 'surveyor']);

        $response = $this->actingAs($surveyor)->get('/master/users');

        $response->assertStatus(403);
    }

    public function test_surveyor_cannot_access_audit_logs_returns_403(): void
    {
        $surveyor = User::factory()->create(['role' => 'surveyor']);

        $response = $this->actingAs($surveyor)->get('/audit-logs');

        $response->assertStatus(403);
    }

    public function test_supervisor_can_access_offers_and_reports(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor)->get('/offers')->assertOk();
        $this->actingAs($supervisor)->get('/reports/production')->assertOk();
    }

    public function test_surveyor_cannot_execute_user_management_livewire_action(): void
    {
        $surveyor = User::factory()->create(['role' => 'surveyor']);
        $this->actingAs($surveyor);

        Livewire::test(\App\Livewire\Master\Users::class)
            ->call('create')
            ->assertStatus(403);
    }

    public function test_sysadmin_can_access_roles_permissions_management_page(): void
    {
        $sysadmin = User::factory()->create(['role' => 'sysadmin']);

        $response = $this->actingAs($sysadmin)->get('/master/roles-permissions');

        $response->assertOk();
    }

    public function test_sysadmin_can_update_role_permissions_via_livewire(): void
    {
        $sysadmin = User::factory()->create(['role' => 'sysadmin']);
        $this->actingAs($sysadmin);

        $surveyorRole = \Spatie\Permission\Models\Role::findByName('surveyor');

        Livewire::test(\App\Livewire\Master\RolesPermissions::class)
            ->call('selectRole', $surveyorRole->id)
            ->set('selectedPermissions', ['menu.dashboard', 'menu.work-orders', 'menu.offers'])
            ->call('savePermissions')
            ->assertHasNoErrors();

        $this->assertTrue($surveyorRole->fresh()->hasPermissionTo('menu.offers'));
    }
}
