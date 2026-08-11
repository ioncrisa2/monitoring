<?php

namespace App\Livewire\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLogIndex extends Component
{
    use WithPagination;

    public ?int $selectedUserId = null;
    public string $selectedAction = '';
    public string $search = '';

    public function triggerBackup(): void
    {
        Artisan::call('db:backup-sqlite');
        session()->flash('message', 'Backup database SQLite berhasil dibuat secara instan di folder storage/app/backups.');
    }

    public function render()
    {
        $query = ActivityLog::with('user');

        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }

        if ($this->selectedAction) {
            $query->where('action', $this->selectedAction);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('description', 'like', "%{$this->search}%")
                  ->orWhere('ip_address', 'like', "%{$this->search}%");
            });
        }

        $logs = $query->latest()->paginate(20);
        $users = User::where('active', true)->get();

        return view('livewire.audit.activity-log-index', [
            'logs' => $logs,
            'users' => $users,
        ])->layout('layouts.app');
    }
}
