<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthRedesignViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_welcome_page_shows_the_expected_guest_and_authorized_account_calls_to_action(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Satu tempat untuk memantau pekerjaan penilaian.')
            ->assertSeeHtml('href="'.route('login').'"')
            ->assertSee('Masuk ke sistem')
            ->assertSeeHtml('href="'.route('register').'"')
            ->assertSee('Daftar akun');

        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSeeHtml('href="'.route('dashboard').'"')
            ->assertSee('Buka dashboard')
            ->assertDontSee('Masuk ke sistem');
    }

    public function test_login_route_renders_heading_bindings_action_and_loading_hook_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.login')
            ->assertSee('Masuk ke Sistem')
            ->assertSeeHtml('wire:submit="login"')
            ->assertSeeHtml('wire:model="form.email"')
            ->assertSeeHtml('wire:model="form.password"')
            ->assertSeeHtml('wire:model="form.remember"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:target="login"');
    }

    public function test_register_route_renders_heading_bindings_action_and_loading_hook_for_guests(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.register')
            ->assertSee('Buat Akun')
            ->assertSeeHtml('wire:submit="register"')
            ->assertSeeHtml('wire:model="name"')
            ->assertSeeHtml('wire:model="email"')
            ->assertSeeHtml('wire:model="password"')
            ->assertSeeHtml('wire:model="password_confirmation"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:target="register"');
    }

    public function test_forgot_password_route_renders_heading_binding_action_and_loading_hook_for_guests(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.forgot-password')
            ->assertSee('Lupa Password')
            ->assertSeeHtml('wire:submit="sendPasswordResetLink"')
            ->assertSeeHtml('wire:model="email"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:target="sendPasswordResetLink"');
    }

    public function test_authenticated_confirmation_and_verification_routes_render_their_actions_and_loading_hooks(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('password.confirm'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.confirm-password')
            ->assertSee('Konfirmasi Password')
            ->assertSeeHtml('wire:submit="confirmPassword"')
            ->assertSeeHtml('wire:model="password"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:target="confirmPassword"');

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.verify-email')
            ->assertSee('Verifikasi Email')
            ->assertSeeHtml('wire:click="sendVerification"')
            ->assertSeeHtml('wire:target="sendVerification"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:click="logout"');
    }

    public function test_profile_route_renders_all_three_volt_components_and_their_ui_contracts(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->get(route('profile'))
            ->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Profil')
            ->assertSeeVolt('profile.update-profile-information-form')
            ->assertSeeVolt('profile.update-password-form')
            ->assertSeeVolt('profile.delete-user-form')
            ->assertSeeHtml('wire:submit="updateProfileInformation"')
            ->assertSeeHtml('wire:model="name"')
            ->assertSeeHtml('wire:model="email"')
            ->assertSeeHtml('wire:target="updateProfileInformation"')
            ->assertSeeHtml('wire:submit="updatePassword"')
            ->assertSeeHtml('wire:model="current_password"')
            ->assertSeeHtml('wire:model="password_confirmation"')
            ->assertSeeHtml('wire:target="updatePassword"')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-labelledby="delete-account-modal-title"')
            ->assertSeeHtml('wire:submit="deleteUser"')
            ->assertSeeHtml('wire:target="deleteUser"')
            ->assertSeeHtml('wire:loading.attr="disabled"');
    }

    public function test_forbidden_page_gives_an_authenticated_user_without_dashboard_permission_a_safe_profile_link(): void
    {
        Role::create(['name' => 'profile-only', 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => 'profile-only']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSee('Akses tidak tersedia')
            ->assertSeeHtml('href="'.route('profile').'"')
            ->assertSee('Buka profil')
            ->assertDontSee('Kembali ke dashboard');
    }
}
