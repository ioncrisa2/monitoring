<div>
    @php
        $statusLabels = [
            'draft' => 'Draft',
            'submitted' => 'Menunggu review',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'retired' => 'Dipensiunkan',
        ];
        $statusClasses = [
            'draft' => 'ui-badge-neutral',
            'submitted' => 'ui-badge-warning',
            'approved' => 'ui-badge-success',
            'rejected' => 'ui-badge-danger',
            'retired' => 'ui-badge-neutral',
        ];
        $categoryLabels = [
            'property-collateral' => 'Penjaminan utang / properti',
            'property-auction' => 'Lelang properti',
            'property-rental' => 'Nilai sewa pasar',
        ];
        $blockLabels = [
            'text' => 'Teks',
            'bullets' => 'Daftar bullet',
            'dynamic' => 'Data dinamis',
            'asset_list' => 'Daftar aset',
            'fee_summary' => 'Ringkasan fee',
            'fee_table' => 'Tabel fee per aset',
            'payment_terms' => 'Termin pembayaran',
            'requirements' => 'Persyaratan',
            'exposure_table' => 'Tabel exposure',
        ];
        $conditionLabels = [
            'has_request_reference' => 'Ada referensi permintaan',
            'has_multiple_assets' => 'Aset lebih dari satu',
            'has_special_assumptions' => 'Ada asumsi khusus',
            'tax_included' => 'Pajak termasuk',
            'tax_excluded' => 'Pajak belum termasuk',
            'fee_lump_sum' => 'Fee lump sum',
            'fee_per_asset' => 'Fee per aset',
        ];
        $sourceLabels = [
            'appraiser_status' => 'Status penilai',
            'client' => 'Pemberi tugas',
            'report_user' => 'Pengguna laporan',
            'ownership_form' => 'Bentuk kepemilikan',
            'currency' => 'Mata uang',
            'purpose' => 'Tujuan',
            'valuation_basis' => 'Dasar nilai',
            'valuation_date' => 'Tanggal penilaian',
            'investigation_level' => 'Tingkat investigasi',
            'special_assumptions' => 'Asumsi khusus',
            'report_specification' => 'Spesifikasi laporan',
            'completion_time' => 'Waktu penyelesaian',
        ];
        $effectiveLabel = static function ($master): string {
            $from = $master->effective_from?->format('d/m/Y') ?? 'Belum diatur';
            $until = $master->effective_until?->format('d/m/Y');

            return $until ? "{$from}—{$until}" : "Mulai {$from}";
        };
    @endphp

    <div class="ui-page space-y-6">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Master Dokumen Penawaran</h1>
                <p class="ui-page-description">Kelola redaksi template, identitas penerbit, dan penandatangan melalui versi yang dapat diaudit. Master yang sudah diajukan tidak dapat diubah.</p>
            </div>

            @can('offers.document-masters.manage')
                @if(!$showTemplateEditor && !$showIssuerEditor && !$showSignerEditor)
                    <x-primary-button
                        type="button"
                        wire:click="{{ $activeTab === 'templates' ? 'createTemplate' : ($activeTab === 'issuers' ? 'createIssuer' : 'createSigner') }}"
                        class="w-full sm:w-auto"
                    >
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5" />
                        </svg>
                        {{ $activeTab === 'templates' ? 'Buat template' : ($activeTab === 'issuers' ? 'Buat profil penerbit' : 'Buat penandatangan') }}
                    </x-primary-button>
                @endif
            @endcan
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        <x-input-error :messages="$errors->get('workflow')" />

        <div class="overflow-x-auto" role="tablist" aria-label="Jenis master dokumen penawaran">
            <div class="ui-tabs">
                <button
                    type="button"
                    role="tab"
                    wire:click="setTab('templates')"
                    aria-selected="{{ $activeTab === 'templates' ? 'true' : 'false' }}"
                    class="ui-tab {{ $activeTab === 'templates' ? 'ui-tab-active' : '' }}"
                >
                    Template
                    <span class="ui-badge ui-badge-neutral">{{ $templates->count() }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    wire:click="setTab('issuers')"
                    aria-selected="{{ $activeTab === 'issuers' ? 'true' : 'false' }}"
                    class="ui-tab {{ $activeTab === 'issuers' ? 'ui-tab-active' : '' }}"
                >
                    Profil penerbit
                    <span class="ui-badge ui-badge-neutral">{{ $issuers->count() }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    wire:click="setTab('signers')"
                    aria-selected="{{ $activeTab === 'signers' ? 'true' : 'false' }}"
                    class="ui-tab {{ $activeTab === 'signers' ? 'ui-tab-active' : '' }}"
                >
                    Penandatangan
                    <span class="ui-badge ui-badge-neutral">{{ $signers->count() }}</span>
                </button>
            </div>
        </div>

        @if($activeTab === 'templates')
            <section role="tabpanel" aria-labelledby="template-panel-heading">
                @if($showTemplateEditor)
                    <form wire:submit="saveTemplate" class="space-y-7">
                        <div class="ui-toolbar">
                            <div>
                                <h2 id="template-panel-heading" class="ui-section-heading">{{ $templateVersionId ? 'Edit draft template' : 'Template baru' }}</h2>
                                <p class="ui-section-description">Schema v2 menggunakan tepat 25 klausul dan hanya blok, kondisi, serta token dari daftar aman.</p>
                            </div>
                            <div class="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row">
                                <x-secondary-button type="button" wire:click="$set('showTemplateEditor', false)" class="w-full sm:w-auto">Batal</x-secondary-button>
                                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="saveTemplate" class="w-full sm:w-auto">
                                    <span wire:loading.remove wire:target="saveTemplate">Simpan draft</span>
                                    <span wire:loading wire:target="saveTemplate">Menyimpan…</span>
                                </x-primary-button>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('templateSchema')" />

                        <fieldset class="ui-surface p-4 sm:p-5">
                            <legend class="px-1 text-sm font-semibold text-ink">Identitas dan masa berlaku</legend>
                            <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <x-input-label for="template-code" value="Kode template" />
                                    <x-text-input id="template-code" wire:model="templateCode" type="text" class="mt-1 font-mono" placeholder="property-custom" :disabled="$templateId !== null" />
                                    <x-input-error :messages="$errors->get('templateCode')" />
                                </div>
                                <div class="xl:col-span-2">
                                    <x-input-label for="template-name" value="Nama template" />
                                    <x-text-input id="template-name" wire:model="templateName" type="text" class="mt-1" placeholder="Penawaran Properti Khusus" :disabled="$templateId !== null" />
                                    <x-input-error :messages="$errors->get('templateName')" />
                                </div>
                                <div>
                                    <x-input-label for="template-category" value="Kategori bisnis" />
                                    <x-select-input id="template-category" wire:model.live="templateCategory" class="mt-1" :disabled="$templateId !== null">
                                        @foreach($categoryOptions as $option)
                                            <option value="{{ $option->value }}">{{ $categoryLabels[$option->value] }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('templateCategory')" />
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="template-purpose-label" value="Deskripsi kegunaan" />
                                    <x-text-input id="template-purpose-label" wire:model="templatePurpose" type="text" class="mt-1" placeholder="Kapan template ini dipilih admin" :disabled="$templateId !== null" />
                                    <x-input-error :messages="$errors->get('templatePurpose')" />
                                </div>
                                <div>
                                    <x-input-label for="template-effective-from" value="Mulai berlaku" />
                                    <x-text-input id="template-effective-from" wire:model="templateEffectiveFrom" type="date" class="mt-1" />
                                    <x-input-error :messages="$errors->get('templateEffectiveFrom')" />
                                </div>
                                <div>
                                    <x-input-label for="template-effective-until" value="Akhir berlaku (opsional)" />
                                    <x-text-input id="template-effective-until" wire:model="templateEffectiveUntil" type="date" class="mt-1" />
                                    <x-input-error :messages="$errors->get('templateEffectiveUntil')" />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="ui-surface p-4 sm:p-5">
                            <legend class="px-1 text-sm font-semibold text-ink">Pembuka, penutup, dan default penawaran</legend>
                            <div class="mt-2 grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <x-input-label for="template-opening" value="Redaksi pembuka" />
                                    <x-textarea-input id="template-opening" wire:model="templateOpening" rows="5" class="mt-1" />
                                    <x-input-error :messages="$errors->get('templateOpening')" />
                                </div>
                                <div>
                                    <x-input-label for="template-closing" value="Redaksi penutup" />
                                    <x-textarea-input id="template-closing" wire:model="templateClosing" rows="5" class="mt-1" />
                                    <x-input-error :messages="$errors->get('templateClosing')" />
                                </div>
                                <div>
                                    <x-input-label for="default-subject" value="Perihal" />
                                    <x-text-input id="default-subject" wire:model="templateDefaults.subject" type="text" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="default-ownership" value="Bentuk kepemilikan" />
                                    <x-text-input id="default-ownership" wire:model="templateDefaults.ownership_form" type="text" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="default-purpose" value="Tujuan penilaian" />
                                    <x-text-input id="default-purpose" wire:model="templateDefaults.purpose" type="text" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="default-basis" value="Dasar nilai" />
                                    <x-text-input id="default-basis" wire:model="templateDefaults.valuation_basis" type="text" class="mt-1" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="default-currency" value="Mata uang" />
                                        <x-text-input id="default-currency" wire:model="templateDefaults.currency" type="text" maxlength="3" class="mt-1 font-mono uppercase" />
                                    </div>
                                    <div>
                                        <x-input-label for="default-copies" value="Salinan laporan" />
                                        <x-text-input id="default-copies" wire:model="templateDefaults.report_copies" type="number" min="1" max="100" class="mt-1" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="default-days" value="Durasi" />
                                        <x-text-input id="default-days" wire:model="templateDefaults.completion_days" type="number" min="1" max="365" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="default-day-type" value="Jenis hari" />
                                        <x-select-input id="default-day-type" wire:model="templateDefaults.completion_day_type" class="mt-1">
                                            <option value="business">Hari kerja</option>
                                            <option value="calendar">Hari kalender</option>
                                        </x-select-input>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <x-input-label for="default-investigation" value="Investigasi" />
                                        <x-select-input id="default-investigation" wire:model="templateDefaults.investigation_level" class="mt-1">
                                            <option value="desktop">Desktop</option>
                                            <option value="limited">Terbatas</option>
                                            <option value="full">Penuh</option>
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label for="default-report-format" value="Format laporan" />
                                        <x-select-input id="default-report-format" wire:model="templateDefaults.report_format" class="mt-1">
                                            <option value="summary">Ringkas</option>
                                            <option value="complete">Lengkap</option>
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label for="default-language" value="Bahasa" />
                                        <x-select-input id="default-language" wire:model="templateDefaults.report_language" class="mt-1">
                                            <option value="id">Indonesia</option>
                                            <option value="en">Inggris</option>
                                        </x-select-input>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="default-tax" value="Perlakuan pajak" />
                                        <x-select-input id="default-tax" wire:model="templateDefaults.tax_inclusion" class="mt-1">
                                            <option value="excluded">Belum termasuk pajak</option>
                                            <option value="included">Termasuk pajak</option>
                                            <option value="non_taxable">Tidak kena pajak</option>
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label for="default-fee-presentation" value="Presentasi fee" />
                                        <x-select-input id="default-fee-presentation" wire:model="templateDefaults.fee_presentation" class="mt-1">
                                            <option value="lump_sum">Lump sum</option>
                                            <option value="per_asset">Per aset</option>
                                        </x-select-input>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="default-ppn" value="PPN (basis point)" />
                                        <x-text-input id="default-ppn" wire:model="templateDefaults.ppn_rate_bps" type="number" min="0" max="10000" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="default-pph" value="PPh (basis point)" />
                                        <x-text-input id="default-pph" wire:model="templateDefaults.pph_rate_bps" type="number" min="0" max="10000" class="mt-1" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="default-cost-inclusions" value="Komponen biaya yang termasuk" />
                                    <x-textarea-input id="default-cost-inclusions" wire:model="templateCostInclusions" rows="4" class="mt-1" placeholder="Satu komponen per baris" />
                                    <p class="ui-help">Satu komponen per baris. Daftar boleh kosong.</p>
                                </div>
                                <div>
                                    <x-input-label for="default-special-assumptions" value="Asumsi khusus default (opsional)" />
                                    <x-textarea-input id="default-special-assumptions" wire:model="templateDefaults.special_assumptions" rows="4" class="mt-1" />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="ui-surface p-4 sm:p-5">
                            <legend class="px-1 text-sm font-semibold text-ink">Termin dan persyaratan default</legend>
                            <div class="mt-2 grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <h3 class="text-sm font-semibold text-ink">Termin pembayaran</h3>
                                            <p class="mt-1 text-xs text-ink-muted">Total persentase harus tepat 10.000 basis point (100%).</p>
                                        </div>
                                        <button type="button" wire:click="addPaymentTerm" class="ui-text-action">Tambah termin</button>
                                    </div>
                                    <div class="mt-3 divide-y divide-line border-y border-line">
                                        @foreach((array) ($templateDefaults['payment_terms'] ?? []) as $index => $term)
                                            <div wire:key="payment-term-{{ $index }}" class="grid grid-cols-1 gap-3 py-3 sm:grid-cols-[7rem_minmax(0,1fr)_7rem_auto]">
                                                <div>
                                                    <x-input-label for="payment-bps-{{ $index }}" value="Basis point" />
                                                    <x-text-input id="payment-bps-{{ $index }}" wire:model="templateDefaults.payment_terms.{{ $index }}.percentage_bps" type="number" min="1" max="10000" class="mt-1" />
                                                </div>
                                                <div>
                                                    <x-input-label for="payment-trigger-{{ $index }}" value="Pemicu pembayaran" />
                                                    <x-text-input id="payment-trigger-{{ $index }}" wire:model="templateDefaults.payment_terms.{{ $index }}.trigger_text" type="text" class="mt-1" />
                                                </div>
                                                <div>
                                                    <x-input-label for="payment-due-{{ $index }}" value="Jatuh tempo" />
                                                    <x-text-input id="payment-due-{{ $index }}" wire:model="templateDefaults.payment_terms.{{ $index }}.due_days" type="number" min="0" max="3650" class="mt-1" />
                                                </div>
                                                <button type="button" wire:click="removePaymentTerm({{ $index }})" class="ui-icon-btn self-end" aria-label="Hapus termin {{ $index + 1 }}">&times;</button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <h3 class="text-sm font-semibold text-ink">Persyaratan data</h3>
                                            <p class="mt-1 text-xs text-ink-muted">Persyaratan ini menjadi default saat template dipilih.</p>
                                        </div>
                                        <button type="button" wire:click="addRequirement" class="ui-text-action">Tambah syarat</button>
                                    </div>
                                    <div class="mt-3 divide-y divide-line border-y border-line">
                                        @foreach((array) ($templateDefaults['requirements'] ?? []) as $index => $requirement)
                                            <div wire:key="requirement-{{ $index }}" class="grid grid-cols-1 gap-3 py-3 sm:grid-cols-[7rem_minmax(0,1fr)_8rem_auto]">
                                                <div>
                                                    <x-input-label for="requirement-code-{{ $index }}" value="Kode" />
                                                    <x-text-input id="requirement-code-{{ $index }}" wire:model="templateDefaults.requirements.{{ $index }}.requirement_code" type="text" class="mt-1 font-mono" />
                                                </div>
                                                <div>
                                                    <x-input-label for="requirement-description-{{ $index }}" value="Deskripsi" />
                                                    <x-text-input id="requirement-description-{{ $index }}" wire:model="templateDefaults.requirements.{{ $index }}.description" type="text" class="mt-1" />
                                                </div>
                                                <div>
                                                    <x-input-label for="requirement-emphasis-{{ $index }}" value="Penekanan" />
                                                    <x-select-input id="requirement-emphasis-{{ $index }}" wire:model="templateDefaults.requirements.{{ $index }}.emphasis_style" class="mt-1">
                                                        <option value="normal">Normal</option>
                                                        <option value="bold">Tebal</option>
                                                        <option value="italic">Miring</option>
                                                        <option value="underline">Garis bawah</option>
                                                    </x-select-input>
                                                </div>
                                                <button type="button" wire:click="removeRequirement({{ $index }})" class="ui-icon-btn self-end" aria-label="Hapus persyaratan {{ $index + 1 }}">&times;</button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="ui-surface p-4 sm:p-5">
                            <legend class="px-1 text-sm font-semibold text-ink">Aturan wajib template</legend>
                            <p class="mt-2 max-w-3xl text-sm text-ink-secondary">Tujuan dan dasar nilai selalu dikunci ke default di atas. Semua field inti penawaran wajib diisi sebelum finalisasi.</p>
                            <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach([
                                    'required_asset_document' => 'Dokumen aset wajib',
                                    'require_fee_per_asset' => 'Fee per aset wajib',
                                    'requires_liquidation_value' => 'Nilai likuidasi wajib',
                                    'requires_exposure_table' => 'Tabel exposure wajib',
                                ] as $field => $label)
                                    <label for="constraint-{{ $field }}" class="flex min-h-11 items-center gap-3 rounded-ui-sm px-2 py-2 text-sm text-ink-secondary hover:bg-surface-subtle">
                                        <input id="constraint-{{ $field }}" wire:model="templateConstraints.{{ $field }}" type="checkbox" class="size-4 rounded border-line-strong text-brand focus:ring-brand">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <section aria-labelledby="clause-editor-heading">
                            <div class="ui-toolbar mb-3">
                                <div>
                                    <h2 id="clause-editor-heading" class="ui-section-heading">25 klausul</h2>
                                    <p class="ui-section-description">Gunakan blok terstruktur. HTML, Blade, PHP, dan token di luar whitelist akan ditolak.</p>
                                </div>
                                <span class="ui-badge ui-badge-info">{{ count($templateClauses) }} / 25</span>
                            </div>

                            <div class="divide-y divide-line border-y border-line">
                                @foreach($templateClauses as $clauseKey => $clause)
                                    <details wire:key="clause-{{ $clauseKey }}" class="group py-1">
                                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 rounded-ui-sm px-3 py-2 text-sm font-semibold text-ink transition hover:bg-surface-subtle">
                                            <span class="flex min-w-0 items-center gap-3">
                                                <span class="font-mono text-xs tabular-nums text-ink-muted">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                                <span>{{ $clause['title'] }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2 text-xs font-normal text-ink-muted">
                                                {{ count($clause['blocks'] ?? []) }} blok
                                                <svg class="size-4 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 9 6 6 6-6" /></svg>
                                            </span>
                                        </summary>

                                        <div class="space-y-4 px-3 pb-5 pt-2">
                                            @foreach((array) ($clause['blocks'] ?? []) as $blockIndex => $block)
                                                <div wire:key="clause-{{ $clauseKey }}-block-{{ $blockIndex }}" class="grid grid-cols-1 gap-3 rounded-ui bg-surface-subtle p-3 lg:grid-cols-[11rem_13rem_minmax(0,1fr)_auto]">
                                                    <div>
                                                        <x-input-label for="block-type-{{ $clauseKey }}-{{ $blockIndex }}" value="Tipe blok" />
                                                        <x-select-input id="block-type-{{ $clauseKey }}-{{ $blockIndex }}" wire:model.live="templateClauses.{{ $clauseKey }}.blocks.{{ $blockIndex }}.type" class="mt-1">
                                                            @foreach($blockTypeOptions as $option)
                                                                <option value="{{ $option->value }}">{{ $blockLabels[$option->value] }}</option>
                                                            @endforeach
                                                        </x-select-input>
                                                    </div>
                                                    <div>
                                                        <x-input-label for="block-condition-{{ $clauseKey }}-{{ $blockIndex }}" value="Kondisi (opsional)" />
                                                        <x-select-input id="block-condition-{{ $clauseKey }}-{{ $blockIndex }}" wire:model="templateClauses.{{ $clauseKey }}.blocks.{{ $blockIndex }}.when" class="mt-1">
                                                            <option value="">Selalu tampil</option>
                                                            @foreach($conditionOptions as $option)
                                                                <option value="{{ $option->value }}">{{ $conditionLabels[$option->value] }}</option>
                                                            @endforeach
                                                        </x-select-input>
                                                    </div>
                                                    <div>
                                                        @if(($block['type'] ?? '') === 'text')
                                                            <x-input-label for="block-content-{{ $clauseKey }}-{{ $blockIndex }}" value="Teks aman" />
                                                            <x-textarea-input id="block-content-{{ $clauseKey }}-{{ $blockIndex }}" wire:model="templateClauses.{{ $clauseKey }}.blocks.{{ $blockIndex }}.content" rows="3" class="mt-1" />
                                                        @elseif(($block['type'] ?? '') === 'bullets')
                                                            <x-input-label for="block-content-{{ $clauseKey }}-{{ $blockIndex }}" value="Butir daftar" />
                                                            <x-textarea-input id="block-content-{{ $clauseKey }}-{{ $blockIndex }}" wire:model="templateClauses.{{ $clauseKey }}.blocks.{{ $blockIndex }}.content" rows="3" class="mt-1" placeholder="Satu butir per baris" />
                                                        @elseif(($block['type'] ?? '') === 'dynamic')
                                                            <x-input-label for="block-source-{{ $clauseKey }}-{{ $blockIndex }}" value="Sumber data" />
                                                            <x-select-input id="block-source-{{ $clauseKey }}-{{ $blockIndex }}" wire:model="templateClauses.{{ $clauseKey }}.blocks.{{ $blockIndex }}.source" class="mt-1">
                                                                @foreach($dynamicSourceOptions as $option)
                                                                    <option value="{{ $option->value }}">{{ $sourceLabels[$option->value] }}</option>
                                                                @endforeach
                                                            </x-select-input>
                                                        @else
                                                            <span class="ui-label">Sumber</span>
                                                            <p class="mt-2 text-sm leading-5 text-ink-secondary">Data dibentuk dari record penawaran dan tidak menerima isi bebas.</p>
                                                        @endif
                                                        <x-input-error :messages="$errors->get('templateClauses.'.$clauseKey.'.blocks.'.$blockIndex.'.content')" />
                                                    </div>
                                                    <button type="button" wire:click="removeTemplateBlock('{{ $clauseKey }}', {{ $blockIndex }})" class="ui-icon-btn self-end" aria-label="Hapus blok {{ $blockIndex + 1 }} dari klausul {{ $clause['title'] }}">&times;</button>
                                                </div>
                                            @endforeach

                                            <button type="button" wire:click="addTemplateBlock('{{ $clauseKey }}')" class="ui-text-action">Tambah blok</button>
                                            <x-input-error :messages="$errors->get('templateClauses.'.$clauseKey)" />
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </section>

                        <div class="flex flex-col-reverse justify-end gap-2 border-t border-line pt-5 sm:flex-row">
                            <x-secondary-button type="button" wire:click="$set('showTemplateEditor', false)" class="w-full sm:w-auto">Batal</x-secondary-button>
                            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="saveTemplate" class="w-full sm:w-auto">
                                <span wire:loading.remove wire:target="saveTemplate">Simpan draft template</span>
                                <span wire:loading wire:target="saveTemplate">Menyimpan…</span>
                            </x-primary-button>
                        </div>
                    </form>
                @else
                    <div class="ui-toolbar mb-4">
                        <div>
                            <h2 id="template-panel-heading" class="ui-section-heading">Katalog versi template</h2>
                            <p class="ui-section-description">Versi approved bersifat immutable. Perubahan selalu dimulai dengan salinan draft baru.</p>
                        </div>
                    </div>

                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <caption class="sr-only">Daftar versi template dokumen penawaran</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Template</th>
                                    <th scope="col">Kategori</th>
                                    <th scope="col">Berlaku</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Jejak review</th>
                                    <th scope="col" class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($templates as $version)
                                    @php $category = $version->template->category instanceof \App\Enums\OfferTemplateCategory ? $version->template->category->value : $version->template->category; @endphp
                                    <tr wire:key="template-version-{{ $version->id }}">
                                        <td>
                                            <div class="font-medium text-ink">{{ $version->template->name }} <span class="font-mono text-xs text-ink-muted">v{{ $version->version_no }}</span></div>
                                            <div class="mt-1 font-mono text-xs text-ink-muted">{{ $version->template->code }} · schema {{ $version->schema_version }}</div>
                                        </td>
                                        <td>
                                            <div class="text-sm text-ink-secondary">{{ $categoryLabels[$category] ?? $category }}</div>
                                            <div class="mt-1 max-w-xs whitespace-normal text-xs text-ink-muted">{{ $version->template->purpose }}</div>
                                        </td>
                                        <td class="whitespace-nowrap text-sm">{{ $effectiveLabel($version) }}</td>
                                        <td><span class="ui-badge {{ $statusClasses[$version->status] ?? 'ui-badge-neutral' }}">{{ $statusLabels[$version->status] ?? $version->status }}</span></td>
                                        <td>
                                            <div class="text-xs leading-5 text-ink-muted">
                                                <div>Pembuat: {{ $version->creator?->name ?? 'Sistem' }}</div>
                                                @if($version->reviewer)<div>Reviewer: {{ $version->reviewer->name }}</div>@endif
                                                @if($version->rejection_note)<div class="max-w-xs whitespace-normal text-red-700 dark:text-red-400">{{ $version->rejection_note }}</div>@endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap justify-end gap-1">
                                                <button type="button" wire:click="previewTemplate({{ $version->id }})" class="ui-text-action">Pratinjau fixture</button>
                                                @can('offers.document-masters.manage')
                                                    <button type="button" wire:click="copyTemplate({{ $version->id }})" class="ui-text-action">Salin versi</button>
                                                    @if($version->status === 'draft')
                                                        <button type="button" wire:click="editTemplate({{ $version->id }})" class="ui-text-action">Edit</button>
                                                        <button type="button" wire:confirm="Ajukan versi ini? Konten akan dikunci selama proses review." wire:click="submitMaster('template', {{ $version->id }})" class="ui-text-action">Ajukan</button>
                                                    @endif
                                                @endcan
                                                @can('offers.document-masters.approve')
                                                    @if($version->status === 'submitted')
                                                        <button type="button" wire:confirm="Setujui master template ini?" wire:click="approveMaster('template', {{ $version->id }})" class="ui-text-action">Setujui</button>
                                                        <button type="button" wire:click="openReviewDialog('reject', 'template', {{ $version->id }})" class="ui-text-action ui-text-action-danger">Tolak</button>
                                                    @elseif($version->status === 'approved')
                                                        <button type="button" wire:click="openReviewDialog('retire', 'template', {{ $version->id }})" class="ui-text-action ui-text-action-danger">Retire</button>
                                                    @endif
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="ui-empty-state">Belum ada template. Buat draft pertama atau jalankan seeder katalog template v2.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @elseif($activeTab === 'issuers')
            <section role="tabpanel" aria-labelledby="issuer-panel-heading">
                <div class="ui-toolbar mb-4">
                    <div>
                        <h2 id="issuer-panel-heading" class="ui-section-heading">Versi profil penerbit</h2>
                        <p class="ui-section-description">Letterhead disimpan privat dan diverifikasi ulang dari hash, MIME, ukuran, serta dimensi sebelum approval.</p>
                    </div>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Daftar versi profil penerbit dan letterhead resmi</caption>
                        <thead><tr><th scope="col">Cabang / versi</th><th scope="col">Identitas penerbit</th><th scope="col">Letterhead</th><th scope="col">Berlaku</th><th scope="col">Status</th><th scope="col" class="text-right">Aksi</th></tr></thead>
                        <tbody>
                            @forelse($issuers as $version)
                                <tr wire:key="issuer-version-{{ $version->id }}">
                                    <td><div class="font-medium text-ink">{{ $version->branch->name }}</div><div class="mt-1 font-mono text-xs text-ink-muted">v{{ $version->version_no }}</div></td>
                                    <td><div class="font-medium text-ink">{{ $version->legal_name }}</div><div class="mt-1 max-w-sm whitespace-normal text-xs text-ink-muted">{{ $version->office_label ?: $version->city }} · {{ $version->permit_no ?: 'Izin belum diisi' }}</div></td>
                                    <td>
                                        @if($version->letterhead_path)
                                            <span class="ui-badge ui-badge-success">Terverifikasi saat submit</span>
                                            <div class="mt-1 font-mono text-xs text-ink-muted">{{ $version->letterhead_width_px }}×{{ $version->letterhead_height_px }} px · {{ number_format(((int) $version->letterhead_size_bytes) / 1024, 0, ',', '.') }} KB</div>
                                        @else
                                            <span class="ui-badge ui-badge-warning">Belum diunggah</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-sm">{{ $effectiveLabel($version) }}</td>
                                    <td><span class="ui-badge {{ $statusClasses[$version->status] ?? 'ui-badge-neutral' }}">{{ $statusLabels[$version->status] ?? $version->status }}</span></td>
                                    <td><div class="flex flex-wrap justify-end gap-1">
                                        @can('offers.document-masters.manage')
                                            <button type="button" wire:click="copyIssuer({{ $version->id }})" class="ui-text-action">Salin versi</button>
                                            @if($version->status === 'draft')
                                                <button type="button" wire:click="editIssuer({{ $version->id }})" class="ui-text-action">Edit</button>
                                                <button type="button" wire:confirm="Ajukan profil penerbit ini untuk review?" wire:click="submitMaster('issuer', {{ $version->id }})" class="ui-text-action">Ajukan</button>
                                            @endif
                                        @endcan
                                        @can('offers.document-masters.approve')
                                            @if($version->status === 'submitted')
                                                <button type="button" wire:confirm="Setujui profil penerbit ini?" wire:click="approveMaster('issuer', {{ $version->id }})" class="ui-text-action">Setujui</button>
                                                <button type="button" wire:click="openReviewDialog('reject', 'issuer', {{ $version->id }})" class="ui-text-action ui-text-action-danger">Tolak</button>
                                            @elseif($version->status === 'approved')
                                                <button type="button" wire:click="openReviewDialog('retire', 'issuer', {{ $version->id }})" class="ui-text-action ui-text-action-danger">Retire</button>
                                            @endif
                                        @endcan
                                    </div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="ui-empty-state">Belum ada profil penerbit. Tambahkan identitas cabang dan letterhead resmi untuk memulai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section role="tabpanel" aria-labelledby="signer-panel-heading">
                <div class="ui-toolbar mb-4">
                    <div>
                        <h2 id="signer-panel-heading" class="ui-section-heading">Versi penandatangan</h2>
                        <p class="ui-section-description">Hanya identitas, jabatan, izin, dan registrasi. Tanda tangan serta stempel tetap diberikan secara basah.</p>
                    </div>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Daftar versi penandatangan dokumen penawaran</caption>
                        <thead><tr><th scope="col">Penandatangan</th><th scope="col">Cabang</th><th scope="col">Izin / registrasi</th><th scope="col">Berlaku</th><th scope="col">Status</th><th scope="col" class="text-right">Aksi</th></tr></thead>
                        <tbody>
                            @forelse($signers as $version)
                                <tr wire:key="signer-version-{{ $version->id }}">
                                    <td><div class="font-medium text-ink">{{ $version->full_name }}{{ $version->title_suffix ? ', '.$version->title_suffix : '' }}</div><div class="mt-1 text-xs text-ink-muted">{{ $version->position }} · <span class="font-mono">{{ $version->signer_key }} v{{ $version->version_no }}</span></div></td>
                                    <td>{{ $version->branch->name }}</td>
                                    <td><div class="text-sm text-ink-secondary">{{ $version->permit_no ?: '—' }}</div><div class="mt-1 text-xs text-ink-muted">{{ $version->registration_no ?: 'Registrasi belum diisi' }}</div></td>
                                    <td class="whitespace-nowrap text-sm">{{ $effectiveLabel($version) }}</td>
                                    <td><span class="ui-badge {{ $statusClasses[$version->status] ?? 'ui-badge-neutral' }}">{{ $statusLabels[$version->status] ?? $version->status }}</span></td>
                                    <td><div class="flex flex-wrap justify-end gap-1">
                                        @can('offers.document-masters.manage')
                                            <button type="button" wire:click="copySigner({{ $version->id }})" class="ui-text-action">Salin versi</button>
                                            @if($version->status === 'draft')
                                                <button type="button" wire:click="editSigner({{ $version->id }})" class="ui-text-action">Edit</button>
                                                <button type="button" wire:confirm="Ajukan penandatangan ini untuk review?" wire:click="submitMaster('signer', {{ $version->id }})" class="ui-text-action">Ajukan</button>
                                            @endif
                                        @endcan
                                        @can('offers.document-masters.approve')
                                            @if($version->status === 'submitted')
                                                <button type="button" wire:confirm="Setujui penandatangan ini?" wire:click="approveMaster('signer', {{ $version->id }})" class="ui-text-action">Setujui</button>
                                                <button type="button" wire:click="openReviewDialog('reject', 'signer', {{ $version->id }})" class="ui-text-action ui-text-action-danger">Tolak</button>
                                            @elseif($version->status === 'approved')
                                                <button type="button" wire:click="openReviewDialog('retire', 'signer', {{ $version->id }})" class="ui-text-action ui-text-action-danger">Retire</button>
                                            @endif
                                        @endcan
                                    </div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="ui-empty-state">Belum ada penandatangan. Tambahkan identitas pejabat yang akan menandatangani dokumen cetak.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    @if($showIssuerEditor)
        <x-modal name="issuer-editor" :show="$showIssuerEditor" close-property="showIssuerEditor" maxWidth="2xl" labelledby="issuer-editor-title" focusable>
            <div class="ui-modal-header">
                <div>
                    <h2 id="issuer-editor-title" class="ui-modal-title">{{ $issuerVersionId ? 'Edit profil penerbit' : 'Profil penerbit baru' }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">Simpan versi draft sebelum mengajukannya ke Supervisor.</p>
                </div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup profil penerbit">&times;</button>
            </div>
            <form wire:submit="saveIssuer">
                <div class="ui-modal-body space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="issuer-branch" value="Cabang" />
                            <x-select-input id="issuer-branch" wire:model="issuerBranchId" class="mt-1" :disabled="$issuerVersionId !== null">
                                <option value="">Pilih cabang</option>
                                @foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('issuerBranchId')" />
                        </div>
                        <div>
                            <x-input-label for="issuer-legal-name" value="Nama legal penerbit" />
                            <x-text-input id="issuer-legal-name" wire:model="issuerLegalName" type="text" class="mt-1" />
                            <x-input-error :messages="$errors->get('issuerLegalName')" />
                        </div>
                        <div>
                            <x-input-label for="issuer-office-label" value="Label kantor" />
                            <x-text-input id="issuer-office-label" wire:model="issuerOfficeLabel" type="text" class="mt-1" placeholder="Kantor Pusat / Cabang Bandung" />
                        </div>
                        <div>
                            <x-input-label for="issuer-permit" value="Nomor izin" />
                            <x-text-input id="issuer-permit" wire:model="issuerPermitNo" type="text" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="issuer-address" value="Alamat" />
                        <x-textarea-input id="issuer-address" wire:model="issuerAddress" rows="3" class="mt-1" />
                        <x-input-error :messages="$errors->get('issuerAddress')" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><x-input-label for="issuer-city" value="Kota" /><x-text-input id="issuer-city" wire:model="issuerCity" type="text" class="mt-1" /><x-input-error :messages="$errors->get('issuerCity')" /></div>
                        <div><x-input-label for="issuer-phone" value="Telepon" /><x-text-input id="issuer-phone" wire:model="issuerPhone" type="tel" class="mt-1" /></div>
                        <div><x-input-label for="issuer-email" value="Email" /><x-text-input id="issuer-email" wire:model="issuerEmail" type="email" class="mt-1" /><x-input-error :messages="$errors->get('issuerEmail')" /></div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><x-input-label for="issuer-effective-from" value="Mulai berlaku" /><x-text-input id="issuer-effective-from" wire:model="issuerEffectiveFrom" type="date" class="mt-1" /><x-input-error :messages="$errors->get('issuerEffectiveFrom')" /></div>
                        <div><x-input-label for="issuer-effective-until" value="Akhir berlaku (opsional)" /><x-text-input id="issuer-effective-until" wire:model="issuerEffectiveUntil" type="date" class="mt-1" /><x-input-error :messages="$errors->get('issuerEffectiveUntil')" /></div>
                    </div>
                    <div class="rounded-ui bg-surface-subtle p-4">
                        <x-input-label for="issuer-letterhead" value="Letterhead resmi" />
                        <input id="issuer-letterhead" wire:model="letterheadUpload" type="file" accept="image/png,image/jpeg" class="mt-2 block w-full text-sm text-ink-secondary file:mr-3 file:rounded-ui-sm file:border-0 file:bg-brand-soft file:px-3 file:py-2 file:font-semibold file:text-brand hover:file:bg-surface-muted">
                        <p class="ui-help">PNG/JPEG, maksimum 10 MB, lebar 300–10.000 px dan tinggi 50–5.000 px. File disimpan privat; gambar dari PDF contoh tidak digunakan.</p>
                        @if($issuerExistingLetterhead)<p class="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-400">Versi ini sudah memiliki letterhead. Unggah file baru hanya jika perlu menggantinya sebelum submit.</p>@endif
                        <p wire:loading wire:target="letterheadUpload" class="ui-help" role="status">Memeriksa file…</p>
                        <x-input-error :messages="$errors->get('letterheadUpload')" />
                    </div>
                </div>
                <div class="ui-modal-footer"><x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button><x-primary-button type="submit" wire:loading.attr="disabled" wire:target="saveIssuer,letterheadUpload"><span wire:loading.remove wire:target="saveIssuer">Simpan draft</span><span wire:loading wire:target="saveIssuer">Menyimpan…</span></x-primary-button></div>
            </form>
        </x-modal>
    @endif

    @if($showSignerEditor)
        <x-modal name="signer-editor" :show="$showSignerEditor" close-property="showSignerEditor" maxWidth="2xl" labelledby="signer-editor-title" focusable>
            <div class="ui-modal-header">
                <div><h2 id="signer-editor-title" class="ui-modal-title">{{ $signerVersionId ? 'Edit penandatangan' : 'Penandatangan baru' }}</h2><p class="mt-1 text-sm text-ink-muted">Identitas teks saja; tidak ada unggahan tanda tangan atau stempel.</p></div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup penandatangan">&times;</button>
            </div>
            <form wire:submit="saveSigner">
                <div class="ui-modal-body space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><x-input-label for="signer-branch" value="Cabang" /><x-select-input id="signer-branch" wire:model="signerBranchId" class="mt-1" :disabled="$signerVersionId !== null"><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-select-input><x-input-error :messages="$errors->get('signerBranchId')" /></div>
                        <div><x-input-label for="signer-key" value="Kode penandatangan" /><x-text-input id="signer-key" wire:model="signerKey" type="text" class="mt-1 font-mono" placeholder="pimpinan-rekan" :disabled="$signerVersionId !== null" /><x-input-error :messages="$errors->get('signerKey')" /></div>
                        <div><x-input-label for="signer-full-name" value="Nama lengkap" /><x-text-input id="signer-full-name" wire:model="signerFullName" type="text" class="mt-1" /><x-input-error :messages="$errors->get('signerFullName')" /></div>
                        <div><x-input-label for="signer-title-suffix" value="Gelar (opsional)" /><x-text-input id="signer-title-suffix" wire:model="signerTitleSuffix" type="text" class="mt-1" /></div>
                        <div><x-input-label for="signer-position" value="Jabatan" /><x-text-input id="signer-position" wire:model="signerPosition" type="text" class="mt-1" /><x-input-error :messages="$errors->get('signerPosition')" /></div>
                        <div><x-input-label for="signer-permit" value="Nomor izin" /><x-text-input id="signer-permit" wire:model="signerPermitNo" type="text" class="mt-1" /></div>
                        <div><x-input-label for="signer-registration" value="Nomor registrasi" /><x-text-input id="signer-registration" wire:model="signerRegistrationNo" type="text" class="mt-1" /></div>
                        <div><x-input-label for="signer-phone" value="Telepon" /><x-text-input id="signer-phone" wire:model="signerPhone" type="tel" class="mt-1" /></div>
                        <div><x-input-label for="signer-email" value="Email" /><x-text-input id="signer-email" wire:model="signerEmail" type="email" class="mt-1" /><x-input-error :messages="$errors->get('signerEmail')" /></div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><x-input-label for="signer-effective-from" value="Mulai berlaku" /><x-text-input id="signer-effective-from" wire:model="signerEffectiveFrom" type="date" class="mt-1" /><x-input-error :messages="$errors->get('signerEffectiveFrom')" /></div>
                        <div><x-input-label for="signer-effective-until" value="Akhir berlaku (opsional)" /><x-text-input id="signer-effective-until" wire:model="signerEffectiveUntil" type="date" class="mt-1" /><x-input-error :messages="$errors->get('signerEffectiveUntil')" /></div>
                    </div>
                    <div class="rounded-ui bg-brand-soft px-4 py-3 text-sm leading-6 text-ink-secondary" role="note">PDF final menyediakan area tanda tangan dan stempel kosong untuk proses basah. Sistem tidak menyimpan gambar keduanya.</div>
                </div>
                <div class="ui-modal-footer"><x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button><x-primary-button type="submit" wire:loading.attr="disabled" wire:target="saveSigner"><span wire:loading.remove wire:target="saveSigner">Simpan draft</span><span wire:loading wire:target="saveSigner">Menyimpan…</span></x-primary-button></div>
            </form>
        </x-modal>
    @endif

    @if($showReviewDialog)
        <x-modal name="master-review-dialog" :show="$showReviewDialog" close-property="showReviewDialog" maxWidth="sm" labelledby="master-review-title" focusable>
            <div class="ui-modal-header">
                <div><h2 id="master-review-title" class="ui-modal-title">{{ $reviewAction === 'reject' ? 'Tolak master' : 'Retire master' }}</h2><p class="mt-1 text-sm text-ink-muted">Catatan tersimpan dalam jejak review dan tidak dapat diubah.</p></div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup dialog review">&times;</button>
            </div>
            <form wire:submit="confirmReviewAction">
                <div class="ui-modal-body">
                    <x-input-label for="review-note" :value="$reviewAction === 'reject' ? 'Alasan penolakan' : 'Alasan retirement'" />
                    <x-textarea-input id="review-note" wire:model="reviewNote" rows="5" class="mt-1" />
                    <x-input-error :messages="$errors->get('reviewNote')" />
                    <x-input-error :messages="$errors->get('workflow')" />
                </div>
                <div class="ui-modal-footer"><x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button><x-danger-button type="submit" wire:loading.attr="disabled" wire:target="confirmReviewAction">Konfirmasi</x-danger-button></div>
            </form>
        </x-modal>
    @endif

    @if($showPreview)
        <x-modal name="template-fixture-preview" :show="$showPreview" close-property="showPreview" maxWidth="2xl" labelledby="template-preview-title">
            <div class="ui-modal-header">
                <div><h2 id="template-preview-title" class="ui-modal-title">{{ $previewTitle }}</h2><p class="mt-1 text-sm text-ink-muted">Data berikut sepenuhnya anonim dan hanya memeriksa urutan serta jenis blok.</p></div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup pratinjau fixture">&times;</button>
            </div>
            <div class="ui-modal-body max-h-[75vh] overflow-y-auto">
                <article class="mx-auto max-w-[44rem] text-sm leading-7 text-ink-secondary">
                    <p class="text-ink">{{ $previewOpening }}</p>
                    <ol class="mt-5 space-y-4">
                        @foreach($previewClauses as $clause)
                            <li>
                                <h3 class="font-semibold text-ink">{{ $loop->iteration }}. {{ $clause['title'] }}</h3>
                                <div class="mt-1 space-y-2">
                                    @forelse($clause['blocks'] as $block)
                                        <div>
                                            <span class="ui-badge ui-badge-neutral">{{ $block['type'] }}</span>
                                            @if($block['condition'])<span class="ml-1 text-xs text-ink-muted">{{ $block['condition'] }}</span>@endif
                                            @foreach($block['lines'] as $line)<p class="mt-1">{{ $line }}</p>@endforeach
                                        </div>
                                    @empty
                                        <p class="text-red-700 dark:text-red-400">Klausul belum memiliki blok.</p>
                                    @endforelse
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-6 text-ink">{{ $previewClosing }}</p>
                </article>
            </div>
            <div class="ui-modal-footer"><x-secondary-button type="button" x-on:click="$dispatch('close')">Tutup</x-secondary-button></div>
        </x-modal>
    @endif
</div>
