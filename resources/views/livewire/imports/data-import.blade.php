<div>
    <div class="ui-page space-y-8">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Impor Data</h1>
                <p class="ui-page-description">Unggah rekap produksi CSV atau TXT ke staging, tinjau hasilnya, lalu konversi ke database utama.</p>
            </div>

            <x-secondary-button
                type="button"
                wire:click="downloadTemplate"
                wire:loading.attr="disabled"
                wire:target="downloadTemplate"
                class="w-full sm:w-auto"
            >
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" />
                </svg>
                <span wire:loading.remove wire:target="downloadTemplate">Unduh template CSV</span>
                <span wire:loading wire:target="downloadTemplate">Menyiapkan…</span>
            </x-secondary-button>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        @if(session()->has('error'))
            <x-flash-message type="error">{{ session('error') }}</x-flash-message>
        @endif

        <section aria-labelledby="import-upload-heading">
            <div class="mb-4 flex items-start gap-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand" aria-hidden="true">1</span>
                <div>
                    <h2 id="import-upload-heading" class="ui-section-heading">Unggah berkas</h2>
                    <p class="ui-section-description">Pilih cabang fallback dan berkas CSV/TXT berukuran maksimal 10 MB.</p>
                </div>
            </div>

            <form wire:submit="uploadFile" class="grid grid-cols-1 gap-4 border-b border-line pb-6 md:grid-cols-[minmax(12rem,1fr)_minmax(0,2fr)] md:items-end">
                <div>
                    <x-input-label for="import-default-branch" value="Cabang fallback" />
                    <x-select-input
                        id="import-default-branch"
                        wire:model="default_branch_code"
                        class="mt-1"
                        aria-describedby="import-default-branch-help import-default-branch-error"
                        aria-invalid="{{ $errors->has('default_branch_code') ? 'true' : 'false' }}"
                    >
                        @foreach($branches as $branch)
                            <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                        @endforeach
                    </x-select-input>
                    <p id="import-default-branch-help" class="ui-help">Digunakan ketika kode cabang pada baris impor kosong.</p>
                    <x-input-error id="import-default-branch-error" :messages="$errors->get('default_branch_code')" />
                </div>

                <div>
                    <x-input-label for="import-file" value="Berkas CSV atau TXT" />
                    <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                        <input
                            id="import-file"
                            wire:model="upload_file"
                            type="file"
                            accept=".csv,.txt,text/csv,text/plain"
                            class="ui-field min-w-0 flex-1 file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-semibold file:text-brand"
                            aria-describedby="import-file-help import-file-error"
                            aria-invalid="{{ $errors->has('upload_file') ? 'true' : 'false' }}"
                        >
                        <x-primary-button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="uploadFile,upload_file"
                            class="shrink-0 sm:self-start"
                        >
                            <span wire:loading.remove wire:target="uploadFile">Unggah ke staging</span>
                            <span wire:loading wire:target="uploadFile">Mengunggah…</span>
                        </x-primary-button>
                    </div>
                    <p id="import-file-help" class="ui-help">Kolom berkas harus mengikuti format pada template.</p>
                    <x-input-error id="import-file-error" :messages="$errors->get('upload_file')" />
                    <p wire:loading wire:target="upload_file" class="ui-help" role="status">Membaca berkas yang dipilih…</p>
                </div>
            </form>
        </section>

        @if($currentBatchId)
            <section aria-labelledby="import-staging-heading">
                <div class="ui-toolbar mb-4">
                    <div class="flex items-start gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand" aria-hidden="true">2</span>
                        <div>
                            <h2 id="import-staging-heading" class="ui-section-heading">Tinjau data staging</h2>
                            <p class="ui-section-description">
                                <span class="tabular-nums">{{ $totalStaging }}</span> baris ditemukan; <span class="tabular-nums">{{ $unprocessedCount }}</span> belum diproses.
                            </p>
                        </div>
                    </div>

                    <div class="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row">
                        <x-secondary-button
                            type="button"
                            wire:click="clearStaging"
                            wire:loading.attr="disabled"
                            wire:target="clearStaging"
                            class="w-full sm:w-auto"
                        >
                            <span wire:loading.remove wire:target="clearStaging">Bersihkan staging</span>
                            <span wire:loading wire:target="clearStaging">Membersihkan…</span>
                        </x-secondary-button>

                        @if($unprocessedCount > 0)
                            <x-primary-button
                                type="button"
                                wire:click="processBatch"
                                wire:loading.attr="disabled"
                                wire:target="processBatch"
                                class="w-full sm:w-auto"
                            >
                                <span wire:loading.remove wire:target="processBatch">Proses ke database</span>
                                <span wire:loading wire:target="processBatch">Memproses…</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Pratinjau data staging sebelum diproses ke database utama</caption>
                        <thead>
                            <tr>
                                <th scope="col">Nomor penawaran</th>
                                <th scope="col">Cabang</th>
                                <th scope="col">Debitur dan klien</th>
                                <th scope="col" class="text-right">Fee</th>
                                <th scope="col">Nomor laporan</th>
                                <th scope="col" class="text-right">Nilai laporan</th>
                                <th scope="col">Status proses</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stagingItems as $item)
                                <tr wire:key="staging-row-{{ $item->id }}">
                                    <td>
                                        <div class="font-mono text-sm font-semibold text-ink">{{ $item->offer_no }}</div>
                                        <div class="mt-1 text-xs text-ink-muted tabular-nums">{{ $item->contract_date ? $item->contract_date->format('d M Y') : '-' }}</div>
                                    </td>
                                    <td><span class="font-mono text-xs text-ink-secondary">{{ $item->branch_code }}</span></td>
                                    <td>
                                        <div class="font-medium text-ink">{{ $item->debtor_name }}</div>
                                        <div class="mt-1 text-xs text-ink-muted">{{ $item->client_name }}</div>
                                    </td>
                                    <td class="text-right font-semibold text-ink tabular-nums">Rp {{ number_format($item->fee, 0, ',', '.') }}</td>
                                    <td><span class="font-mono text-xs text-ink-secondary">{{ $item->report_no ?: '-' }}</span></td>
                                    <td class="text-right font-semibold text-ink tabular-nums">Rp {{ number_format($item->report_value, 0, ',', '.') }}</td>
                                    <td>
                                        @if($item->error_message)
                                            <span class="ui-badge ui-badge-danger">Gagal</span>
                                            <p class="mt-1.5 max-w-xs whitespace-normal text-xs leading-5 text-rose-700 dark:text-rose-400">{{ $item->error_message }}</p>
                                        @elseif($item->is_processed)
                                            <span class="ui-badge ui-badge-success">Selesai</span>
                                        @else
                                            <span class="ui-badge ui-badge-warning">Siap diproses</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="ui-empty-state">Batch ini belum memiliki data staging.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($stagingItems->hasPages())
                    <div class="mt-4">{{ $stagingItems->links() }}</div>
                @endif
            </section>
        @endif
    </div>
</div>
