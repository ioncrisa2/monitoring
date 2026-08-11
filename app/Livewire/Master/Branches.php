<?php

namespace App\Livewire\Master;

use App\Models\Branch;
use Livewire\Component;
use Livewire\WithPagination;

class Branches extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public bool $active = true;

    protected function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:branches,code,' . $this->editingId,
            'name' => 'required|string|max:255',
            'active' => 'boolean',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('menu.master-data');
        $this->reset(['editingId', 'code', 'name', 'active']);
        $this->active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('menu.master-data');
        $branch = Branch::findOrFail($id);
        $this->editingId = $branch->id;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->active = $branch->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('menu.master-data');
        $validated = $this->validate();

        if ($this->editingId) {
            Branch::findOrFail($this->editingId)->update($validated);
            session()->flash('message', 'Data Cabang berhasil diperbarui.');
        } else {
            Branch::create($validated);
            session()->flash('message', 'Data Cabang baru berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'code', 'name', 'active']);
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('menu.master-data');
        $branch = Branch::findOrFail($id);
        $branch->update(['active' => !$branch->active]);
        session()->flash('message', 'Status aktif Cabang berhasil diubah.');
    }

    public function render()
    {
        $branches = Branch::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%');
            })
            ->withCount('users')
            ->latest()
            ->paginate(10);

        return view('livewire.master.branches', [
            'branches' => $branches,
        ])->layout('layouts.app');
    }
}
