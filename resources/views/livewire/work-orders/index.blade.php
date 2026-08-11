<div>
    <div class="ui-page">
        <div class="space-y-6">
            <header class="ui-page-header">
                <div>
                    <h1 class="ui-page-title">Pekerjaan</h1>
                    <p class="ui-page-description">Pantau alur pengerjaan, SLA, penugasan PIC, dan waktu yang berjalan pada setiap status.</p>
                </div>
            </header>

            @if (session()->has('message'))
                <div role="status" class="flex items-center justify-between gap-4 rounded-ui border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <span>{{ session('message') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="ui-icon-btn -my-2 h-8 w-8 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-900/60 dark:hover:text-emerald-200" aria-label="Tutup pemberitahuan">&times;</button>
                </div>
            @endif

            <section aria-labelledby="work-order-list-heading">
                <h2 id="work-order-list-heading" class="sr-only">Daftar pekerjaan</h2>

                <div class="ui-toolbar mb-4">
                    <div class="flex flex-1 flex-col gap-3 lg:flex-row lg:items-center">
                        <div class="relative w-full lg:max-w-sm">
                            <label for="work-order-search" class="sr-only">Cari pekerjaan</label>
                            <input id="work-order-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Cari kontrak, debitur, atau klien" class="ui-field pl-10">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg aria-hidden="true" class="h-4 w-4 text-ui-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:flex lg:items-center">
                            <label for="work-order-status" class="sr-only">Filter status</label>
                            <x-select-input id="work-order-status" wire:model.live="filterStatus" class="lg:w-48">
                                <option value="">Semua status</option>
                                <option value="PERSIAPAN">Persiapan</option>
                                <option value="SURVEY">Survey</option>
                                <option value="PENGERJAAN">Pengerjaan</option>
                                <option value="REVIEW">Review</option>
                                <option value="CETAK">Cetak</option>
                                <option value="SELESAI">Selesai</option>
                                <option value="BATAL">Batal</option>
                            </x-select-input>

                            <label for="work-order-branch" class="sr-only">Filter cabang</label>
                            <x-select-input id="work-order-branch" wire:model.live="filterBranchId" class="lg:w-52">
                                <option value="">Semua cabang</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </x-select-input>
                        </div>

                        <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 whitespace-nowrap text-sm font-medium text-ui-secondary">
                            <input wire:model.live="filterOverdueOnly" type="checkbox" class="rounded-ui-sm border-line-strong text-rose-700 shadow-none focus:ring-2 focus:ring-rose-600 focus:ring-offset-2 dark:bg-surface dark:focus:ring-offset-gray-900">
                            <span>Hanya SLA terlewat</span>
                        </label>
                    </div>

                    <div wire:loading.flex wire:target="search,filterStatus,filterBranchId,filterOverdueOnly" class="items-center gap-2 text-xs text-ui-muted" role="status">
                        <svg aria-hidden="true" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Memuat data
                    </div>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Daftar pekerjaan beserta cabang, klien, SLA, status, dan waktu berjalan</caption>
                        <thead>
                            <tr>
                                <th scope="col">Kontrak & cabang</th>
                                <th scope="col">Debitur & klien</th>
                                <th scope="col">Batas SLA</th>
                                <th scope="col">Status</th>
                                <th scope="col">Waktu berjalan</th>
                                <th scope="col" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workOrders as $workOrder)
                                <tr wire:key="work-order-{{ $workOrder->id }}">
                                    <td>
                                        <div class="font-mono text-sm font-semibold text-brand">{{ $workOrder->contract_no }}</div>
                                        <div class="mt-0.5 text-xs text-ui-muted">{{ $workOrder->offer?->branch?->name ?? 'Cabang tidak tersedia' }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-ui-primary">{{ $workOrder->offer?->debtor?->name ?? 'Debitur tidak tersedia' }}</div>
                                        <div class="mt-0.5 text-xs text-ui-muted">{{ $workOrder->offer?->client?->name ?? 'Klien tidak tersedia' }}</div>
                                    </td>
                                    <td class="tabular-nums">
                                        <div class="whitespace-nowrap text-sm">{{ $workOrder->sla_date ? $workOrder->sla_date->format('d M Y') : 'Belum ditetapkan' }}</div>
                                        <x-sla-badge :overdue="$workOrder->is_overdue" :applicable="$workOrder->sla_date && !in_array($workOrder->current_status, ['SELESAI', 'BATAL'])" class="mt-1" />
                                    </td>
                                    <td>
                                        <x-status-badge :status="$workOrder->current_status" />
                                    </td>
                                    <td class="whitespace-nowrap tabular-nums text-sm">
                                        {{ $workOrder->aging_days }} hari
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('work-orders.show', $workOrder->id) }}" wire:navigate class="inline-flex min-h-9 items-center rounded-ui-sm px-2 text-sm font-semibold text-brand transition hover:bg-brand-soft hover:text-brand-hover">
                                            Buka &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="h-auto px-6 py-12 text-center">
                                        <div class="font-medium text-ui-primary">Pekerjaan tidak ditemukan</div>
                                        <p class="mt-1 text-sm text-ui-muted">Ubah kata pencarian atau filter untuk melihat data lainnya.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $workOrders->links() }}
                </div>
            </section>
        </div>
    </div>
</div>
