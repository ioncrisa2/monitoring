<?php

namespace App\Livewire\Master;

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
        $this->authorize('menu.master-users');
        $firstRole = Role::first();
        if ($firstRole) {
            $this->selectRole($firstRole->id);
        }
    }

    public function selectRole(int $roleId): void
    {
        $this->authorize('menu.master-users');
        $role = Role::with('permissions')->findOrFail($roleId);
        $this->selectedRoleId = $role->id;
        $this->selectedRoleName = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
    }

    public function savePermissions(): void
    {
        $this->authorize('menu.master-users');

        if (!$this->selectedRoleId) {
            return;
        }

        $role = Role::findOrFail($this->selectedRoleId);
        $role->syncPermissions($this->selectedPermissions);

        // Clear Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        session()->flash('message', "Hak akses (permissions) untuk role '{$role->name}' berhasil diperbarui.");
    }

    public function createRole(): void
    {
        $this->authorize('menu.master-users');
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
        $roles = Role::withCount('users')->with('permissions')->get();
        $allPermissions = Permission::all();

        // Categorized permissions
        $groupedPermissions = [
            'Akses Menu Navigasi' => $allPermissions->filter(fn($p) => str_starts_with($p->name, 'menu.')),
            'Manajemen Pengguna' => $allPermissions->filter(fn($p) => str_starts_with($p->name, 'users.')),
            'Workflow Pekerjaan / Work Order' => $allPermissions->filter(fn($p) => str_starts_with($p->name, 'work-orders.')),
        ];

        return view('livewire.master.roles-permissions', [
            'roles' => $roles,
            'groupedPermissions' => $groupedPermissions,
        ])->layout('layouts.app');
    }
}
