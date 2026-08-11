<div>
    <div class="ui-page space-y-6">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Debitur</h1>
                <p class="ui-page-description">Kelola pihak atau objek yang dinilai beserta identitas dan alamat utamanya.</p>
            </div>

            <x-primary-button type="button" wire:click="create" class="w-full sm:w-auto">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5" />
                </svg>
                Tambah debitur
            </x-primary-button>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        <section aria-labelledby="debtor-list-heading">
            <div class="ui-toolbar mb-4">
                <div>
                    <h2 id="debtor-list-heading" class="ui-section-heading">Daftar debitur</h2>
                    <p class="ui-section-description">{{ $debtors->total() }} debitur ditemukan.</p>
                </div>

                <div class="relative w-full sm:w-80">
                    <x-input-label for="debtor-search" value="Cari debitur" class="sr-only" />
                    <x-text-input
                        id="debtor-search"
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="Cari nama atau kode debitur"
                        class="pl-10"
                    />
                    <svg class="pointer-events-none absolute left-3 top-3 size-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                    </svg>
                    <p wire:loading wire:target="search" class="ui-help" role="status">Memperbarui daftar…</p>
                </div>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <caption class="sr-only">Daftar debitur atau objek penilaian</caption>
                    <thead>
                        <tr>
                            <th scope="col">Kode debitur</th>
                            <th scope="col">Nama debitur</th>
                            <th scope="col">Alamat objek atau kantor</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($debtors as $debtor)
                            <tr wire:key="debtor-row-{{ $debtor->id }}">
                                <td><span class="font-mono text-sm text-ink-secondary">{{ $debtor->identifier ?: '-' }}</span></td>
                                <td class="font-medium text-ink">{{ $debtor->name }}</td>
                                <td>
                                    <p class="max-w-xl whitespace-normal text-sm leading-6 text-ink-secondary">{{ $debtor->address ?: '-' }}</p>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <button
                                            wire:click="edit({{ $debtor->id }})"
                                            type="button"
                                            class="ui-text-action"
                                            aria-label="Edit debitur {{ $debtor->name }}"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            wire:confirm="Apakah Anda yakin ingin menghapus data debitur ini?"
                                            wire:click="delete({{ $debtor->id }})"
                                            type="button"
                                            class="ui-text-action ui-text-action-danger"
                                            aria-label="Hapus debitur {{ $debtor->name }}"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="ui-empty-state">
                                    {{ $search ? 'Tidak ada debitur yang cocok dengan pencarian.' : 'Belum ada debitur. Tambahkan debitur pertama untuk memulai.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($debtors->hasPages())
                <div class="mt-4">{{ $debtors->links() }}</div>
            @endif
        </section>
    </div>

    @if($showModal)
        <x-modal name="debtor-editor" :show="$showModal" close-property="showModal" maxWidth="sm" labelledby="debtor-editor-title" focusable>
            <div class="ui-modal-header">
                <div>
                    <h2 id="debtor-editor-title" class="ui-modal-title">{{ $editingId ? 'Edit debitur' : 'Tambah debitur' }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">Simpan identitas yang digunakan pada penawaran dan pekerjaan.</p>
                </div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form debitur">&times;</button>
            </div>

            <form wire:submit="save">
                <div class="ui-modal-body space-y-4">
                    <div>
                        <x-input-label for="debtor-name" value="Nama debitur" />
                        <x-text-input
                            id="debtor-name"
                            wire:model="name"
                            type="text"
                            placeholder="Contoh: PT Surya Citra Kencana"
                            class="mt-1"
                            aria-describedby="debtor-name-error"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                        />
                        <x-input-error id="debtor-name-error" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="debtor-identifier" value="Kode debitur (opsional)" />
                        <x-text-input
                            id="debtor-identifier"
                            wire:model="identifier"
                            type="text"
                            placeholder="Contoh: DEB-2026-001"
                            class="mt-1 font-mono"
                            aria-describedby="debtor-identifier-error"
                            aria-invalid="{{ $errors->has('identifier') ? 'true' : 'false' }}"
                        />
                        <x-input-error id="debtor-identifier-error" :messages="$errors->get('identifier')" />
                    </div>

                    <div>
                        <x-input-label for="debtor-address" value="Alamat objek atau kantor" />
                        <x-textarea-input
                            id="debtor-address"
                            wire:model="address"
                            rows="4"
                            placeholder="Alamat lokasi debitur"
                            class="mt-1"
                            aria-describedby="debtor-address-error"
                            aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}"
                        ></x-textarea-input>
                        <x-input-error id="debtor-address-error" :messages="$errors->get('address')" />
                    </div>
                </div>

                <div class="ui-modal-footer">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')" class="w-full sm:w-auto">Batal</x-secondary-button>
                    <x-primary-button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="w-full sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="save">Simpan debitur</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
