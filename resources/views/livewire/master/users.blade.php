<div>
    @php
        $roleLabels = [
            'sysadmin' => 'System Admin',
            'supervisor' => 'Supervisor',
            'admin' => 'Admin',
            'reviewer' => 'Reviewer',
            'surveyor' => 'Surveyor',
        ];
    @endphp

    <div class="ui-page space-y-6">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Pengguna</h1>
                <p class="ui-page-description">Kelola akun, penugasan cabang, status, dan peran akses pengguna sistem.</p>
            </div>

            <div class="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row">
                @can('menu.master-users')
                    <a href="{{ route('master.roles-permissions') }}" wire:navigate class="ui-btn ui-btn-secondary w-full sm:w-auto">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3.5 4.5 6.75v5.5c0 4.15 2.82 7.64 7.5 8.75 4.68-1.11 7.5-4.6 7.5-8.75v-5.5L12 3.5Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9.25 12 1.75 1.75 3.75-4" />
                        </svg>
                        Peran dan hak akses
                    </a>
                @endcan

                <x-primary-button type="button" wire:click="create" class="w-full sm:w-auto">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5" />
                    </svg>
                    Tambah pengguna
                </x-primary-button>
            </div>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        <section aria-labelledby="user-list-heading">
            <div class="ui-toolbar mb-4">
                <div>
                    <h2 id="user-list-heading" class="ui-section-heading">Daftar pengguna</h2>
                    <p class="ui-section-description">{{ $users->total() }} akun ditemukan.</p>
                </div>

                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 md:w-auto">
                    <div class="relative sm:min-w-64">
                        <x-input-label for="user-search" value="Cari pengguna" class="sr-only" />
                        <x-text-input
                            id="user-search"
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="Cari nama atau email"
                            class="pl-10"
                        />
                        <svg class="pointer-events-none absolute left-3 top-3 size-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                        </svg>
                    </div>

                    <div>
                        <x-input-label for="user-role-filter" value="Filter peran" class="sr-only" />
                        <x-select-input id="user-role-filter" wire:model.live="filterRole" aria-label="Filter peran pengguna">
                            <option value="">Semua peran</option>
                            <option value="sysadmin">System Admin</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                            <option value="reviewer">Reviewer</option>
                            <option value="surveyor">Surveyor</option>
                        </x-select-input>
                    </div>

                    <div>
                        <x-input-label for="user-branch-filter" value="Filter cabang" class="sr-only" />
                        <x-select-input id="user-branch-filter" wire:model.live="filterBranchId" aria-label="Filter cabang pengguna">
                            <option value="">Semua cabang</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <p wire:loading wire:target="search,filterRole,filterBranchId" class="ui-help sm:col-span-3" role="status">Memperbarui daftar…</p>
                </div>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <caption class="sr-only">Daftar akun pengguna sistem</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nama dan email</th>
                            <th scope="col">Cabang</th>
                            <th scope="col">Peran</th>
                            <th scope="col">Telepon</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr wire:key="user-row-{{ $user->id }}">
                                <td>
                                    <div class="font-medium text-ink">{{ $user->name }}</div>
                                    <div class="mt-1 text-xs text-ink-muted">{{ $user->email }}</div>
                                </td>
                                <td>{{ $user->branch?->name ?? '-' }}</td>
                                <td><span class="ui-badge ui-badge-neutral">{{ $roleLabels[$user->role] ?? $user->role }}</span></td>
                                <td><span class="tabular-nums">{{ $user->phone ?: '-' }}</span></td>
                                <td><x-active-status-badge :active="$user->active" /></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <button
                                            wire:click="toggleActive({{ $user->id }})"
                                            type="button"
                                            class="ui-text-action"
                                            aria-label="{{ $user->active ? 'Nonaktifkan' : 'Aktifkan' }} pengguna {{ $user->name }}"
                                        >
                                            {{ $user->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                        <button
                                            wire:click="edit({{ $user->id }})"
                                            type="button"
                                            class="ui-text-action"
                                            aria-label="Edit pengguna {{ $user->name }}"
                                        >
                                            Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="ui-empty-state">
                                    {{ $search || $filterRole || $filterBranchId ? 'Tidak ada pengguna yang cocok dengan pencarian atau filter.' : 'Belum ada pengguna.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="mt-4">{{ $users->links() }}</div>
            @endif
        </section>
    </div>

    @if($showModal)
        <x-modal name="user-editor" :show="$showModal" close-property="showModal" maxWidth="md" labelledby="user-editor-title" focusable>
            <div class="ui-modal-header">
                <div>
                    <h2 id="user-editor-title" class="ui-modal-title">{{ $editingId ? 'Edit pengguna' : 'Tambah pengguna' }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">Atur identitas akun, cabang, dan peran aksesnya.</p>
                </div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form pengguna">&times;</button>
            </div>

            <form wire:submit="save">
                <div class="ui-modal-body space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="user-name" value="Nama lengkap" />
                            <x-text-input
                                id="user-name"
                                wire:model="name"
                                type="text"
                                autocomplete="name"
                                placeholder="Nama pengguna"
                                class="mt-1"
                                aria-describedby="user-name-error"
                                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                            />
                            <x-input-error id="user-name-error" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="user-email" value="Email" />
                            <x-text-input
                                id="user-email"
                                wire:model="email"
                                type="email"
                                autocomplete="email"
                                placeholder="nama@perusahaan.com"
                                class="mt-1"
                                aria-describedby="user-email-error"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            />
                            <x-input-error id="user-email-error" :messages="$errors->get('email')" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="user-password" :value="$editingId ? 'Password baru (opsional)' : 'Password'" />
                            <x-text-input
                                id="user-password"
                                wire:model="password"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Minimal 6 karakter"
                                class="mt-1"
                                aria-describedby="user-password-help user-password-error"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            />
                            <p id="user-password-help" class="ui-help">
                                {{ $editingId ? 'Kosongkan untuk mempertahankan password saat ini.' : 'Gunakan minimal 6 karakter.' }}
                            </p>
                            <x-input-error id="user-password-error" :messages="$errors->get('password')" />
                        </div>

                        <div>
                            <x-input-label for="user-phone" value="Telepon atau WhatsApp" />
                            <x-text-input
                                id="user-phone"
                                wire:model="phone"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="08123456789"
                                class="mt-1"
                                aria-describedby="user-phone-error"
                                aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}"
                            />
                            <x-input-error id="user-phone-error" :messages="$errors->get('phone')" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="user-branch" value="Penugasan cabang" />
                            <x-select-input
                                id="user-branch"
                                wire:model="branch_id"
                                class="mt-1"
                                aria-describedby="user-branch-error"
                                aria-invalid="{{ $errors->has('branch_id') ? 'true' : 'false' }}"
                            >
                                <option value="">Pilih cabang</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error id="user-branch-error" :messages="$errors->get('branch_id')" />
                        </div>

                        <div>
                            <x-input-label for="user-role" value="Peran akses" />
                            <x-select-input
                                id="user-role"
                                wire:model="role"
                                class="mt-1"
                                aria-describedby="user-role-error"
                                aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}"
                            >
                                <option value="admin">Admin</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="reviewer">Reviewer</option>
                                <option value="surveyor">Surveyor</option>
                                <option value="sysadmin">System Admin</option>
                            </x-select-input>
                            <x-input-error id="user-role-error" :messages="$errors->get('role')" />
                        </div>
                    </div>

                    <label for="user-active" class="flex min-h-10 items-center gap-3 text-sm text-ink-secondary">
                        <input id="user-active" wire:model="active" type="checkbox" class="size-4 rounded border-line-strong text-brand focus:ring-brand">
                        <span>
                            <span class="block font-medium text-ink">Akun aktif</span>
                            <span class="mt-0.5 block text-xs text-ink-muted">Akun aktif tersedia pada daftar penugasan operasional.</span>
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
                        <span wire:loading.remove wire:target="save">Simpan pengguna</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
