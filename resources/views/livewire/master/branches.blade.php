<div>
    <div class="ui-page space-y-6">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Cabang</h1>
                <p class="ui-page-description">Kelola kantor pusat dan cabang operasional beserta kode penomoran penawarannya.</p>
            </div>

            <x-primary-button type="button" wire:click="create" class="w-full sm:w-auto">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5" />
                </svg>
                Tambah cabang
            </x-primary-button>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        <section aria-labelledby="branch-list-heading">
            <div class="ui-toolbar mb-4">
                <div>
                    <h2 id="branch-list-heading" class="ui-section-heading">Daftar cabang</h2>
                    <p class="ui-section-description">{{ $branches->total() }} unit kantor ditemukan.</p>
                </div>

                <div class="relative w-full sm:w-72">
                    <x-input-label for="branch-search" value="Cari cabang" class="sr-only" />
                    <x-text-input
                        id="branch-search"
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="Cari kode atau nama cabang"
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
                    <caption class="sr-only">Daftar kantor pusat dan cabang operasional</caption>
                    <thead>
                        <tr>
                            <th scope="col">Kode</th>
                            <th scope="col">Kode angka</th>
                            <th scope="col">Nama cabang</th>
                            <th scope="col">Pengguna</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches as $branch)
                            <tr wire:key="branch-row-{{ $branch->id }}">
                                <td><span class="font-mono text-sm font-semibold text-ink">{{ $branch->code }}</span></td>
                                <td>
                                    @if(is_null($branch->number_code))
                                        <span class="text-sm font-medium text-rose-700 dark:text-rose-400">Belum diatur</span>
                                    @else
                                        <span class="font-mono text-sm text-ink-secondary">{{ $branch->number_code }}</span>
                                    @endif
                                </td>
                                <td class="font-medium text-ink">{{ $branch->name }}</td>
                                <td><span class="tabular-nums">{{ $branch->users_count }}</span> pengguna</td>
                                <td><x-active-status-badge :active="$branch->active" /></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <button
                                            wire:click="toggleActive({{ $branch->id }})"
                                            type="button"
                                            class="ui-text-action"
                                            aria-label="{{ $branch->active ? 'Nonaktifkan' : 'Aktifkan' }} cabang {{ $branch->name }}"
                                        >
                                            {{ $branch->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                        <button
                                            wire:click="edit({{ $branch->id }})"
                                            type="button"
                                            class="ui-text-action"
                                            aria-label="Edit cabang {{ $branch->name }}"
                                        >
                                            Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="ui-empty-state">
                                    {{ $search ? 'Tidak ada cabang yang cocok dengan pencarian.' : 'Belum ada cabang. Tambahkan unit kantor pertama untuk memulai.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($branches->hasPages())
                <div class="mt-4">{{ $branches->links() }}</div>
            @endif
        </section>
    </div>

    @if($showModal)
        <x-modal name="branch-editor" :show="$showModal" close-property="showModal" maxWidth="sm" labelledby="branch-editor-title" focusable>
            <div class="ui-modal-header">
                <div>
                    <h2 id="branch-editor-title" class="ui-modal-title">{{ $editingId ? 'Edit cabang' : 'Tambah cabang' }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">Kode angka digunakan untuk menyusun nomor penawaran dan kontrak.</p>
                </div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form cabang">&times;</button>
            </div>

            <form wire:submit="save">
                <div class="ui-modal-body space-y-4">
                    <div>
                        <x-input-label for="branch-code" value="Kode cabang" />
                        <x-text-input
                            id="branch-code"
                            wire:model="code"
                            type="text"
                            placeholder="Contoh: PST, JKT, atau SBY"
                            class="mt-1 font-mono"
                            aria-describedby="branch-code-error"
                            aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}"
                        />
                        <x-input-error id="branch-code-error" :messages="$errors->get('code')" />
                    </div>

                    <div>
                        <x-input-label for="branch-number-code" value="Kode angka" />
                        <x-text-input
                            id="branch-number-code"
                            wire:model="number_code"
                            type="number"
                            min="0"
                            placeholder="Contoh: 0 atau 10"
                            class="mt-1"
                            aria-describedby="branch-number-help branch-number-error"
                            aria-invalid="{{ $errors->has('number_code') ? 'true' : 'false' }}"
                        />
                        <p id="branch-number-help" class="ui-help">Gunakan 0 untuk kantor pusat. Nilai ini muncul sebagai segmen cabang pada nomor dokumen.</p>
                        <x-input-error id="branch-number-error" :messages="$errors->get('number_code')" />
                    </div>

                    <div>
                        <x-input-label for="branch-name" value="Nama cabang" />
                        <x-text-input
                            id="branch-name"
                            wire:model="name"
                            type="text"
                            placeholder="Nama lengkap cabang"
                            class="mt-1"
                            aria-describedby="branch-name-error"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                        />
                        <x-input-error id="branch-name-error" :messages="$errors->get('name')" />
                    </div>

                    <label for="branch-active" class="flex min-h-10 items-center gap-3 text-sm text-ink-secondary">
                        <input id="branch-active" wire:model="active" type="checkbox" class="size-4 rounded border-line-strong text-brand focus:ring-brand">
                        <span>
                            <span class="block font-medium text-ink">Cabang aktif</span>
                            <span class="mt-0.5 block text-xs text-ink-muted">Cabang aktif tersedia pada pilihan penawaran dan laporan.</span>
                        </span>
                    </label>
                </div>

                <div class="ui-modal-footer">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')" class="w-full sm:w-auto">Batal</x-secondary-button>
                    <x-primary-button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="w-full sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="save">Simpan cabang</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
