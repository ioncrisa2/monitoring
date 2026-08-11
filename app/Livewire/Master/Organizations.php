<?php

namespace App\Livewire\Master;

use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;

class Organizations extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterType = '';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $type = 'pemberi_tugas';
    public string $address = '';
    public string $tax_id = '';
    public string $phone = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:pemberi_tugas,pengguna_laporan,klien,lainnya',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('menu.master-data');
        $this->reset(['editingId', 'name', 'type', 'address', 'tax_id', 'phone']);
        $this->type = 'pemberi_tugas';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('menu.master-data');
        $org = Organization::findOrFail($id);
        $this->editingId = $org->id;
        $this->name = $org->name;
        $this->type = $org->type;
        $this->address = $org->address ?? '';
        $this->tax_id = $org->tax_id ?? '';
        $this->phone = $org->phone ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('menu.master-data');
        $validated = $this->validate();

        if ($this->editingId) {
            Organization::findOrFail($this->editingId)->update($validated);
            session()->flash('message', 'Data Organisasi berhasil diperbarui.');
        } else {
            Organization::create($validated);
            session()->flash('message', 'Organisasi baru berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'type', 'address', 'tax_id', 'phone']);
    }

    public function delete(int $id): void
    {
        $this->authorize('menu.master-data');
        Organization::findOrFail($id)->delete();
        session()->flash('message', 'Organisasi berhasil dihapus.');
    }

    public function render()
    {
        $organizations = Organization::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('tax_id', 'like', '%' . $this->search . '%');
            })
            ->when($this->filterType, function ($query) {
                $query->where('type', $this->filterType);
            })
            ->latest()
            ->paginate(10);

        return view('livewire.master.organizations', [
            'organizations' => $organizations,
        ])->layout('layouts.app');
    }
}
