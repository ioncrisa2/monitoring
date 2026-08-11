<?php

namespace App\Livewire\Master;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterRole = '';
    public ?int $filterBranchId = null;

    public bool $showModal = false;
    public ?int $editingId = null;

    public ?int $branch_id = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'admin';
    public string $phone = '';
    public bool $active = true;

    protected function rules(): array
    {
        return [
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $this->editingId,
            'password' => $this->editingId ? 'nullable|min:6' : 'required|min:6',
            'role' => 'required|in:sysadmin,supervisor,admin,reviewer,surveyor',
            'phone' => 'nullable|string|max:30',
            'active' => 'boolean',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('users.manage');
        $this->reset(['editingId', 'branch_id', 'name', 'email', 'password', 'role', 'phone', 'active']);
        $this->role = 'admin';
        $this->active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('users.manage');
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->branch_id = $user->branch_id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role;
        $this->phone = $user->phone ?? '';
        $this->active = $user->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('users.manage');
        $validated = $this->validate();

        if ($this->password) {
            $validated['password'] = Hash::make($this->password);
        } else {
            unset($validated['password']);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update($validated);
            $user->syncRoles([$validated['role']]);
            session()->flash('message', 'Data Pengguna berhasil diperbarui.');
        } else {
            $user = User::create($validated);
            $user->syncRoles([$validated['role']]);
            session()->flash('message', 'Pengguna baru berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'branch_id', 'name', 'email', 'password', 'role', 'phone', 'active']);
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('users.manage');
        $user = User::findOrFail($id);
        $user->update(['active' => !$user->active]);
        session()->flash('message', 'Status aktif pengguna berhasil diubah.');
    }

    public function render()
    {
        $users = User::query()
            ->with('branch')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterRole, function ($query) {
                $query->where('role', $this->filterRole);
            })
            ->when($this->filterBranchId, function ($query) {
                $query->where('branch_id', $this->filterBranchId);
            })
            ->latest()
            ->paginate(10);

        $branches = Branch::where('active', true)->get();

        return view('livewire.master.users', [
            'users' => $users,
            'branches' => $branches,
        ])->layout('layouts.app');
    }
}
