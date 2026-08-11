<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100">
                        Laporan Produksi & Export Center
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pusat ringkasan data produksi, laporan resmi terbit, bukti kirim resi, dan ekspor Excel terstruktur.</p>
                </div>
                <div>
                    <button wire:click="exportExcel" type="button" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow flex items-center gap-2 transition cursor-pointer">
                        📊 Export Excel (Format Rapi 5 Kelompok Data)
                    </button>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase text-gray-400">Filter Laporan Produksi</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Filter Cabang</label>
                        <select wire:model.live="selectedBranchId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Cabang (Global)</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Filter Status Pekerjaan</label>
                        <select wire:model.live="selectedStatus" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Status</option>
                            <option value="PERSIAPAN">PERSIAPAN</option>
                            <option value="SURVEY">SURVEY</option>
                            <option value="PENGERJAAN">PENGERJAAN</option>
                            <option value="REVIEW">REVIEW</option>
                            <option value="CETAK">CETAK</option>
                            <option value="SELESAI">SELESAI</option>
                            <option value="BATAL">BATAL</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Dari Tanggal</label>
                        <input wire:model.live="fromDate" type="date" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Sampai Tanggal</label>
                        <input wire:model.live="toDate" type="date" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <!-- Table Production Summary -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">Ringkasan Tabel Produksi</h3>
                    <span class="text-xs text-gray-400">Menampilkan {{ $workOrders->total() }} Data Pekerjaan</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Identitas Kontrak</th>
                                <th class="px-4 py-3">Debitur & Klien</th>
                                <th class="px-4 py-3 text-right">Fee (Rp)</th>
                                <th class="px-4 py-3">Operasional & SLA</th>
                                <th class="px-4 py-3">Laporan Resmi</th>
                                <th class="px-4 py-3">Pengiriman & Resi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($workOrders as $wo)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-white">
                                        {{ $wo->contract_no }}
                                        <div class="text-[10px] text-gray-400 font-sans">{{ $wo->offer?->branch?->code }} | {{ $wo->contract_date ? $wo->contract_date->format('d M Y') : '-' }}</div>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $wo->offer?->debtor?->name }}</div>
                                        <div class="text-xs text-gray-400">{{ $wo->offer?->client?->name }}</div>
                                    </td>

                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900 dark:text-white">
                                        Rp {{ number_format($wo->offer?->fee ?? 0, 0, ',', '.') }}
                                    </td>

                                    <td class="px-4 py-3 text-xs">
                                        <div class="font-semibold text-indigo-600 dark:text-indigo-400 font-mono">{{ $wo->current_status }}</div>
                                        <div class="text-[11px] text-gray-400">SLA: {{ $wo->sla_date ? $wo->sla_date->format('d M Y') : '-' }}</div>
                                        @if($wo->is_overdue)
                                            <span class="inline-block px-1.5 py-0.5 bg-rose-100 text-rose-700 font-bold rounded text-[9px]">OVERDUE</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-xs">
                                        @forelse($wo->reports as $rep)
                                            <div class="font-mono font-semibold text-gray-900 dark:text-white">{{ $rep->report_no }}</div>
                                            <div class="text-[10px] text-emerald-600 font-mono">Nilai: Rp {{ number_format($rep->report_value ?? 0, 0, ',', '.') }}</div>
                                        @empty
                                            <span class="text-gray-400">Belum terbit</span>
                                        @endforelse
                                    </td>

                                    <td class="px-4 py-3 text-xs">
                                        @php $del = $wo->reports->first()?->delivery; @endphp
                                        @if($del)
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $del->courier }} (Resi: {{ $del->tracking_no ?? '-' }})</div>
                                            <div class="text-[10px] text-gray-400">Kirim: {{ $del->sent_date ? $del->sent_date->format('d M Y') : '-' }}</div>
                                        @else
                                            <span class="text-gray-400">Belum dikirim</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada data produksi sesuai filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $workOrders->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
