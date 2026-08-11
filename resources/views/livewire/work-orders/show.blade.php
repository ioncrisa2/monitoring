<div>
    <div class="ui-page">
        <div class="space-y-6">
            <header class="ui-page-header">
                <div class="min-w-0">
                    <a href="{{ route('work-orders.index') }}" wire:navigate class="mb-2 inline-flex min-h-8 items-center rounded-ui-sm px-1 text-sm font-medium text-ink-muted transition hover:text-brand">
                        &larr; Daftar pekerjaan
                    </a>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="ui-page-title break-all font-mono">{{ $workOrder->contract_no }}</h1>
                        <x-status-badge :status="$workOrder->current_status" />
                        <x-sla-badge :overdue="$workOrder->is_overdue" />
                    </div>
                    <p class="ui-page-description">
                        {{ $workOrder->offer?->branch?->name ?? 'Cabang tidak tersedia' }}
                        <span aria-hidden="true" class="mx-1.5">&middot;</span>
                        {{ $workOrder->offer?->debtor?->name ?? 'Debitur tidak tersedia' }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <button wire:click="$set('showSlaModal', true)" type="button" class="ui-btn ui-btn-secondary">
                        Atur SLA & survey
                    </button>
                    <button wire:click="$set('showAssignModal', true)" type="button" class="ui-btn ui-btn-primary">
                        Atur PIC
                    </button>
                </div>
            </header>

            @if (session()->has('message'))
                <div role="status" class="flex items-center justify-between gap-4 rounded-ui border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <span>{{ session('message') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="ui-icon-btn -my-2 h-8 w-8 text-emerald-600 hover:bg-emerald-100 dark:text-emerald-400 dark:hover:bg-emerald-900/60" aria-label="Tutup pemberitahuan">&times;</button>
                </div>
            @endif

            @if (session()->has('error'))
                <div role="alert" class="flex items-center justify-between gap-4 rounded-ui border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/70 dark:bg-red-950/40 dark:text-red-300">
                    <span>{{ session('error') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="ui-icon-btn -my-2 h-8 w-8 text-red-600 hover:bg-red-100 dark:text-red-400 dark:hover:bg-red-900/60" aria-label="Tutup pesan kesalahan">&times;</button>
                </div>
            @endif

            <section class="border-y border-line py-4" aria-labelledby="workflow-heading">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <h2 id="workflow-heading" class="ui-section-heading">Alur status pekerjaan</h2>
                    <span class="text-xs text-ink-muted">Pilih tahap untuk memperbarui status</span>
                </div>
                @php
                    $statuses = $workOrder->survey_required
                        ? ['PERSIAPAN', 'SURVEY', 'PENGERJAAN', 'REVIEW', 'CETAK', 'SELESAI']
                        : ['PERSIAPAN', 'PENGERJAAN', 'REVIEW', 'CETAK', 'SELESAI'];
                    $currentIdx = array_search($workOrder->current_status, $statuses);
                @endphp
                <ol class="flex items-center overflow-x-auto pb-1" aria-label="Tahapan pekerjaan">
                    @foreach($statuses as $idx => $st)
                        @php
                            $isCurrent = $workOrder->current_status === $st;
                            $isPast = $currentIdx !== false && $idx < $currentIdx;
                        @endphp
                        <li class="flex shrink-0 items-center">
                            <button wire:click="openStatusModal('{{ $st }}')" type="button" @if($isCurrent) aria-current="step" @endif class="group flex shrink-0 items-center gap-2 rounded-ui-sm py-1 focus-visible:outline-offset-4">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full
                                    {{ $isCurrent
                                        ? 'bg-brand ring-4 ring-brand-soft'
                                        : ($isPast ? 'bg-brand' : 'border-2 border-line-strong bg-surface') }}">
                                    @if($isPast)
                                        <svg aria-hidden="true" class="h-3.5 w-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    @elseif($isCurrent)
                                        <span class="h-2 w-2 rounded-full bg-white"></span>
                                    @endif
                                </span>
                                <span class="whitespace-nowrap text-xs font-semibold {{ $isCurrent ? 'text-brand' : ($isPast ? 'text-ink-secondary' : 'text-ink-muted') }} group-hover:text-brand">{{ ucfirst(strtolower($st)) }}</span>
                            </button>
                            @if(!$loop->last)
                                <div aria-hidden="true" class="mx-2 h-px w-6 shrink-0 sm:w-10 {{ $isPast ? 'bg-brand/50' : 'bg-line' }}"></div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <div class="overflow-x-auto">
                <nav class="ui-tabs" role="tablist" aria-label="Detail pekerjaan">
                    <x-tab-button id="tab-info" wire:click="$set('activeTab', 'info')" :active="$activeTab === 'info'" aria-controls="work-order-info">
                        Informasi Utama
                    </x-tab-button>

                    <x-tab-button id="tab-assets" wire:click="$set('activeTab', 'assets')" :active="$activeTab === 'assets'" aria-controls="work-order-assets">
                        Objek Aset ({{ $workOrder->assets->count() }})
                    </x-tab-button>

                    <x-tab-button id="tab-reports" wire:click="$set('activeTab', 'reports')" :active="$activeTab === 'reports'" aria-controls="work-order-reports">
                        Laporan Resmi ({{ $workOrder->reports->count() }})
                    </x-tab-button>

                    <x-tab-button id="tab-documents" wire:click="$set('activeTab', 'documents')" :active="$activeTab === 'documents'" aria-controls="work-order-documents">
                        Arsip Dokumen ({{ $workOrder->documents->count() }})
                    </x-tab-button>
                </nav>
            </div>

            @if($activeTab === 'info')
                <div id="work-order-info" role="tabpanel" aria-labelledby="tab-info" tabindex="0" class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div class="space-y-8 lg:col-span-2">
                        <section aria-labelledby="job-information-heading">
                            <h2 id="job-information-heading" class="ui-section-heading border-b border-line pb-3">Informasi pekerjaan & penawaran</h2>
                            <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-5 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs text-ink-muted">Nomor kontrak / penawaran</dt>
                                    <dd class="mt-1 break-all font-mono font-semibold text-ink">{{ $workOrder->contract_no }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-ink-muted">Tanggal kontrak</dt>
                                    <dd class="mt-1 font-medium tabular-nums text-ink">{{ $workOrder->contract_date ? $workOrder->contract_date->format('d M Y') : 'Belum ditetapkan' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-ink-muted">Debitur (objek)</dt>
                                    <dd class="mt-1 font-medium text-ink">{{ $workOrder->offer?->debtor?->name ?? 'Tidak tersedia' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-ink-muted">Pemberi tugas (klien)</dt>
                                    <dd class="mt-1 font-medium text-ink">{{ $workOrder->offer?->client?->name ?? 'Tidak tersedia' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-ink-muted">Pengguna laporan</dt>
                                    <dd class="mt-1 font-medium text-ink">{{ $workOrder->offer?->reportUser?->name ?? $workOrder->offer?->client?->name ?? 'Tidak tersedia' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-ink-muted">Survey lapangan</dt>
                                    <dd class="mt-1 font-medium text-ink">{{ $workOrder->survey_required ? 'Dibutuhkan' : 'Tidak dibutuhkan' }}</dd>
                                </div>
                            </dl>

                            <div class="mt-6 border-t border-line pt-5">
                                <h3 class="text-sm font-semibold text-ink">Ringkasan keuangan</h3>
                                <dl class="mt-3 grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                                    <div>
                                        <dt class="text-ink-muted">Fee total</dt>
                                        <dd class="mt-1 font-semibold tabular-nums text-ink">Rp {{ number_format($workOrder->offer?->fee ?? 0, 0, ',', '.') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-ink-muted">DPP</dt>
                                        <dd class="mt-1 font-semibold tabular-nums text-ink">Rp {{ number_format($workOrder->offer?->dpp ?? 0, 0, ',', '.') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-ink-muted">PPN (11%)</dt>
                                        <dd class="mt-1 font-semibold tabular-nums text-ink">Rp {{ number_format($workOrder->offer?->ppn ?? 0, 0, ',', '.') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-ink-muted">PPh (2%)</dt>
                                        <dd class="mt-1 font-semibold tabular-nums text-ink">Rp {{ number_format($workOrder->offer?->pph ?? 0, 0, ',', '.') }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </section>

                        <section aria-labelledby="status-history-heading">
                            <h2 id="status-history-heading" class="ui-section-heading border-b border-line pb-3">Riwayat status</h2>
                            <div class="mt-5">
                                @forelse($workOrder->statusHistories as $history)
                                    <article class="relative flex gap-4 pb-6 last:pb-0">
                                        @if(!$loop->last)
                                            <div aria-hidden="true" class="absolute bottom-0 left-[5px] top-3 w-px bg-line"></div>
                                        @endif
                                        <div aria-hidden="true" class="relative z-10 mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-brand ring-4 ring-canvas"></div>
                                        <div class="min-w-0 flex-1 text-sm">
                                            <div class="flex flex-col-reverse gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="font-semibold text-ink">
                                                    Status menjadi <span class="text-brand">{{ ucfirst(strtolower($history->to_status)) }}</span>
                                                    @if($history->from_status)<span class="font-normal text-ink-muted"> dari {{ ucfirst(strtolower($history->from_status)) }}</span>@endif
                                                </div>
                                                <time class="shrink-0 whitespace-nowrap text-xs tabular-nums text-ink-muted" datetime="{{ $history->created_at->toIso8601String() }}">{{ $history->created_at->format('d M Y, H:i') }}</time>
                                            </div>
                                            <div class="mt-1 text-xs text-ink-muted">Oleh {{ $history->user?->name ?? 'Pengguna tidak tersedia' }}</div>
                                            @if($history->note)
                                                <p class="mt-1 text-sm italic leading-5 text-ink-secondary">{{ $history->note }}</p>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <div class="ui-empty-state py-8">Belum ada riwayat perubahan status.</div>
                                @endforelse
                            </div>
                        </section>
                    </div>

                    <aside class="space-y-5" aria-label="Konteks pekerjaan">
                        <section class="ui-surface p-5" aria-labelledby="sla-overview-heading">
                            <div class="flex items-center justify-between gap-3 border-b border-line pb-3">
                                <h2 id="sla-overview-heading" class="ui-section-heading">Status & SLA</h2>
                                <button wire:click="$set('showSlaModal', true)" type="button" class="ui-text-action -my-1">Ubah</button>
                            </div>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-xs text-ink-muted">Status terbaru</dt>
                                    <dd><x-status-badge :status="$workOrder->current_status" /></dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-xs text-ink-muted">Batas SLA</dt>
                                    <dd class="whitespace-nowrap font-medium tabular-nums text-ink">{{ $workOrder->sla_date ? $workOrder->sla_date->format('d M Y') : 'Belum ditetapkan' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-xs text-ink-muted">Waktu pada status</dt>
                                    <dd class="whitespace-nowrap font-medium tabular-nums text-ink">{{ $workOrder->aging_days }} hari</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="ui-surface p-5" aria-labelledby="pic-heading">
                            <div class="flex items-center justify-between gap-3 border-b border-line pb-3">
                                <h2 id="pic-heading" class="ui-section-heading">Penugasan PIC</h2>
                                <button wire:click="$set('showAssignModal', true)" type="button" class="ui-text-action -my-1">Ubah</button>
                            </div>
                            <div class="mt-4 space-y-4 text-sm">
                                <div>
                                    <div class="mb-2 text-xs text-ink-muted">Surveyor / pelaksana inspeksi</div>
                                    @forelse($workOrder->surveyors as $s)
                                        <div class="flex items-center gap-2 font-semibold text-ink">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-ui-sm bg-brand-soft text-xs font-semibold text-brand">S</span>
                                            {{ $s->user?->name ?? 'Pengguna tidak tersedia' }}
                                        </div>
                                    @empty
                                        <span class="ui-badge ui-badge-warning">Belum ditugaskan</span>
                                    @endforelse
                                </div>

                                <div class="border-t border-line pt-4">
                                    <div class="mb-2 text-xs text-ink-muted">Reviewer laporan</div>
                                    @forelse($workOrder->reviewers as $r)
                                        <div class="flex items-center gap-2 font-semibold text-ink">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-ui-sm bg-surface-muted text-xs font-semibold text-ink-secondary">R</span>
                                            {{ $r->user?->name ?? 'Pengguna tidak tersedia' }}
                                        </div>
                                    @empty
                                        <span class="ui-badge ui-badge-warning">Belum ditugaskan</span>
                                    @endforelse
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
            @endif

            <!-- TAB 2: OBJEK ASET -->
            @if($activeTab === 'assets')
                <section id="work-order-assets" role="tabpanel" aria-labelledby="tab-assets assets-heading" tabindex="0">
                    <div class="ui-toolbar mb-4">
                        <div>
                            <h2 id="assets-heading" class="ui-section-heading">Objek aset penilaian</h2>
                            <p class="ui-section-description">Data tanah, bangunan, mesin, kendaraan, dan objek lain dalam pekerjaan ini.</p>
                        </div>
                        <button wire:click="createAsset" type="button" class="ui-btn ui-btn-primary shrink-0">
                            Tambah objek aset
                        </button>
                    </div>

                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <caption class="sr-only">Daftar objek aset penilaian untuk pekerjaan {{ $workOrder->contract_no }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Jenis aset</th>
                                    <th scope="col">Alamat lokasi</th>
                                    <th scope="col">Kota / provinsi</th>
                                    <th scope="col">Deskripsi</th>
                                    <th scope="col" class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($workOrder->assets as $asset)
                                    <tr wire:key="asset-{{ $asset->id }}">
                                        <td class="font-semibold capitalize text-ink">
                                            {{ str_replace('_', ' ', $asset->asset_type) }}
                                        </td>
                                        <td class="max-w-xs font-medium text-ink">{{ $asset->address ?? 'Belum diisi' }}</td>
                                        <td>{{ $asset->city ?? '-' }}, {{ $asset->province ?? '-' }}</td>
                                        <td class="max-w-xs text-xs text-ink-muted">{{ $asset->description ?? 'Belum ada deskripsi' }}</td>
                                        <td class="whitespace-nowrap text-right">
                                            <button wire:click="editAsset({{ $asset->id }})" type="button" class="ui-text-action" aria-label="Edit aset {{ str_replace('_', ' ', $asset->asset_type) }}">Edit</button>
                                            <button wire:confirm="Yakin ingin menghapus aset ini?" wire:click="deleteAsset({{ $asset->id }})" type="button" class="ui-text-action ui-text-action-danger" aria-label="Hapus aset {{ str_replace('_', ' ', $asset->asset_type) }}">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="h-auto p-0">
                                            <div class="ui-empty-state">
                                                <div class="font-medium text-ink">Belum ada objek aset</div>
                                                <p class="mt-1 text-sm">Tambahkan objek yang akan dinilai pada pekerjaan ini.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <!-- TAB 3: LAPORAN RESMI -->
            @if($activeTab === 'reports')
                <section id="work-order-reports" role="tabpanel" aria-labelledby="tab-reports reports-heading" tabindex="0">
                    <div class="ui-toolbar">
                        <div>
                            <h2 id="reports-heading" class="ui-section-heading">Laporan resmi</h2>
                            <p class="ui-section-description">Nomor laporan, nilai penilaian, tanggal cetak, dan bukti pengiriman.</p>
                        </div>
                        <button wire:click="createReport" type="button" class="ui-btn ui-btn-primary shrink-0">
                            Terbitkan laporan
                        </button>
                    </div>

                    <div class="divide-y divide-line">
                        @forelse($workOrder->reports as $report)
                            <article wire:key="report-{{ $report->id }}" class="space-y-4 py-5 first:pt-5 last:pb-0">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <h3 class="break-all font-mono text-sm font-semibold text-ink">{{ $report->report_no }}</h3>
                                        <p class="mt-1 text-xs text-ink-muted">
                                            {{ $report->report_date->format('d M Y') }}
                                            <span aria-hidden="true" class="mx-1">&middot;</span>
                                            {{ $report->purpose }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1">
                                        <button wire:click="openDeliveryModal({{ $report->id }})" type="button" class="ui-text-action">Pengiriman</button>
                                        <button wire:click="editReport({{ $report->id }})" type="button" class="ui-text-action">Edit</button>
                                        <button wire:confirm="Yakin hapus laporan ini?" wire:click="deleteReport({{ $report->id }})" type="button" class="ui-text-action ui-text-action-danger">Hapus</button>
                                    </div>
                                </div>

                                <dl class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-3">
                                    <div>
                                        <dt class="text-ink-muted">Nilai resume</dt>
                                        <dd class="mt-1 text-sm font-semibold tabular-nums text-ink">Rp {{ number_format($report->resume_value, 0, ',', '.') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-ink-muted">Nilai laporan final</dt>
                                        <dd class="mt-1 text-sm font-semibold tabular-nums text-ink">Rp {{ number_format($report->report_value, 0, ',', '.') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-ink-muted">Tanggal cetak</dt>
                                        <dd class="mt-1 text-sm font-semibold tabular-nums text-ink">{{ $report->print_date ? $report->print_date->format('d M Y') : 'Belum dicetak' }}</dd>
                                    </div>
                                </dl>

                                @if($report->delivery)
                                    <div class="flex flex-wrap items-center gap-2 border-t border-line pt-3 text-xs text-ink-secondary">
                                        <span>{{ $report->delivery->courier }} &middot; Resi {{ $report->delivery->tracking_no ?? '-' }} &middot; Dikirim {{ $report->delivery->sent_date->format('d M Y') }}</span>
                                        @if($report->delivery->received_date)
                                            <span class="ui-badge ui-badge-success">Diterima {{ $report->delivery->received_date->format('d M Y') }}</span>
                                        @else
                                            <span class="ui-badge ui-badge-warning">Dalam perjalanan</span>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="ui-empty-state">
                                <div class="font-medium text-ink">Belum ada laporan resmi</div>
                                <p class="mt-1 text-sm">Terbitkan laporan setelah proses review selesai.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            <!-- TAB 4: ARSIP DOKUMEN ONLINE -->
            @if($activeTab === 'documents')
                <section id="work-order-documents" role="tabpanel" aria-labelledby="tab-documents documents-heading" tabindex="0">
                    <div class="ui-toolbar mb-4">
                        <div>
                            <h2 id="documents-heading" class="ui-section-heading">Arsip dokumen</h2>
                            <p class="ui-section-description">Berkas penawaran, data survey, draft laporan, dan scan laporan final.</p>
                        </div>
                        <button wire:click="openDocumentModal" type="button" class="ui-btn ui-btn-primary shrink-0">
                            Unggah dokumen
                        </button>
                    </div>

                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <caption class="sr-only">Daftar arsip dokumen untuk pekerjaan {{ $workOrder->contract_no }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Judul dokumen</th>
                                    <th scope="col">Kategori</th>
                                    <th scope="col">Diunggah oleh</th>
                                    <th scope="col">Tanggal unggah</th>
                                    <th scope="col" class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($workOrder->documents as $doc)
                                    <tr wire:key="document-{{ $doc->id }}">
                                        <td class="font-semibold text-ink">{{ $doc->title }}</td>
                                        <td class="text-xs capitalize">{{ str_replace('_', ' ', $doc->type) }}</td>
                                        <td class="text-xs">{{ $doc->uploader?->name ?? 'Pengguna tidak tersedia' }}</td>
                                        <td class="whitespace-nowrap text-xs tabular-nums text-ink-muted">{{ $doc->created_at->format('d M Y, H:i') }}</td>
                                        <td class="whitespace-nowrap text-right">
                                            <a href="{{ Storage::url($doc->file_path) }}" target="_blank" rel="noopener" class="ui-text-action" aria-label="Unduh {{ $doc->title }} di tab baru">Unduh</a>
                                            <button wire:confirm="Hapus dokumen ini dari arsip?" wire:click="deleteDocument({{ $doc->id }})" type="button" class="ui-text-action ui-text-action-danger" aria-label="Hapus {{ $doc->title }}">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="h-auto p-0">
                                            <div class="ui-empty-state">
                                                <div class="font-medium text-ink">Belum ada dokumen</div>
                                                <p class="mt-1 text-sm">Unggah dokumen untuk menyimpan arsip pekerjaan secara terpusat.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>

    <!-- MODAL FASE 3: ASSET -->
    @if($showAssetModal)
        <x-modal name="asset-editor" :show="$showAssetModal" close-property="showAssetModal" maxWidth="md" labelledby="asset-modal-title" focusable>
                <div class="ui-modal-header">
                    <h2 id="asset-modal-title" class="ui-modal-title">
                        {{ $editingAssetId ? 'Edit Data Aset' : 'Tambah Objek Aset Baru' }}
                    </h2>
                    <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form aset">&times;</button>
                </div>

                <form wire:submit="saveAsset">
                    <div class="ui-modal-body space-y-4">
                        <div>
                            <x-input-label for="asset_type" value="Jenis objek aset" />
                            <x-select-input id="asset_type" wire:model="asset_type" class="mt-1.5">
                                <option value="tanah_bangunan">Tanah & bangunan</option>
                                <option value="tanah_kosong">Tanah kosong</option>
                                <option value="mesin_peralatan">Mesin & peralatan</option>
                                <option value="kendaraan">Kendaraan operasional</option>
                                <option value="inventaris">Inventaris / lainnya</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('asset_type')" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="asset_city" value="Kota / kabupaten" />
                                <x-text-input id="asset_city" wire:model="asset_city" type="text" placeholder="Nama kota" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="asset_province" value="Provinsi" />
                                <x-text-input id="asset_province" wire:model="asset_province" type="text" placeholder="Nama provinsi" class="mt-1.5" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="asset_address" value="Alamat lengkap aset" />
                            <x-textarea-input id="asset_address" wire:model="asset_address" rows="3" placeholder="Alamat lokasi aset" class="mt-1.5"></x-textarea-input>
                        </div>

                        <div>
                            <x-input-label for="asset_description" value="Deskripsi spesifikasi" />
                            <x-textarea-input id="asset_description" wire:model="asset_description" rows="3" placeholder="Luas, merek, tahun, atau spesifikasi lainnya" class="mt-1.5"></x-textarea-input>
                        </div>
                    </div>

                    <div class="ui-modal-footer">
                        <button type="button" x-on:click="$dispatch('close')" class="ui-btn ui-btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveAsset" class="ui-btn ui-btn-primary">
                            <span wire:loading.remove wire:target="saveAsset">Simpan objek aset</span>
                            <span wire:loading wire:target="saveAsset">Menyimpan...</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif

    <!-- MODAL FASE 3: REPORT -->
    @if($showReportModal)
        <x-modal name="report-editor" :show="$showReportModal" close-property="showReportModal" maxWidth="md" labelledby="report-modal-title" focusable>
                <div class="ui-modal-header">
                    <h2 id="report-modal-title" class="ui-modal-title">
                        {{ $editingReportId ? 'Edit Laporan Resmi' : 'Terbitkan Laporan Resmi Baru' }}
                    </h2>
                    <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form laporan">&times;</button>
                </div>

                <form wire:submit="saveReport">
                    <div class="ui-modal-body space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="report_no" value="Nomor laporan" />
                                <x-text-input id="report_no" type="text" value="{{ $report_no }}" readonly disabled class="mt-1.5 font-mono" />
                                <p class="ui-help">Mengikuti nomor kontrak pekerjaan.</p>
                            </div>

                            <div>
                                <x-input-label for="report_date" value="Tanggal laporan" />
                                <x-text-input id="report_date" wire:model="report_date" type="date" class="mt-1.5" />
                                <x-input-error :messages="$errors->get('report_date')" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="report_purpose" value="Tujuan penilaian" />
                            <x-text-input id="report_purpose" wire:model="report_purpose" type="text" placeholder="Contoh: penjaminan utang atau laporan keuangan" class="mt-1.5" />
                            <x-input-error :messages="$errors->get('report_purpose')" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="resume_value" value="Nilai resume (Rp)" />
                                <x-text-input id="resume_value" wire:model="resume_value" type="number" step="0.01" class="mt-1.5 tabular-nums" />
                            </div>

                            <div>
                                <x-input-label for="report_value" value="Nilai laporan final (Rp)" />
                                <x-text-input id="report_value" wire:model="report_value" type="number" step="0.01" class="mt-1.5 tabular-nums" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="print_date" value="Tanggal cetak (opsional)" />
                            <x-text-input id="print_date" wire:model="print_date" type="date" class="mt-1.5" />
                        </div>
                    </div>

                    <div class="ui-modal-footer">
                        <button type="button" x-on:click="$dispatch('close')" class="ui-btn ui-btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveReport" class="ui-btn ui-btn-primary">
                            <span wire:loading.remove wire:target="saveReport">Simpan laporan</span>
                            <span wire:loading wire:target="saveReport">Menyimpan...</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif

    <!-- MODAL FASE 3: DELIVERY -->
    @if($showDeliveryModal)
        <x-modal name="delivery-editor" :show="$showDeliveryModal" close-property="showDeliveryModal" maxWidth="md" labelledby="delivery-modal-title" focusable>
                <div class="ui-modal-header">
                    <h2 id="delivery-modal-title" class="ui-modal-title">Status & resi pengiriman laporan</h2>
                    <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form pengiriman">&times;</button>
                </div>

                <form wire:submit="saveDelivery">
                    <div class="ui-modal-body space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="sent_date" value="Tanggal kirim" />
                                <x-text-input id="sent_date" wire:model="sent_date" type="date" class="mt-1.5" />
                            </div>

                            <div>
                                <x-input-label for="courier" value="Kurir / ekspedisi" />
                                <x-text-input id="courier" wire:model="courier" type="text" placeholder="Contoh: JNE atau hand delivery" class="mt-1.5" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="tracking_no" value="Nomor resi" />
                            <x-text-input id="tracking_no" wire:model="tracking_no" type="text" placeholder="Nomor resi ekspedisi" class="mt-1.5 font-mono" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="received_date" value="Tanggal diterima" />
                                <x-text-input id="received_date" wire:model="received_date" type="date" class="mt-1.5" />
                            </div>

                            <div>
                                <x-input-label for="recipient_name" value="Nama penerima" />
                                <x-text-input id="recipient_name" wire:model="recipient_name" type="text" placeholder="Penerima di pihak klien" class="mt-1.5" />
                            </div>
                        </div>
                    </div>

                    <div class="ui-modal-footer">
                        <button type="button" x-on:click="$dispatch('close')" class="ui-btn ui-btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveDelivery" class="ui-btn ui-btn-primary">
                            <span wire:loading.remove wire:target="saveDelivery">Simpan bukti kirim</span>
                            <span wire:loading wire:target="saveDelivery">Menyimpan...</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif

    <!-- MODAL FASE 3: DOCUMENT UPLOAD -->
    @if($showDocumentModal)
        <x-modal name="document-upload" :show="$showDocumentModal" close-property="showDocumentModal" maxWidth="md" labelledby="document-modal-title" focusable>
                <div class="ui-modal-header">
                    <h2 id="document-modal-title" class="ui-modal-title">Unggah dokumen ke arsip</h2>
                    <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form unggah dokumen">&times;</button>
                </div>

                <form wire:submit="uploadDocument">
                    <div class="ui-modal-body space-y-4">
                        <div>
                            <x-input-label for="doc_title" value="Judul dokumen" />
                            <x-text-input id="doc_title" wire:model="doc_title" type="text" placeholder="Contoh: scan laporan final" class="mt-1.5" />
                            <x-input-error :messages="$errors->get('doc_title')" />
                        </div>

                        <div>
                            <x-input-label for="doc_type" value="Kategori dokumen" />
                            <x-select-input id="doc_type" wire:model="doc_type" class="mt-1.5">
                                <option value="scan_final">Scan laporan final</option>
                                <option value="draft_laporan">Draft laporan</option>
                                <option value="survey">Foto / data survey</option>
                                <option value="penawaran">Surat penawaran / kontrak</option>
                                <option value="historis_pdf">Arsip PDF historis</option>
                                <option value="lainnya">Lainnya</option>
                            </x-select-input>
                        </div>

                        <div>
                            <x-input-label for="upload_file" value="File dokumen" />
                            <x-text-input id="upload_file" wire:model="upload_file" type="file" class="mt-1.5" />
                            <p class="ui-help">Ukuran file maksimum 10 MB.</p>
                            <x-input-error :messages="$errors->get('upload_file')" />
                        </div>
                    </div>

                    <div class="ui-modal-footer">
                        <button type="button" x-on:click="$dispatch('close')" class="ui-btn ui-btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="uploadDocument" class="ui-btn ui-btn-primary">
                            <span wire:loading.remove wire:target="uploadDocument">Unggah & simpan</span>
                            <span wire:loading wire:target="uploadDocument">Mengunggah...</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif

    <!-- MODAL EXISTINGS: STATUS & ASSIGNMENT & SLA -->
    @if($showStatusModal)
        <x-modal name="status-editor" :show="$showStatusModal" close-property="showStatusModal" maxWidth="sm" labelledby="status-modal-title" focusable>
                <div class="ui-modal-header">
                    <div>
                        <h2 id="status-modal-title" class="ui-modal-title">Ubah status pekerjaan</h2>
                        <p class="mt-1 text-sm text-ink-muted">Perubahan akan dicatat dalam riwayat status.</p>
                    </div>
                    <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form status">&times;</button>
                </div>
                <form wire:submit="updateStatus">
                    <div class="ui-modal-body space-y-4">
                        <div>
                            <x-input-label for="next_status" value="Status baru" />
                            <x-select-input id="next_status" wire:model="next_status" class="mt-1.5 font-semibold">
                                <option value="PERSIAPAN">Persiapan</option>
                                <option value="SURVEY">Survey</option>
                                <option value="PENGERJAAN">Pengerjaan</option>
                                <option value="REVIEW">Review</option>
                                <option value="CETAK">Cetak</option>
                                <option value="SELESAI">Selesai</option>
                                <option value="BATAL">Batal</option>
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label for="status_note" value="Catatan transisi" />
                            <x-textarea-input id="status_note" wire:model="status_note" rows="4" placeholder="Jelaskan alasan atau hasil perubahan status" class="mt-1.5"></x-textarea-input>
                        </div>
                    </div>
                    <div class="ui-modal-footer">
                        <button type="button" x-on:click="$dispatch('close')" class="ui-btn ui-btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="updateStatus" class="ui-btn ui-btn-primary">
                            <span wire:loading.remove wire:target="updateStatus">Simpan status</span>
                            <span wire:loading wire:target="updateStatus">Menyimpan...</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif

    @if($showAssignModal)
        <x-modal name="assignment-editor" :show="$showAssignModal" close-property="showAssignModal" maxWidth="sm" labelledby="assignment-modal-title" focusable>
                <div class="ui-modal-header">
                    <div>
                        <h2 id="assignment-modal-title" class="ui-modal-title">Penugasan PIC</h2>
                        <p class="mt-1 text-sm text-ink-muted">Pilih surveyor dan reviewer untuk pekerjaan ini.</p>
                    </div>
                    <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form penugasan">&times;</button>
                </div>
                <form wire:submit="saveAssignments">
                    <div class="ui-modal-body space-y-4">
                        <div>
                            <x-input-label for="selected_surveyor_id" value="Surveyor" />
                            <x-select-input id="selected_surveyor_id" wire:model="selected_surveyor_id" class="mt-1.5">
                                <option value="">Pilih surveyor</option>
                                @foreach($surveyors as $s) <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->role }})</option> @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label for="selected_reviewer_id" value="Reviewer" />
                            <x-select-input id="selected_reviewer_id" wire:model="selected_reviewer_id" class="mt-1.5">
                                <option value="">Pilih reviewer</option>
                                @foreach($reviewers as $r) <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->role }})</option> @endforeach
                            </x-select-input>
                        </div>
                    </div>
                    <div class="ui-modal-footer">
                        <button type="button" x-on:click="$dispatch('close')" class="ui-btn ui-btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveAssignments" class="ui-btn ui-btn-primary">
                            <span wire:loading.remove wire:target="saveAssignments">Simpan penugasan</span>
                            <span wire:loading wire:target="saveAssignments">Menyimpan...</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif

    @if($showSlaModal)
        <x-modal name="sla-editor" :show="$showSlaModal" close-property="showSlaModal" maxWidth="sm" labelledby="sla-modal-title" focusable>
                <div class="ui-modal-header">
                    <div>
                        <h2 id="sla-modal-title" class="ui-modal-title">Atur SLA & survey</h2>
                        <p class="mt-1 text-sm text-ink-muted">Tetapkan batas penyelesaian dan kebutuhan survey lapangan.</p>
                    </div>
                    <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form SLA">&times;</button>
                </div>
                <form wire:submit="saveSlaConfig">
                    <div class="ui-modal-body space-y-4">
                        <div>
                            <x-input-label for="edit_sla_date" value="Batas SLA" />
                            <x-text-input id="edit_sla_date" wire:model="edit_sla_date" type="date" class="mt-1.5" />
                        </div>
                        <label class="flex min-h-10 cursor-pointer items-center gap-3 rounded-ui border border-line px-3 py-2 text-sm font-medium text-ink-secondary">
                            <input wire:model="edit_survey_required" id="edit_survey" type="checkbox" class="rounded-ui-sm border-line-strong text-brand shadow-none focus:ring-brand">
                            <span>Membutuhkan survey lapangan</span>
                        </label>
                    </div>
                    <div class="ui-modal-footer">
                        <button type="button" x-on:click="$dispatch('close')" class="ui-btn ui-btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveSlaConfig" class="ui-btn ui-btn-primary">
                            <span wire:loading.remove wire:target="saveSlaConfig">Simpan konfigurasi</span>
                            <span wire:loading wire:target="saveSlaConfig">Menyimpan...</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
