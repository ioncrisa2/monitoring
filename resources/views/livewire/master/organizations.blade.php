<div>
    @php
        $organizationTypeLabels = [
            'pemberi_tugas' => 'Pemberi tugas',
            'pengguna_laporan' => 'Pengguna laporan',
            'klien' => 'Klien langsung',
            'lainnya' => 'Lainnya',
        ];
    @endphp

    <div class="ui-page space-y-6">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Klien dan Organisasi</h1>
                <p class="ui-page-description">Kelola pemberi tugas, pengguna laporan, klien langsung, dan organisasi terkait lainnya.</p>
            </div>

            <x-primary-button type="button" wire:click="create" class="w-full sm:w-auto">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5" />
                </svg>
                Tambah organisasi
            </x-primary-button>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        <section aria-labelledby="organization-list-heading">
            <div class="ui-toolbar mb-4">
                <div>
                    <h2 id="organization-list-heading" class="ui-section-heading">Daftar organisasi</h2>
                    <p class="ui-section-description">{{ $organizations->total() }} organisasi ditemukan.</p>
                </div>

                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-[minmax(0,18rem)_minmax(0,12rem)] md:w-auto">
                    <div class="relative">
                        <x-input-label for="organization-search" value="Cari organisasi" class="sr-only" />
                        <x-text-input
                            id="organization-search"
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="Cari nama atau NPWP"
                            class="pl-10"
                        />
                        <svg class="pointer-events-none absolute left-3 top-3 size-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                        </svg>
                    </div>

                    <div>
                        <x-input-label for="organization-type-filter" value="Filter jenis organisasi" class="sr-only" />
                        <x-select-input id="organization-type-filter" wire:model.live="filterType" aria-label="Filter jenis organisasi">
                            <option value="">Semua jenis</option>
                            <option value="pemberi_tugas">Pemberi tugas</option>
                            <option value="pengguna_laporan">Pengguna laporan</option>
                            <option value="klien">Klien langsung</option>
                            <option value="lainnya">Lainnya</option>
                        </x-select-input>
                    </div>

                    <p wire:loading wire:target="search,filterType" class="ui-help sm:col-span-2" role="status">Memperbarui daftar…</p>
                </div>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <caption class="sr-only">Daftar klien dan organisasi terkait</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nama organisasi</th>
                            <th scope="col">Kategori</th>
                            <th scope="col">NPWP</th>
                            <th scope="col">Telepon</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($organizations as $organization)
                            <tr wire:key="organization-row-{{ $organization->id }}">
                                <td>
                                    <div class="font-medium text-ink">{{ $organization->name }}</div>
                                    @if($organization->address)
                                        <div class="mt-1 max-w-md whitespace-normal text-xs leading-5 text-ink-muted">{{ $organization->address }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="ui-badge ui-badge-neutral">{{ $organizationTypeLabels[$organization->type] ?? $organization->type }}</span>
                                </td>
                                <td><span class="font-mono text-sm text-ink-secondary">{{ $organization->tax_id ?: '-' }}</span></td>
                                <td><span class="tabular-nums">{{ $organization->phone ?: '-' }}</span></td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <button
                                            wire:click="edit({{ $organization->id }})"
                                            type="button"
                                            class="ui-text-action"
                                            aria-label="Edit organisasi {{ $organization->name }}"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            wire:confirm="Apakah Anda yakin ingin menghapus organisasi ini?"
                                            wire:click="delete({{ $organization->id }})"
                                            type="button"
                                            class="ui-text-action ui-text-action-danger"
                                            aria-label="Hapus organisasi {{ $organization->name }}"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="ui-empty-state">
                                    {{ $search || $filterType ? 'Tidak ada organisasi yang cocok dengan pencarian atau filter.' : 'Belum ada organisasi. Tambahkan organisasi pertama untuk memulai.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($organizations->hasPages())
                <div class="mt-4">{{ $organizations->links() }}</div>
            @endif
        </section>
    </div>

    @if($showModal)
        <x-modal name="organization-editor" :show="$showModal" close-property="showModal" maxWidth="md" labelledby="organization-editor-title" focusable>
            <div class="ui-modal-header">
                <div>
                    <h2 id="organization-editor-title" class="ui-modal-title">{{ $editingId ? 'Edit organisasi' : 'Tambah organisasi' }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">Data ini tersedia pada pilihan klien dan pengguna laporan.</p>
                </div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form organisasi">&times;</button>
            </div>

            <form wire:submit="save">
                <div class="ui-modal-body space-y-4">
                    <div>
                        <x-input-label for="organization-name" value="Nama organisasi" />
                        <x-text-input
                            id="organization-name"
                            wire:model="name"
                            type="text"
                            placeholder="Contoh: PT Bank Nusantara"
                            class="mt-1"
                            aria-describedby="organization-name-error"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                        />
                        <x-input-error id="organization-name-error" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="organization-type" value="Kategori organisasi" />
                        <x-select-input
                            id="organization-type"
                            wire:model="type"
                            class="mt-1"
                            aria-describedby="organization-type-error"
                            aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}"
                        >
                            <option value="pemberi_tugas">Pemberi tugas</option>
                            <option value="pengguna_laporan">Pengguna laporan</option>
                            <option value="klien">Klien langsung</option>
                            <option value="lainnya">Lainnya</option>
                        </x-select-input>
                        <x-input-error id="organization-type-error" :messages="$errors->get('type')" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="organization-tax-id" value="NPWP" />
                            <x-text-input
                                id="organization-tax-id"
                                wire:model="tax_id"
                                type="text"
                                placeholder="Nomor NPWP"
                                class="mt-1 font-mono"
                                aria-describedby="organization-tax-id-error"
                                aria-invalid="{{ $errors->has('tax_id') ? 'true' : 'false' }}"
                            />
                            <x-input-error id="organization-tax-id-error" :messages="$errors->get('tax_id')" />
                        </div>

                        <div>
                            <x-input-label for="organization-phone" value="Telepon" />
                            <x-text-input
                                id="organization-phone"
                                wire:model="phone"
                                type="tel"
                                inputmode="tel"
                                placeholder="Nomor telepon kantor"
                                class="mt-1"
                                aria-describedby="organization-phone-error"
                                aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}"
                            />
                            <x-input-error id="organization-phone-error" :messages="$errors->get('phone')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="organization-address" value="Alamat kantor" />
                        <x-textarea-input
                            id="organization-address"
                            wire:model="address"
                            rows="4"
                            placeholder="Alamat lengkap organisasi"
                            class="mt-1"
                            aria-describedby="organization-address-error"
                            aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}"
                        ></x-textarea-input>
                        <x-input-error id="organization-address-error" :messages="$errors->get('address')" />
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
                        <span wire:loading.remove wire:target="save">Simpan organisasi</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
