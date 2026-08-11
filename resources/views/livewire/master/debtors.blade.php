<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100">
                        Master Data Debitur
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pengelolaan master data Debitur atau Objek Penilaian.</p>
                </div>
                <div>
                    <button wire:click="create" type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-medium rounded-lg shadow transition duration-150 cursor-pointer">
                        <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Debitur
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
                    <div class="relative w-full md:w-72">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama atau ID debitur..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3.5">ID / Kode Debitur</th>
                                <th class="px-6 py-3.5">Nama Debitur</th>
                                <th class="px-6 py-3.5">Alamat Objek / Kantor</th>
                                <th class="px-6 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($debtors as $debtor)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-6 py-4 font-mono text-xs text-gray-700 dark:text-gray-300">
                                        {{ $debtor->identifier ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $debtor->name }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 truncate max-w-sm">{{ $debtor->address ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <button wire:click="edit({{ $debtor->id }})" type="button" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-medium cursor-pointer">Edit</button>
                                        <button wire:confirm="Apakah Anda yakin ingin menghapus data debitur ini?" wire:click="delete({{ $debtor->id }})" type="button" class="text-rose-600 hover:text-rose-900 dark:text-rose-400 font-medium cursor-pointer">Hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">Tidak ada data debitur ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $debtors->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                        {{ $editingId ? 'Edit Data Debitur' : 'Tambah Debitur Baru' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" type="button" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Nama Debitur</label>
                        <input wire:model="name" type="text" placeholder="Contoh: PT Surya Citra Kencana" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        @error('name') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">ID / Kode Debitur (Opsional)</label>
                        <input wire:model="identifier" type="text" placeholder="Contoh: DEB-2026-001" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500">
                        @error('identifier') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Alamat Objek / Kantor</label>
                        <textarea wire:model="address" rows="3" placeholder="Alamat lokasi debitur..." class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                        @error('address') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
