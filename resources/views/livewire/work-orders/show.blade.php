<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('work-orders.index') }}" wire:navigate class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-semibold">&larr; Kembali</a>
                        <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100 font-mono">
                            {{ $workOrder->contract_no }}
                        </h2>
                        @if($workOrder->is_overdue)
                            <span class="px-3 py-1 bg-rose-100 dark:bg-rose-900/60 text-rose-700 dark:text-rose-300 font-bold rounded-full text-xs animate-pulse">OVERDUE SLA</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Cabang: {{ $workOrder->offer?->branch?->name }} | Debitur: {{ $workOrder->offer?->debtor?->name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="$set('showSlaModal', true)" type="button" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium transition cursor-pointer">
                        Edit SLA & Flag Survey
                    </button>
                    <button wire:click="$set('showAssignModal', true)" type="button" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow transition cursor-pointer">
                        Assign PIC (Surveyor & Reviewer)
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

            <!-- Status Funnel Lifecycle Progress Bar -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700/50 space-y-4">
                <h3 class="text-xs font-bold uppercase text-gray-400">Alur Status Pengerjaan (Lifecycle Funnel)</h3>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    @php
                        $statuses = $workOrder->survey_required 
                            ? ['PERSIAPAN', 'SURVEY', 'PENGERJAAN', 'REVIEW', 'CETAK', 'SELESAI']
                            : ['PERSIAPAN', 'PENGERJAAN', 'REVIEW', 'CETAK', 'SELESAI'];
                        $currentIdx = array_search($workOrder->current_status, $statuses);
                    @endphp

                    @foreach($statuses as $idx => $st)
                        <div class="flex-1 min-w-[100px]">
                            <button wire:click="openStatusModal('{{ $st }}')" type="button" 
                                class="w-full py-3 px-2 rounded-xl text-center font-semibold text-xs transition border cursor-pointer
                                {{ $workOrder->current_status === $st 
                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-500/30 scale-105' 
                                    : ($currentIdx !== false && $idx < $currentIdx 
                                        ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700' 
                                        : 'bg-gray-50 dark:bg-gray-900/60 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-indigo-400') }}">
                                <div>{{ $st }}</div>
                                @if($workOrder->current_status === $st)
                                    <div class="text-[10px] font-normal opacity-90">Status Saat Ini</div>
                                @endif
                            </button>
                        </div>
                        @if(!$loop->last)
                            <div class="hidden md:block text-gray-300 dark:text-gray-600">&rarr;</div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Tab Navigation Bar -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8">
                    <button wire:click="$set('activeTab', 'info')" type="button" class="py-4 px-1 inline-flex items-center gap-2 border-b-2 text-sm font-semibold cursor-pointer {{ $activeTab === 'info' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        Informasi Utama
                    </button>

                    <button wire:click="$set('activeTab', 'assets')" type="button" class="py-4 px-1 inline-flex items-center gap-2 border-b-2 text-sm font-semibold cursor-pointer {{ $activeTab === 'assets' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        Objek Aset ({{ $workOrder->assets->count() }})
                    </button>

                    <button wire:click="$set('activeTab', 'reports')" type="button" class="py-4 px-1 inline-flex items-center gap-2 border-b-2 text-sm font-semibold cursor-pointer {{ $activeTab === 'reports' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        Laporan Resmi ({{ $workOrder->reports->count() }})
                    </button>

                    <button wire:click="$set('activeTab', 'documents')" type="button" class="py-4 px-1 inline-flex items-center gap-2 border-b-2 text-sm font-semibold cursor-pointer {{ $activeTab === 'documents' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        Arsip Dokumen ({{ $workOrder->documents->count() }})
                    </button>
                </nav>
            </div>

            <!-- TAB 1: INFORMASI UTAMA & TIMELINE -->
            @if($activeTab === 'info')
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700/50 space-y-4">
                            <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-2">Informasi Pekerjaan & Penawaran</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Nomor Kontrak / Penawaran</span>
                                    <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $workOrder->contract_no }}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Tanggal Kontrak</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $workOrder->contract_date ? $workOrder->contract_date->format('d M Y') : '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Nama Debitur (Objek)</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $workOrder->offer?->debtor?->name }}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Pemberi Tugas (Klien)</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $workOrder->offer?->client?->name }}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Pengguna Laporan</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $workOrder->offer?->reportUser?->name ?? $workOrder->offer?->client?->name }}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Survey Lapangan</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ $workOrder->survey_required ? 'Dibutuhkan (YA)' : 'Tidak Membutuhkan Survey' }}
                                    </span>
                                </div>
                            </div>

                            <div class="p-4 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-200 dark:border-gray-700 space-y-2 mt-4">
                                <h4 class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Ringkasan Keuangan</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-mono text-xs">
                                    <div>
                                        <span class="text-gray-400 block">Fee Total</span>
                                        <span class="font-bold text-gray-900 dark:text-white">Rp {{ number_format($workOrder->offer?->fee ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 block">DPP</span>
                                        <span class="font-bold text-gray-900 dark:text-white">Rp {{ number_format($workOrder->offer?->dpp ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 block">PPN (11%)</span>
                                        <span class="font-bold text-gray-900 dark:text-white">Rp {{ number_format($workOrder->offer?->ppn ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 block">PPh (2%)</span>
                                        <span class="font-bold text-gray-900 dark:text-white">Rp {{ number_format($workOrder->offer?->pph ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700/50 space-y-4">
                            <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-2">Timeline Audit Histori Status</h3>
                            <div class="space-y-4">
                                @forelse($workOrder->statusHistories as $history)
                                    <div class="flex gap-4 items-start">
                                        <div class="w-2.5 h-2.5 rounded-full bg-indigo-600 dark:bg-indigo-400 mt-1.5 shrink-0"></div>
                                        <div class="flex-1 text-sm bg-gray-50 dark:bg-gray-900/60 p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                            <div class="flex items-center justify-between">
                                                <div class="font-semibold text-gray-900 dark:text-white">
                                                    Status diubah ke <span class="text-indigo-600 dark:text-indigo-400 font-mono">{{ $history->to_status }}</span>
                                                    @if($history->from_status)
                                                        <span class="text-xs text-gray-400">(dari {{ $history->from_status }})</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-gray-400">{{ $history->created_at->format('d M Y, H:i') }}</div>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Oleh: {{ $history->user?->name }}</div>
                                            @if($history->note)
                                                <div class="text-xs text-gray-600 dark:text-gray-300 mt-1 italic">"{{ $history->note }}"</div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-xs text-gray-400">Belum ada riwayat perubahan status.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700/50 space-y-4">
                            <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-2">Status & SLA Overview</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Status Terbaru</span>
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400 font-mono">{{ $workOrder->current_status }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Deadline SLA</span>
                                    <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $workOrder->sla_date ? $workOrder->sla_date->format('d M Y') : '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Aging di Status Ini</span>
                                    <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $workOrder->aging_days }} hari</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700/50 space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-2">
                                <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">Penugasan PIC</h3>
                                <button wire:click="$set('showAssignModal', true)" type="button" class="text-xs text-indigo-600 hover:underline font-semibold cursor-pointer">Ubah PIC</button>
                            </div>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">Surveyor / Pelaksana Inspeksi</span>
                                    @forelse($workOrder->surveyors as $s)
                                        <div class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-teal-100 dark:bg-teal-900/60 text-teal-700 dark:text-teal-300 text-xs flex items-center justify-center font-bold">S</div>
                                            {{ $s->user?->name }}
                                        </div>
                                    @empty
                                        <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Belum diassign</span>
                                    @endforelse
                                </div>

                                <div class="pt-2 border-t border-gray-100 dark:border-gray-700/50">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">Reviewer Laporan</span>
                                    @forelse($workOrder->reviewers as $r)
                                        <div class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 text-xs flex items-center justify-center font-bold">R</div>
                                            {{ $r->user?->name }}
                                        </div>
                                    @empty
                                        <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Belum diassign</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- TAB 2: OBJEK ASET -->
            @if($activeTab === 'assets')
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700/50 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Daftar Objek Aset Penilaian</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Satu pekerjaan dapat memiliki banyak objek aset (Tanah, Bangunan, Mesin, Kendaraan, dll).</p>
                        </div>
                        <button wire:click="createAsset" type="button" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow cursor-pointer">
                            + Tambah Objek Aset
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3.5">Jenis Aset</th>
                                    <th class="px-6 py-3.5">Alamat Lokasi</th>
                                    <th class="px-6 py-3.5">Kota / Provinsi</th>
                                    <th class="px-6 py-3.5">Deskripsi</th>
                                    <th class="px-6 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($workOrder->assets as $asset)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-6 py-4 font-semibold text-indigo-600 dark:text-indigo-400 capitalize">
                                            {{ str_replace('_', ' ', $asset->asset_type) }}
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white max-w-xs">{{ $asset->address ?? '-' }}</td>
                                        <td class="px-6 py-4">{{ $asset->city ?? '-' }}, {{ $asset->province ?? '-' }}</td>
                                        <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400 max-w-xs">{{ $asset->description ?? '-' }}</td>
                                        <td class="px-6 py-4 text-right space-x-2">
                                            <button wire:click="editAsset({{ $asset->id }})" type="button" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-medium cursor-pointer">Edit</button>
                                            <button wire:confirm="Yakin ingin menghapus aset ini?" wire:click="deleteAsset({{ $asset->id }})" type="button" class="text-rose-600 hover:text-rose-900 dark:text-rose-400 font-medium cursor-pointer">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">Belum ada objek aset yang ditambahkan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- TAB 3: LAPORAN RESMI -->
            @if($activeTab === 'reports')
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700/50 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Daftar Laporan Resmi</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Penomoran laporan resmi, nilai resume, nilai laporan, dan bukti pengiriman resi.</p>
                        </div>
                        <button wire:click="createReport" type="button" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow cursor-pointer">
                            + Terbitkan Laporan Resmi
                        </button>
                    </div>

                    <div class="space-y-4">
                        @forelse($workOrder->reports as $report)
                            <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-700 pb-3">
                                    <div>
                                        <div class="font-mono font-bold text-lg text-indigo-600 dark:text-indigo-400">{{ $report->report_no }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Tgl Laporan: {{ $report->report_date->format('d M Y') }} | Tujuan: {{ $report->purpose }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button wire:click="openDeliveryModal({{ $report->id }})" type="button" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow cursor-pointer">
                                            🚚 Status / Resi Pengiriman
                                        </button>
                                        <button wire:click="editReport({{ $report->id }})" type="button" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-xs font-semibold cursor-pointer">Edit</button>
                                        <button wire:confirm="Yakin hapus laporan ini?" wire:click="deleteReport({{ $report->id }})" type="button" class="px-3 py-1.5 bg-rose-100 text-rose-700 rounded-lg text-xs font-semibold cursor-pointer">Hapus</button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs font-mono">
                                    <div class="p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                                        <span class="text-gray-400 block">Nilai Resume</span>
                                        <span class="font-bold text-gray-900 dark:text-white text-sm">Rp {{ number_format($report->resume_value, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                                        <span class="text-gray-400 block">Nilai Laporan Final</span>
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400 text-sm">Rp {{ number_format($report->report_value, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                                        <span class="text-gray-400 block">Tanggal Cetak Laporan</span>
                                        <span class="font-bold text-gray-900 dark:text-white text-sm">{{ $report->print_date ? $report->print_date->format('d M Y') : 'Belum Dicetak' }}</span>
                                    </div>
                                </div>

                                <!-- Delivery status summary if available -->
                                @if($report->delivery)
                                    <div class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-xl border border-blue-200 dark:border-blue-800 text-xs text-blue-700 dark:text-blue-300 flex items-center justify-between">
                                        <div>
                                            <strong>Pengiriman ({{ $report->delivery->courier }}):</strong> Resi {{ $report->delivery->tracking_no ?? '-' }} | Tgl Kirim: {{ $report->delivery->sent_date->format('d M Y') }}
                                        </div>
                                        <div>
                                            @if($report->delivery->received_date)
                                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded font-bold">DITERIMA ({{ $report->delivery->received_date->format('d M Y') }})</span>
                                            @else
                                                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded font-bold">DALAM PERJALANAN</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-8 text-center text-gray-500 dark:text-gray-400">Belum ada laporan resmi yang terbit.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            <!-- TAB 4: ARSIP DOKUMEN ONLINE -->
            @if($activeTab === 'documents')
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700/50 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Arsip Dokumen Online</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Penyimpanan berkas penawaran, foto survey, draft laporan, hingga PDF scan final.</p>
                        </div>
                        <button wire:click="openDocumentModal" type="button" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow cursor-pointer">
                            + Upload Dokumen
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3.5">Judul Dokumen</th>
                                    <th class="px-6 py-3.5">Kategori Berkas</th>
                                    <th class="px-6 py-3.5">Diupload Oleh</th>
                                    <th class="px-6 py-3.5">Tanggal Upload</th>
                                    <th class="px-6 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($workOrder->documents as $doc)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                            {{ $doc->title }}
                                        </td>
                                        <td class="px-6 py-4 font-mono text-xs uppercase text-indigo-600 dark:text-indigo-400">
                                            {{ str_replace('_', ' ', $doc->type) }}
                                        </td>
                                        <td class="px-6 py-4 text-xs">{{ $doc->uploader?->name }}</td>
                                        <td class="px-6 py-4 text-xs text-gray-400">{{ $doc->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-6 py-4 text-right space-x-2">
                                            <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-medium">Download</a>
                                            <button wire:confirm="Hapus dokumen ini dari arsip?" wire:click="deleteDocument({{ $doc->id }})" type="button" class="text-rose-600 hover:text-rose-900 dark:text-rose-400 font-medium">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">Belum ada dokumen yang diupload ke arsip online.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- MODAL FASE 3: ASSET -->
    @if($showAssetModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                        {{ $editingAssetId ? 'Edit Data Aset' : 'Tambah Objek Aset Baru' }}
                    </h3>
                    <button wire:click="$set('showAssetModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>

                <form wire:submit="saveAsset" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Jenis Objek Aset</label>
                        <select wire:model="asset_type" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="tanah_bangunan">Tanah & Bangunan</option>
                            <option value="tanah_kosong">Tanah Kosong</option>
                            <option value="mesin_peralatan">Mesin & Peralatan</option>
                            <option value="kendaraan">Kendaraan Operational</option>
                            <option value="inventaris">Inventaris / Lainnya</option>
                        </select>
                        @error('asset_type') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Kota / Kabupaten</label>
                            <input wire:model="asset_city" type="text" placeholder="Kota" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Provinsi</label>
                            <input wire:model="asset_province" type="text" placeholder="Provinsi" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Alamat Lengkap Aset</label>
                        <textarea wire:model="asset_address" rows="2" placeholder="Alamat detail lokasi aset..." class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Deskripsi Spesifikasi</label>
                        <textarea wire:model="asset_description" rows="2" placeholder="Luas tanah/bangunan, Merk/Tahun Mesin, dll..." class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showAssetModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium cursor-pointer">Simpan Objek Aset</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL FASE 3: REPORT -->
    @if($showReportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                        {{ $editingReportId ? 'Edit Laporan Resmi' : 'Terbitkan Laporan Resmi Baru' }}
                    </h3>
                    <button wire:click="$set('showReportModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>

                <form wire:submit="saveReport" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nomor Laporan</label>
                            <input type="text" value="{{ $report_no }}" readonly disabled class="w-full px-3 py-2 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 text-sm font-mono cursor-not-allowed">
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Mengikuti Nomor Kontrak pekerjaan ini.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tanggal Laporan</label>
                            <input wire:model="report_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            @error('report_date') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tujuan Penilaian</label>
                        <input wire:model="report_purpose" type="text" placeholder="Contoh: Penjaminan Utang / Jual Beli / Laporan Keuangan" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        @error('report_purpose') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nilai Resume (Rp)</label>
                            <input wire:model="resume_value" type="number" step="0.01" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm font-mono focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nilai Laporan Final (Rp)</label>
                            <input wire:model="report_value" type="number" step="0.01" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm font-mono focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tanggal Cetak (Opsional)</label>
                        <input wire:model="print_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showReportModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium cursor-pointer">Simpan Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL FASE 3: DELIVERY -->
    @if($showDeliveryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                        Status / Resi Pengiriman Laporan
                    </h3>
                    <button wire:click="$set('showDeliveryModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>

                <form wire:submit="saveDelivery" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tanggal Kirim</label>
                            <input wire:model="sent_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Kurir / Ekspedisi</label>
                            <input wire:model="courier" type="text" placeholder="JNE / J&T / Hand Delivery" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nomor Resi Tracking</label>
                        <input wire:model="tracking_no" type="text" placeholder="Nomor Resi Ekspedisi" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm font-mono focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tanggal Diterima</label>
                            <input wire:model="received_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nama Penerima</label>
                            <input wire:model="recipient_name" type="text" placeholder="Penerima di Klien" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showDeliveryModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium cursor-pointer">Simpan Bukti Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL FASE 3: DOCUMENT UPLOAD -->
    @if($showDocumentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                        Upload Dokumen ke Arsip Online
                    </h3>
                    <button wire:click="$set('showDocumentModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>

                <form wire:submit="uploadDocument" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Judul Dokumen</label>
                        <input wire:model="doc_title" type="text" placeholder="Contoh: Scan Laporan Final PDF" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        @error('doc_title') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Kategori Dokumen</label>
                        <select wire:model="doc_type" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="scan_final">Scan Laporan Final (PDF)</option>
                            <option value="draft_laporan">Draft Laporan</option>
                            <option value="survey">Foto / Data Survey Field</option>
                            <option value="penawaran">Surat Penawaran / Kontrak</option>
                            <option value="historis_pdf">Arsip PDF Historis</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">File Berkas (PDF, JPG, PNG, DOCX - Max 10MB)</label>
                        <input wire:model="upload_file" type="file" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm">
                        @error('upload_file') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showDocumentModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium cursor-pointer">Upload & Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL EXISTINGS: STATUS & ASSIGNMENT & SLA -->
    @if($showStatusModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Ubah / Override Status Pekerjaan</h3>
                    <button wire:click="$set('showStatusModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>
                <form wire:submit="updateStatus" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Status Baru</label>
                        <select wire:model="next_status" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm font-semibold focus:ring-2 focus:ring-indigo-500">
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
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Catatan Transisi</label>
                        <textarea wire:model="status_note" rows="3" placeholder="Masukkan catatan transisi status..." class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showStatusModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 dark:text-gray-300 rounded-lg text-sm">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showAssignModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Penugasan PIC (Surveyor & Reviewer)</h3>
                    <button wire:click="$set('showAssignModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>
                <form wire:submit="saveAssignments" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Surveyor</label>
                        <select wire:model="selected_surveyor_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm">
                            <option value="">Pilih User Surveyor</option>
                            @foreach($surveyors as $s) <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->role }})</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Reviewer</label>
                        <select wire:model="selected_reviewer_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm">
                            <option value="">Pilih User Reviewer</option>
                            @foreach($reviewers as $r) <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->role }})</option> @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showAssignModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Simpan Penugasan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showSlaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Edit SLA & Flag Survey</h3>
                    <button wire:click="$set('showSlaModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>
                <form wire:submit="saveSlaConfig" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tanggal Deadline SLA</label>
                        <input wire:model="edit_sla_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm">
                    </div>
                    <div class="flex items-center">
                        <input wire:model="edit_survey_required" id="edit_survey" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                        <label for="edit_survey" class="ms-2 text-sm text-gray-700 dark:text-gray-300 font-medium">Membutuhkan Survey Field</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showSlaModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Simpan Konfigurasi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
