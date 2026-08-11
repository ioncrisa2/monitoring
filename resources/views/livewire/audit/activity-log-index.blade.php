<div>
    @php
        $auditActionMeta = [
            'CREATE' => ['label' => 'Buat', 'class' => 'ui-badge-success'],
            'UPDATE' => ['label' => 'Ubah', 'class' => 'ui-badge-info'],
            'DELETE' => ['label' => 'Hapus', 'class' => 'ui-badge-danger'],
            'OVERRIDE' => ['label' => 'Timpa', 'class' => 'ui-badge-warning'],
            'CONVERT' => ['label' => 'Konversi', 'class' => 'ui-badge-success'],
            'BACKUP' => ['label' => 'Cadangan', 'class' => 'ui-badge-neutral'],
            'EXPORT' => ['label' => 'Ekspor', 'class' => 'ui-badge-info'],
        ];
    @endphp

    <div class="ui-page space-y-6">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Jejak Audit</h1>
                <p class="ui-page-description">Tinjau aktivitas yang dicatat aplikasi dan buat cadangan database saat diperlukan.</p>
            </div>

            <x-primary-button
                type="button"
                wire:click="triggerBackup"
                wire:loading.attr="disabled"
                wire:target="triggerBackup"
                class="w-full sm:w-auto"
            >
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 4h11l3 3v13H5V4Zm3 0v6h8V4M8 20v-6h8v6" />
                </svg>
                <span wire:loading.remove wire:target="triggerBackup">Buat cadangan</span>
                <span wire:loading wire:target="triggerBackup">Membuat…</span>
            </x-primary-button>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        <section aria-labelledby="audit-filter-heading">
            <div class="mb-4">
                <h2 id="audit-filter-heading" class="ui-section-heading">Filter aktivitas</h2>
                <p class="ui-section-description">Persempit log berdasarkan kata kunci, pengguna aktif, atau jenis tindakan.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 border-b border-line pb-5 sm:grid-cols-3">
                <div>
                    <x-input-label for="audit-search" value="Deskripsi atau alamat IP" />
                    <x-text-input
                        id="audit-search"
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="Cari kata kunci"
                        class="mt-1"
                    />
                </div>

                <div>
                    <x-input-label for="audit-user-filter" value="Pengguna" />
                    <x-select-input id="audit-user-filter" wire:model.live="selectedUserId" class="mt-1">
                        <option value="">Semua pengguna aktif</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ \Illuminate\Support\Str::headline($user->role) }})</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="audit-action-filter" value="Jenis tindakan" />
                    <x-select-input id="audit-action-filter" wire:model.live="selectedAction" class="mt-1">
                        <option value="">Semua tindakan</option>
                        <option value="CREATE">Buat</option>
                        <option value="UPDATE">Ubah</option>
                        <option value="DELETE">Hapus</option>
                        <option value="OVERRIDE">Timpa</option>
                        <option value="CONVERT">Konversi</option>
                        <option value="BACKUP">Cadangan</option>
                        <option value="EXPORT">Ekspor</option>
                    </x-select-input>
                </div>

                <p wire:loading wire:target="search,selectedUserId,selectedAction" class="ui-help sm:col-span-3" role="status">Memperbarui jejak audit…</p>
            </div>
        </section>

        <section aria-labelledby="audit-list-heading">
            <div class="ui-toolbar mb-4">
                <div>
                    <h2 id="audit-list-heading" class="ui-section-heading">Aktivitas tercatat</h2>
                    <p class="ui-section-description">{{ $logs->total() }} rekaman ditemukan.</p>
                </div>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <caption class="sr-only">Daftar aktivitas yang tercatat dalam jejak audit</caption>
                    <thead>
                        <tr>
                            <th scope="col">Waktu</th>
                            <th scope="col">Pengguna</th>
                            <th scope="col">Tindakan</th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col">Alamat IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            @php($actionMeta = $auditActionMeta[$log->action] ?? ['label' => \Illuminate\Support\Str::headline($log->action), 'class' => 'ui-badge-neutral'])
                            <tr wire:key="audit-log-row-{{ $log->id }}">
                                <td>
                                    <time datetime="{{ $log->created_at->toIso8601String() }}" class="whitespace-nowrap text-xs text-ink-secondary tabular-nums">
                                        {{ $log->created_at->format('d M Y, H:i:s') }}
                                    </time>
                                </td>
                                <td>
                                    <div class="font-medium text-ink">{{ $log->user?->name ?? 'Sistem' }}</div>
                                    @if($log->user?->role)
                                        <div class="mt-1 text-xs text-ink-muted">{{ \Illuminate\Support\Str::headline($log->user->role) }}</div>
                                    @endif
                                </td>
                                <td><span class="ui-badge {{ $actionMeta['class'] }}">{{ $actionMeta['label'] }}</span></td>
                                <td><p class="min-w-64 max-w-2xl whitespace-normal text-sm leading-6 text-ink-secondary">{{ $log->description }}</p></td>
                                <td><span class="whitespace-nowrap font-mono text-xs text-ink-muted">{{ $log->ip_address ?: '—' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="ui-empty-state">
                                    {{ $search || $selectedUserId || $selectedAction ? 'Tidak ada aktivitas yang cocok dengan filter.' : 'Belum ada aktivitas yang tercatat.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="mt-4">{{ $logs->links() }}</div>
            @endif
        </section>
    </div>
</div>
