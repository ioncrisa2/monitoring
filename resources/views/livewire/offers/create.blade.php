<div>
    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex items-center gap-3 pb-4 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ route('offers.index') }}" wire:navigate class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-semibold">&larr; Kembali</a>
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100">
                        Buat Penawaran Baru
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pencatatan penawaran jasa penilaian, kalkulasi fee/pajak, dan penomoran otomatis.</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl p-6 border border-gray-100 dark:border-gray-700/50">
                <form wire:submit="save" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nomor Urut</label>
                            <input wire:model.live="sequence_no" type="number" min="1" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Nomor urut terakhir tahun {{ \Carbon\Carbon::parse($offer_date ?: now())->year }}: {{ $this->lastSequenceForYear() }}</p>
                            @error('sequence_no') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Tanggal Penawaran</label>
                            <input wire:model.live="offer_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                            @error('offer_date') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Cabang</label>
                            <select wire:model.live="branch_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Pilih Cabang</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nomor Penawaran (Otomatis)</label>
                        <input type="text" value="{{ $offer_no ?: 'Pilih cabang & isi nomor urut untuk melihat pratinjau' }}" readonly disabled class="w-full px-3 py-2 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 text-sm font-mono cursor-not-allowed">
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
                        <a href="{{ route('offers.index') }}" wire:navigate class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium">Batal</a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium cursor-pointer">Simpan Penawaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
