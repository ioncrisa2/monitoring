<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100">
                        System Audit Trail & Log Keamanan
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pencatatan jejak audit sistem real-time untuk seluruh tindakan sensitif, perubahan status, dan pembuatan cadangan database.</p>
                </div>
                <div>
                    <button wire:click="triggerBackup" type="button" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow flex items-center gap-2 transition cursor-pointer">
                        💾 Instant Backup Database SQLite
                    </button>
                </div>
            </div>

            @if (session()->has('message'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 rounded-xl text-sm flex items-center justify-between">
                    <span>{{ session('message') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600">&times;</button>
                </div>
            @endif

            <!-- Filter Bar Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase text-gray-400">Filter Log Aktivitas</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Cari Deskripsi / IP Address</label>
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pencarian kata kunci..." class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Filter Pengguna</label>
                        <select wire:model.live="selectedUserId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua User</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Filter Jenis Aksi</label>
                        <select wire:model.live="selectedAction" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Aksi</option>
                            <option value="CREATE">CREATE</option>
                            <option value="UPDATE">UPDATE</option>
                            <option value="DELETE">DELETE</option>
                            <option value="OVERRIDE">OVERRIDE</option>
                            <option value="CONVERT">CONVERT</option>
                            <option value="BACKUP">BACKUP</option>
                            <option value="EXPORT">EXPORT</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Audit Trail Table Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">Tabel Jejak Audit Aktivitas</h3>
                    <span class="text-xs text-gray-400">Total Log: {{ $logs->total() }}</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3.5">Waktu</th>
                                <th class="px-5 py-3.5">Pengguna</th>
                                <th class="px-5 py-3.5">Jenis Aksi</th>
                                <th class="px-5 py-3.5">Deskripsi Aktivitas</th>
                                <th class="px-5 py-3.5">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($logs as $log)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-5 py-4 text-xs font-mono text-gray-400">
                                        {{ $log->created_at->format('d M Y, H:i:s') }}
                                    </td>

                                    <td class="px-5 py-4 font-medium text-gray-900 dark:text-white">
                                        {{ $log->user?->name ?? 'System' }}
                                        @if($log->user?->role)
                                            <div class="text-[10px] text-gray-400 uppercase font-mono">{{ $log->user->role }}</div>
                                        @endif
                                    </td>

                                    <td class="px-5 py-4 font-mono text-xs">
                                        @php
                                            $actionClasses = [
                                                'CREATE' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                                'UPDATE' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
                                                'DELETE' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
                                                'OVERRIDE' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                                'BACKUP' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300',
                                            ];
                                            $class = $actionClasses[$log->action] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                                        @endphp
                                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-full {{ $class }}">
                                            {{ $log->action }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 text-xs text-gray-800 dark:text-gray-200">
                                        {{ $log->description }}
                                    </td>

                                    <td class="px-5 py-4 font-mono text-xs text-gray-400">
                                        {{ $log->ip_address ?? '127.0.0.1' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-gray-500">Belum ada rekaman audit log.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
