<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100">
                        Pekerjaan
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tracking alur proses pengerjaan, status SLA, penugasan PIC, dan aging status real-time.</p>
                </div>
            </div>

            @if (session()->has('message'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 rounded-xl text-sm flex items-center justify-between">
                    <span>{{ session('message') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600">&times;</button>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl p-6 border border-gray-100 dark:border-gray-700/50">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative w-full sm:w-72">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari No Kontrak, Debitur, Klien..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                        </div>

                        <select wire:model.live="filterStatus" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Status Workflow</option>
                            <option value="PERSIAPAN">PERSIAPAN</option>
                            <option value="SURVEY">SURVEY</option>
                            <option value="PENGERJAAN">PENGERJAAN</option>
                            <option value="REVIEW">REVIEW</option>
                            <option value="CETAK">CETAK</option>
                            <option value="SELESAI">SELESAI</option>
                            <option value="BATAL">BATAL</option>
                        </select>

                        <select wire:model.live="filterBranchId" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Cabang</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>

                        <label class="inline-flex items-center text-xs font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input wire:model.live="filterOverdueOnly" type="checkbox" class="rounded border-gray-300 text-rose-600 focus:ring-rose-500">
                            <span class="ms-2">Hanya Overdue SLA</span>
                        </label>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3.5">No Kontrak & Cabang</th>
                                <th class="px-6 py-3.5">Debitur & Klien</th>
                                <th class="px-6 py-3.5">Deadline SLA</th>
                                <th class="px-6 py-3.5">Status Lifecycle</th>
                                <th class="px-6 py-3.5">Aging</th>
                                <th class="px-6 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($workOrders as $workOrder)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ $workOrder->contract_no }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $workOrder->offer?->branch?->name }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $workOrder->offer?->debtor?->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $workOrder->offer?->client?->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs">
                                        <div>{{ $workOrder->sla_date ? $workOrder->sla_date->format('d M Y') : '-' }}</div>
                                        <x-sla-badge :overdue="$workOrder->is_overdue" :applicable="$workOrder->sla_date && !in_array($workOrder->current_status, ['SELESAI', 'BATAL'])" class="mt-1" />
                                    </td>
                                    <td class="px-6 py-4">
                                        <x-status-badge :status="$workOrder->current_status" />
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs text-gray-600 dark:text-gray-300">
                                        {{ $workOrder->aging_days }} hari
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('work-orders.show', $workOrder->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-medium text-xs">
                                            Buka &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">Tidak ada data pekerjaan ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $workOrders->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
