<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100">
                        Manajemen Penawaran
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pencatatan penawaran jasa penilaian, kalkulasi fee/pajak, dan konversi ke pekerjaan.</p>
                </div>
                <div>
                    <button wire:click="create" type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-medium rounded-lg shadow transition duration-150 cursor-pointer">
                        <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Buat Penawaran Baru
                    </button>
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
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari No Penawaran, Debitur, Klien..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                        </div>

                        <select wire:model.live="filterOutcome" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Outcome</option>
                            <option value="DRAFT">DRAFT</option>
                            <option value="DIKIRIM">DIKIRIM</option>
                            <option value="DITERIMA">DITERIMA (Pekerjaan)</option>
                            <option value="TIDAK_LANJUT">TIDAK LANJUT</option>
                            <option value="DITOLAK">DITOLAK / BATAL</option>
                        </select>

                        <select wire:model.live="filterBranchId" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Cabang</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3.5">No Penawaran & Tgl</th>
                                <th class="px-6 py-3.5">Debitur & Pemberi Tugas</th>
                                <th class="px-6 py-3.5">Cabang</th>
                                <th class="px-6 py-3.5">Fee & DPP</th>
                                <th class="px-6 py-3.5">Outcome Status</th>
                                <th class="px-6 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($offers as $offer)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ $offer->offer_no }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $offer->offer_date->format('d M Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $offer->debtor?->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Klien: {{ $offer->client?->name }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs">{{ $offer->branch?->code }}</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs">
                                        <div class="font-semibold text-gray-900 dark:text-white">Rp {{ number_format($offer->fee, 0, ',', '.') }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">DPP: Rp {{ number_format($offer->dpp, 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @switch($offer->outcome)
                                            @case('DITERIMA')
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">DITERIMA</span>
                                                @break
                                            @case('DIKIRIM')
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">DIKIRIM</span>
                                                @break
                                            @case('TIDAK_LANJUT')
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">TIDAK LANJUT</span>
                                                @break
                                            @case('DITOLAK')
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300">DITOLAK</span>
                                                @break
                                            @default
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">DRAFT</span>
                                        @endswitch
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        @if($offer->outcome === 'DITERIMA' && $offer->workOrder)
                                            <a href="{{ route('work-orders.show', $offer->workOrder->id) }}" wire:navigate class="inline-flex items-center text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                                Lihat Job ({{ $offer->workOrder->contract_no }}) &rarr;
                                            </a>
                                        @elseif($offer->outcome !== 'TIDAK_LANJUT' && $offer->outcome !== 'DITOLAK')
                                            <button wire:click="prepareConvert({{ $offer->id }})" type="button" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold shadow cursor-pointer">
                                                + Jadikan Pekerjaan
                                            </button>
                                        @endif
                                        <button wire:click="edit({{ $offer->id }})" type="button" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-medium cursor-pointer">Edit</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">Tidak ada data penawaran ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $offers->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form Offer -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                        {{ $editingId ? 'Edit Data Penawaran' : 'Buat Penawaran Baru' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nomor Penawaran</label>
                            <input wire:model="offer_no" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm font-mono focus:ring-2 focus:ring-indigo-500">
                            @error('offer_no') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tanggal Penawaran</label>
                            <input wire:model="offer_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            @error('offer_date') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Cabang</label>
                            <select wire:model="branch_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Pilih Cabang</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Debitur (Objek)</label>
                            <select wire:model="debtor_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Pilih Debitur</option>
                                @foreach($debtors as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                            @error('debtor_id') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Pemberi Tugas (Klien)</label>
                            <select wire:model="client_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Pilih Klien</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('client_id') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Pengguna Laporan (Opsional)</label>
                            <select wire:model="report_user_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Sama dengan Klien</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('report_user_id') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Keuangan & Pajak -->
                    <div class="p-4 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-200 dark:border-gray-700 space-y-3">
                        <h4 class="text-xs font-bold uppercase text-gray-600 dark:text-gray-300">Kalkulasi Keuangan & Pajak</h4>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Fee Penawaran</label>
                                <input wire:model.live="fee" type="number" step="0.01" class="w-full px-2.5 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">TA (Operational)</label>
                                <input wire:model.live="ta" type="number" step="0.01" class="w-full px-2.5 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">DPP</label>
                                <input wire:model="dpp" type="number" step="0.01" readonly class="w-full px-2.5 py-1.5 rounded border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm font-semibold">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">PPN (11%)</label>
                                <input wire:model="ppn" type="number" step="0.01" readonly class="w-full px-2.5 py-1.5 rounded border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">PPh (2%)</label>
                                <input wire:model="pph" type="number" step="0.01" readonly class="w-full px-2.5 py-1.5 rounded border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Outcome Status Penawaran</label>
                            <select wire:model="outcome" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="DRAFT">DRAFT (Draft awal)</option>
                                <option value="DIKIRIM">DIKIRIM (Dikirim ke klien)</option>
                                <option value="DITERIMA">DITERIMA (Disetujui klien)</option>
                                <option value="TIDAK_LANJUT">TIDAK LANJUT (Tidak ada kelanjutan)</option>
                                <option value="DITOLAK">DITOLAK (Ditolak / Batal)</option>
                            </select>
                            @error('outcome') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Catatan</label>
                            <input wire:model="note" type="text" placeholder="Catatan tambahan..." class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium cursor-pointer">Simpan Penawaran</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Convert to Job Modal -->
    @if($showConvertModal && $convertingOffer)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                        Konversi Penawaran ke Pekerjaan
                    </h3>
                    <button wire:click="$set('showConvertModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>

                <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-xs space-y-1 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                    <div><strong>No Penawaran / Kontrak:</strong> {{ $convertingOffer->offer_no }}</div>
                    <div><strong>Debitur:</strong> {{ $convertingOffer->debtor?->name }}</div>
                    <div><strong>Klien:</strong> {{ $convertingOffer->client?->name }}</div>
                </div>

                <form wire:submit="convertToJob" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tanggal SLA Pekerjaan (Batas Akhir)</label>
                        <input wire:model="sla_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        @error('sla_date') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center">
                        <input wire:model="survey_required" id="survey_req" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="survey_req" class="ms-2 text-sm text-gray-700 dark:text-gray-300 font-medium">Pekerjaan Membutuhkan Survey Lapangan</label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showConvertModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium cursor-pointer">Konversi Sekarang</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
