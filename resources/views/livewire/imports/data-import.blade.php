<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100">
                        Impor Data & Migrasi Historis
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unggah berkas Excel / CSV rekap produksi (Workbook 2026 atau Historis 2024-2025) untuk dikonversi otomatis ke database produksi.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="downloadTemplate" type="button" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-xl text-sm font-semibold transition cursor-pointer flex items-center gap-2">
                        📥 Download Format Template CSV
                    </button>
                </div>
            </div>

            @if (session()->has('message'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 rounded-xl text-sm flex items-center justify-between">
                    <span>{{ session('message') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600">&times;</button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="p-4 bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 rounded-xl text-sm flex items-center justify-between">
                    <span>{{ session('error') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="text-rose-400 hover:text-rose-600">&times;</button>
                </div>
            @endif

            <!-- Form Upload Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">1. Unggah Berkas Rekap Produksi (.CSV / .TXT)</h3>
                
                <form wire:submit="uploadFile" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Cabang Default (Jika Kode Kosong)</label>
                            <select wire:model="default_branch_code" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                                @foreach($branches as $b)
                                    <option value="{{ $b->code }}">{{ $b->name }} ({{ $b->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Pilih Berkas CSV / Rekap Excel (Max 10MB)</label>
                            <div class="flex gap-3">
                                <input wire:model="upload_file" type="file" accept=".csv, .txt" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm">
                                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg text-sm transition cursor-pointer shrink-0">
                                    Upload & Stage Data
                                </button>
                            </div>
                            @error('upload_file') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </form>
            </div>

            <!-- Staging Data Preview & Action Bar -->
            @if($currentBatchId)
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-4">
                        <div>
                            <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">2. Preview & Validation Staging Table</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Total Record: {{ $totalStaging }} | Belum Diproses: {{ $unprocessedCount }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="clearStaging" type="button" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-xl cursor-pointer">
                                Bersihkan Staging
                            </button>
                            @if($unprocessedCount > 0)
                                <button wire:click="processBatch" type="button" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow cursor-pointer">
                                    ⚡ Konversi Staging ke Database Utama
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">No. Penawaran</th>
                                    <th class="px-4 py-3">Cabang</th>
                                    <th class="px-4 py-3">Debitur & Klien</th>
                                    <th class="px-4 py-3 text-right">Fee (Rp)</th>
                                    <th class="px-4 py-3">No. Laporan Resmi</th>
                                    <th class="px-4 py-3 text-right">Nilai Laporan</th>
                                    <th class="px-4 py-3">Status Pros.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($stagingItems as $item)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-white">
                                            {{ $item->offer_no }}
                                            <div class="text-[10px] text-gray-400">{{ $item->contract_date ? $item->contract_date->format('d M Y') : '-' }}</div>
                                        </td>

                                        <td class="px-4 py-3 font-mono text-xs">{{ $item->branch_code }}</td>

                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $item->debtor_name }}</div>
                                            <div class="text-xs text-gray-400">{{ $item->client_name }}</div>
                                        </td>

                                        <td class="px-4 py-3 text-right font-mono font-bold text-gray-900 dark:text-white">
                                            Rp {{ number_format($item->fee, 0, ',', '.') }}
                                        </td>

                                        <td class="px-4 py-3 font-mono text-xs">
                                            {{ $item->report_no ?? '-' }}
                                        </td>

                                        <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 text-xs">
                                            Rp {{ number_format($item->report_value, 0, ',', '.') }}
                                        </td>

                                        <td class="px-4 py-3">
                                            @if($item->is_processed)
                                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 font-bold rounded text-[10px]">TERSELESAIKAN</span>
                                            @else
                                                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 font-bold rounded text-[10px]">SIAP DIKONVERSI</span>
                                            @endif

                                            @if($item->error_message)
                                                <div class="text-[10px] text-rose-600 mt-1 font-mono">{{ $item->error_message }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Tidak ada item staging.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $stagingItems->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
