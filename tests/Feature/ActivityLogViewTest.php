<?php

namespace Tests\Feature;

use App\Livewire\Audit\ActivityLogIndex;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogViewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create([
            'name' => 'System Auditor',
            'role' => 'sysadmin',
            'active' => true,
        ]);
        $this->user->syncRoles(['sysadmin']);
    }

    public function test_authorized_user_can_render_the_audit_route_with_expected_livewire_bindings(): void
    {
        $this->actingAs($this->user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Jejak Audit')
            ->assertSee('Filter aktivitas')
            ->assertSee('Aktivitas tercatat')
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"')
            ->assertSeeHtml('wire:model.live="selectedUserId"')
            ->assertSeeHtml('wire:model.live="selectedAction"')
            ->assertSeeHtml('wire:click="triggerBackup"');
    }

    public function test_logs_can_be_searched_by_description_or_ip_address(): void
    {
        $descriptionLog = $this->createLog([
            'description' => 'Mengubah tenggat SLA kontrak Alfa',
            'ip_address' => '10.10.1.1',
        ]);
        $ipLog = $this->createLog([
            'description' => 'Pengguna membuka laporan produksi',
            'ip_address' => '172.16.44.9',
        ]);
        $this->createLog([
            'description' => 'Aktivitas lain yang tidak berkaitan',
            'ip_address' => '192.0.2.1',
        ]);

        Livewire::actingAs($this->user)
            ->test(ActivityLogIndex::class)
            ->set('search', 'SLA kontrak Alfa')
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1
                && $logs->first()->is($descriptionLog))
            ->set('search', '172.16.44.9')
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1
                && $logs->first()->is($ipLog));
    }

    public function test_logs_can_be_filtered_by_user(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'Ayu Auditor',
            'role' => 'reviewer',
            'active' => true,
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Bagus Operator',
            'role' => 'admin',
            'active' => true,
        ]);
        $targetLog = $this->createLog([
            'user_id' => $targetUser->id,
            'description' => 'Aktivitas milik Ayu',
        ]);
        $this->createLog([
            'user_id' => $otherUser->id,
            'description' => 'Aktivitas milik Bagus',
        ]);

        Livewire::actingAs($this->user)
            ->test(ActivityLogIndex::class)
            ->set('selectedUserId', $targetUser->id)
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1
                && $logs->first()->is($targetLog));
    }

    public function test_logs_can_be_filtered_by_action(): void
    {
        $updateLog = $this->createLog([
            'action' => 'UPDATE',
            'description' => 'Data pekerjaan diperbarui',
        ]);
        $this->createLog([
            'action' => 'CREATE',
            'description' => 'Data pekerjaan dibuat',
        ]);

        Livewire::actingAs($this->user)
            ->test(ActivityLogIndex::class)
            ->set('selectedAction', 'UPDATE')
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1
                && $logs->first()->is($updateLog));
    }

    public function test_only_active_users_are_available_in_the_user_selector(): void
    {
        $activeUser = User::factory()->create([
            'name' => 'Rina Aktif',
            'role' => 'admin',
            'active' => true,
        ]);
        $inactiveUser = User::factory()->create([
            'name' => 'Doni Nonaktif',
            'role' => 'admin',
            'active' => false,
        ]);

        Livewire::actingAs($this->user)
            ->test(ActivityLogIndex::class)
            ->assertViewHas('users', fn ($users): bool => $users->contains(fn (User $user): bool => $user->is($activeUser))
                && $users->contains(fn (User $user): bool => $user->is($this->user))
                && ! $users->contains(fn (User $user): bool => $user->is($inactiveUser))
                && $users->every(fn (User $user): bool => $user->active))
            ->assertSee('Rina Aktif')
            ->assertDontSee('Doni Nonaktif');
    }

    public function test_the_table_has_semantic_markup_and_renders_an_em_dash_for_a_missing_ip_address(): void
    {
        $log = $this->createLog([
            'user_id' => null,
            'action' => 'BACKUP',
            'description' => 'Cadangan terjadwal selesai',
            'ip_address' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(ActivityLogIndex::class)
            ->assertSeeHtml('<table')
            ->assertSeeHtml('<caption')
            ->assertSeeHtml('<th scope="col">Waktu</th>')
            ->assertSeeHtml('<th scope="col">Pengguna</th>')
            ->assertSeeHtml('<th scope="col">Tindakan</th>')
            ->assertSeeHtml('<th scope="col">Deskripsi</th>')
            ->assertSeeHtml('<th scope="col">Alamat IP</th>')
            ->assertSeeHtml('<time datetime="'.$log->created_at->toIso8601String().'"')
            ->assertSeeHtml('wire:key="audit-log-row-'.$log->id.'"')
            ->assertSee('Sistem')
            ->assertSee('—');
    }

    public function test_empty_states_distinguish_an_empty_log_from_an_empty_filter_result(): void
    {
        Livewire::actingAs($this->user)
            ->test(ActivityLogIndex::class)
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 0)
            ->assertSee('Belum ada aktivitas yang tercatat.')
            ->set('search', 'aktivitas-yang-tidak-ada')
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 0)
            ->assertSee('Tidak ada aktivitas yang cocok dengan filter.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLog(array $attributes = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'description' => 'Aktivitas audit tercatat',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'ActivityLogViewTest',
        ], $attributes));
    }
}
