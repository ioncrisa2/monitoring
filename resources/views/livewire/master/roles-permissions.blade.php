<div>
    @php
        $categoryLabels = [
            'Akses Menu Navigasi' => 'Navigasi',
            'Manajemen Pengguna' => 'Pengguna',
            'Workflow Pekerjaan / Work Order' => 'Workflow pekerjaan',
        ];

        $permissionLabels = [
            'menu.dashboard' => 'Dashboard',
            'menu.offers' => 'Penawaran',
            'menu.work-orders' => 'Pekerjaan',
            'menu.reports' => 'Laporan produksi',
            'menu.imports' => 'Impor data',
            'menu.audit-logs' => 'Jejak audit',
            'menu.master-users' => 'Pengguna dan peran',
            'menu.master-data' => 'Master data',
            'users.manage' => 'Kelola pengguna',
            'work-orders.assign-pic' => 'Atur PIC',
            'work-orders.change-status' => 'Ubah status',
            'work-orders.edit-sla' => 'Ubah SLA',
            'work-orders.survey' => 'Kelola survei dan aset',
            'work-orders.review' => 'Kelola review dan laporan',
        ];
    @endphp

    <div class="ui-page space-y-6">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Peran dan Hak Akses</h1>
                <p class="ui-page-description">Atur akses menu dan tindakan operasional untuk setiap peran dalam sistem.</p>
            </div>

            <div class="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row">
                <a href="{{ route('master.users') }}" wire:navigate class="ui-btn ui-btn-secondary w-full sm:w-auto">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 20a4 4 0 0 0-8 0m4-7a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 7a3.5 3.5 0 0 0-3-3.46M17 5.13a3.5 3.5 0 0 1 0 6.74" />
                    </svg>
                    Daftar pengguna
                </a>
                <x-primary-button type="button" wire:click="$set('showCreateModal', true)" class="w-full sm:w-auto">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5" />
                    </svg>
                    Tambah peran
                </x-primary-button>
            </div>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside aria-labelledby="role-list-heading">
                <div class="mb-3">
                    <h2 id="role-list-heading" class="ui-section-heading">Daftar peran</h2>
                    <p class="ui-section-description">Pilih peran untuk mengatur hak aksesnya.</p>
                </div>

                <div class="ui-surface grid gap-1 p-2 sm:grid-cols-2 lg:grid-cols-1">
                    @forelse($roles as $role)
                        @php
                            $isSelected = $selectedRoleId === $role->id;
                            $roleLabel = \Illuminate\Support\Str::headline($role->name);
                        @endphp
                        <button
                            wire:key="role-option-{{ $role->id }}"
                            wire:click="selectRole({{ $role->id }})"
                            type="button"
                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                            class="flex min-h-16 w-full items-center justify-between gap-3 rounded-ui px-3 py-2.5 text-left transition {{ $isSelected ? 'bg-brand-soft text-brand' : 'text-ink-secondary hover:bg-surface-subtle hover:text-ink' }}"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold">{{ $roleLabel }}</span>
                                <span class="mt-1 block text-xs tabular-nums {{ $isSelected ? 'text-brand/80' : 'text-ink-muted' }}">
                                    {{ $role->users_count }} pengguna · {{ $role->permissions->count() }} hak akses
                                </span>
                            </span>
                            @if($isSelected)
                                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m7.5 12 3 3 6-6" />
                                </svg>
                            @endif
                        </button>
                    @empty
                        <p class="ui-empty-state sm:col-span-2 lg:col-span-1">Belum ada peran.</p>
                    @endforelse
                </div>
            </aside>

            <section class="min-w-0" aria-labelledby="permission-panel-heading">
                @if($selectedRoleId)
                    <div class="ui-toolbar mb-1">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 id="permission-panel-heading" class="ui-section-heading">Hak akses</h2>
                                <span class="ui-badge ui-badge-neutral">{{ \Illuminate\Support\Str::headline($selectedRoleName) }}</span>
                            </div>
                            <p class="ui-section-description">Perubahan diterapkan setelah tombol simpan ditekan.</p>
                        </div>

                        <x-primary-button
                            type="button"
                            wire:click="savePermissions"
                            wire:loading.attr="disabled"
                            wire:target="savePermissions"
                            class="w-full sm:w-auto"
                        >
                            <span wire:loading.remove wire:target="savePermissions">Simpan hak akses</span>
                            <span wire:loading wire:target="savePermissions">Menyimpan…</span>
                        </x-primary-button>
                    </div>

                    <div class="divide-y divide-line">
                        @foreach($groupedPermissions as $category => $permissions)
                            @if($permissions->isNotEmpty())
                                <fieldset class="py-5 first:pt-4" wire:key="permission-group-{{ \Illuminate\Support\Str::slug($category) }}">
                                    <legend class="ui-section-heading">{{ $categoryLabels[$category] ?? $category }}</legend>
                                    <div class="mt-3 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                        @foreach($permissions as $permission)
                                            <label
                                                for="permission-{{ $permission->id }}"
                                                class="flex min-h-14 cursor-pointer items-start gap-3 rounded-ui-sm px-3 py-2.5 transition hover:bg-surface-subtle"
                                                wire:key="permission-option-{{ $permission->id }}"
                                            >
                                                <input
                                                    id="permission-{{ $permission->id }}"
                                                    type="checkbox"
                                                    value="{{ $permission->name }}"
                                                    wire:model="selectedPermissions"
                                                    class="mt-0.5 size-4 rounded border-line-strong text-brand focus:ring-brand"
                                                >
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-medium text-ink">{{ $permissionLabels[$permission->name] ?? \Illuminate\Support\Str::headline(\Illuminate\Support\Str::afterLast($permission->name, '.')) }}</span>
                                                    <span class="mt-0.5 block break-all font-mono text-xs text-ink-muted">{{ $permission->name }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="ui-empty-state border-y border-line">Pilih peran untuk melihat dan mengatur hak akses.</div>
                @endif
            </section>
        </div>
    </div>

    @if($showCreateModal)
        <x-modal name="role-creator" :show="$showCreateModal" close-property="showCreateModal" maxWidth="sm" labelledby="role-creator-title" focusable>
            <div class="ui-modal-header">
                <div>
                    <h2 id="role-creator-title" class="ui-modal-title">Tambah peran</h2>
                    <p class="mt-1 text-sm text-ink-muted">Peran baru dibuat tanpa hak akses dan dapat dikonfigurasi setelah disimpan.</p>
                </div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form peran">&times;</button>
            </div>

            <form wire:submit="createRole">
                <div class="ui-modal-body">
                    <x-input-label for="new-role-name" value="Nama peran" />
                    <x-text-input
                        id="new-role-name"
                        wire:model="newRoleName"
                        type="text"
                        placeholder="Contoh: auditor atau manajer operasional"
                        class="mt-1"
                        aria-describedby="new-role-name-help new-role-name-error"
                        aria-invalid="{{ $errors->has('newRoleName') ? 'true' : 'false' }}"
                    />
                    <p id="new-role-name-help" class="ui-help">Spasi akan diubah menjadi underscore dan nama disimpan dalam huruf kecil.</p>
                    <x-input-error id="new-role-name-error" :messages="$errors->get('newRoleName')" />
                </div>

                <div class="ui-modal-footer">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')" class="w-full sm:w-auto">Batal</x-secondary-button>
                    <x-primary-button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="createRole"
                        class="w-full sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="createRole">Buat peran</span>
                        <span wire:loading wire:target="createRole">Membuat…</span>
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
