<?php

namespace App\Livewire\Master;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissions extends Component
{
    public ?int $selectedRoleId = null;

    public string $selectedRoleName = '';

    public array $selectedPermissions = [];

    // Create new role state
    public bool $showCreateModal = false;

    public string $newRoleName = '';

    public function mount(): void
    {
        $this->authorize('users.manage');
        $firstRole = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->first();

        if ($firstRole) {
            $this->selectRole($firstRole->id);
        }
    }

    public function selectRole(int $roleId): void
    {
        $this->authorize('users.manage');
        $role = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->findOrFail($roleId);

        $this->resetErrorBag();
        $this->selectedRoleId = $role->id;
        $this->selectedRoleName = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
    }

    public function savePermissions(): void
    {
        $this->authorize('users.manage');
        $validated = $this->validate([
            'selectedRoleId' => [
                'required',
                'integer',
                Rule::exists(Role::class, 'id')->where('guard_name', 'web'),
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => [
                'string',
                'distinct',
                Rule::exists(Permission::class, 'name')->where('guard_name', 'web'),
            ],
        ]);

        $role = Role::query()
            ->where('guard_name', 'web')
            ->findOrFail($validated['selectedRoleId']);
        $protectionMessage = $this->roleProtectionMessage($role);

        if ($protectionMessage !== null) {
            $this->addError('selectedRoleId', $protectionMessage);

            return;
        }

        $role->syncPermissions($validated['selectedPermissions']);

        // Clear Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        session()->flash('message', "Hak akses (permissions) untuk role '{$role->name}' berhasil diperbarui.");
    }

    public function createRole(): void
    {
        $this->authorize('users.manage');
        $this->validate([
            'newRoleName' => 'required|string|max:50|unique:roles,name',
        ]);

        $roleName = strtolower(trim(str_replace(' ', '_', $this->newRoleName)));

        $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);

        $this->showCreateModal = false;
        $this->newRoleName = '';
        $this->selectRole($role->id);

        session()->flash('message', "Role baru '{$role->name}' berhasil dibuat.");
    }

    public function render()
    {
        $this->authorize('users.manage');

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount('users')
            ->with('permissions')
            ->get();
        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->get();
        $selectedRole = $this->selectedRoleId
            ? $roles->firstWhere('id', $this->selectedRoleId)
            : null;
        $selectedRoleProtectionMessage = $selectedRole
            ? $this->roleProtectionMessage($selectedRole)
            : null;

        // Categorized permissions
        $groupedPermissions = [
            'Akses Menu Navigasi' => $allPermissions->filter(fn ($p) => str_starts_with($p->name, 'menu.')),
            'Dokumen Penawaran' => $allPermissions->filter(fn ($p) => str_starts_with($p->name, 'offers.')),
            'Manajemen Pengguna' => $allPermissions->filter(fn ($p) => str_starts_with($p->name, 'users.')),
            'Workflow Pekerjaan / Work Order' => $allPermissions->filter(fn ($p) => str_starts_with($p->name, 'work-orders.')),
        ];

        return view('livewire.master.roles-permissions', [
            'roles' => $roles,
            'groupedPermissions' => $groupedPermissions,
            'selectedRoleProtected' => $selectedRoleProtectionMessage !== null,
            'selectedRoleProtectionMessage' => $selectedRoleProtectionMessage,
        ])->layout('layouts.app');
    }

    private function roleProtectionMessage(Role $role): ?string
    {
        if ($role->name === 'sysadmin') {
            return 'Hak akses role sysadmin dilindungi dan tidak dapat diubah.';
        }

        $actor = auth()->user();

        if ($actor !== null && $actor->roles()->whereKey($role->getKey())->exists()) {
            return 'Anda tidak dapat mengubah hak akses role yang sedang digunakan oleh akun sendiri.';
        }

        return null;
    }
}
