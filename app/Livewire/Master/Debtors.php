<?php

namespace App\Livewire\Master;

use App\Models\Debtor;
use Livewire\Component;
use Livewire\WithPagination;

class Debtors extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $identifier = '';
    public string $address = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'identifier' => 'nullable|string|max:100',
            'address' => 'nullable|string',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('menu.master-data');
        $this->reset(['editingId', 'name', 'identifier', 'address']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('menu.master-data');
        $debtor = Debtor::findOrFail($id);
        $this->editingId = $debtor->id;
        $this->name = $debtor->name;
        $this->identifier = $debtor->identifier ?? '';
        $this->address = $debtor->address ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('menu.master-data');
        $validated = $this->validate();

        if ($this->editingId) {
            Debtor::findOrFail($this->editingId)->update($validated);
            session()->flash('message', 'Data Debitur berhasil diperbarui.');
        } else {
            Debtor::create($validated);
            session()->flash('message', 'Debitur baru berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'identifier', 'address']);
    }

    public function delete(int $id): void
    {
        $this->authorize('menu.master-data');
        Debtor::findOrFail($id)->delete();
        session()->flash('message', 'Data Debitur berhasil dihapus.');
    }

    public function render()
    {
        $debtors = Debtor::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('identifier', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        return view('livewire.master.debtors', [
            'debtors' => $debtors,
        ])->layout('layouts.app');
    }
}
