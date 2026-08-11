<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100 flex items-center gap-3">
                        <span>Manajemen Role & Hak Akses</span>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300">Sysadmin Only</span>
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Konfigurasi matriks perizinan (permissions) per role secara interaktif.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('master.users') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg transition duration-150 cursor-pointer">
                        <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 43g"/></svg>
                        Daftar Pengguna
                    </a>
                    <button wire:click="$set('showCreateModal', true)" type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-medium rounded-lg shadow transition duration-150 cursor-pointer">
                        <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Role Baru
                    </button>
                </div>
            </div>

            @if (session()->has('message'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 rounded-xl text-sm flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('message') }}</span>
                    </div>
                    <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600">&times;</button>
                </div>
            @endif

            <!-- Main Layout: Left Role List, Right Permissions Matrix -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Role Selector Column (4 cols) -->
                <div class="lg:col-span-4 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 px-1">Daftar Role Sistem</h3>
                    
                    <div class="space-y-2">
                        @foreach($roles as $role)
                            @php
                                $isSelected = $selectedRoleId === $role->id;
                            @endphp
                            <div wire:click="selectRole({{ $role->id }})" 
                                 class="p-4 rounded-xl border transition cursor-pointer flex items-center justify-between {{ $isSelected ? 'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-500 ring-2 ring-indigo-500/20' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700/80 hover:border-gray-300 dark:hover:border-gray-600' }}">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-base {{ $isSelected ? 'text-indigo-900 dark:text-indigo-200' : 'text-gray-900 dark:text-white' }}">
                                            {{ strtoupper($role->name) }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-3">
                                        <span>👥 {{ $role->users_count }} Pengguna</span>
                                        <span>🔑 {{ $role->permissions->count() }} Hak Akses</span>
                                    </div>
                                </div>
                                @if($isSelected)
                                    <div class="text-indigo-600 dark:text-indigo-400">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Permission Matrix Column (8 cols) -->
                <div class="lg:col-span-8">
                    @if($selectedRoleId)
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-200 dark:border-gray-700 space-y-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700 gap-3">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-900 dark:text-white">
                                        Hak Akses Role: <span class="text-indigo-600 dark:text-indigo-400 uppercase">{{ $selectedRoleName }}</span>
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Centang atau hapus centang untuk mengatur perizinan modul bagi role ini.</p>
                                </div>
                                <div>
                                    <button wire:click="savePermissions" type="button" class="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-medium rounded-lg shadow-md transition duration-150 cursor-pointer flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </div>

                            <!-- Permission Categories -->
                            <div class="space-y-6">
                                @foreach($groupedPermissions as $category => $permissions)
                                    @if($permissions->count() > 0)
                                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700/60 space-y-3">
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700/80 pb-2">
                                                {{ $category }}
                                            </h4>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                                                @foreach($permissions as $perm)
                                                    <label class="flex items-start gap-3 p-2.5 rounded-lg hover:bg-white dark:hover:bg-gray-800 border border-transparent hover:border-gray-200 dark:hover:border-gray-700 transition cursor-pointer">
                                                        <input type="checkbox" 
                                                               value="{{ $perm->name }}" 
                                                               wire:model="selectedPermissions"
                                                               class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                                        <div class="text-sm">
                                                            <div class="font-medium text-gray-900 dark:text-gray-200">
                                                                {{ str_replace(['menu.', 'work-orders.', 'users.'], '', $perm->name) }}
                                                            </div>
                                                            <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                                                                {{ $perm->name }}
                                                            </div>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                                <button wire:click="savePermissions" type="button" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg shadow transition cursor-pointer">
                                    Simpan Hak Akses {{ strtoupper($selectedRoleName) }}
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Create Role Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Tambah Role Baru</h3>
                    <button wire:click="$set('showCreateModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>

                <form wire:submit="createRole" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nama Role</label>
                        <input wire:model="newRoleName" type="text" placeholder="Contoh: auditor, manager_operasional" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        <p class="text-xs text-gray-400 mt-1">Gunakan huruf kecil atau underscore.</p>
                        @error('newRoleName') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium cursor-pointer">Buat Role</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
