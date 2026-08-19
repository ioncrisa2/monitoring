<div>
    @php
        $engagement = $draft['engagement'] ?? [];
        $subjects = $draft['subjects'] ?? [];
        $feeItems = $draft['fee_items'] ?? [];
        $paymentTerms = $draft['payment_terms'] ?? [];
        $requirements = $draft['requirements'] ?? [];
        $feeTotal = collect($feeItems)->sum(fn ($item) => (float) ($item['quantity'] ?? 0) * (int) ($item['unit_amount'] ?? 0));
        $termTotal = collect($paymentTerms)->sum(fn ($term) => (int) ($term['percentage_bps'] ?? 0)) / 100;
        $assetCount = collect($subjects)->sum(fn ($subject) => count($subject['assets'] ?? []));
        $assetOptions = collect($subjects)->flatMap(function ($subject, $subjectIndex) {
            return collect($subject['assets'] ?? [])->map(function ($asset, $assetIndex) use ($subject, $subjectIndex) {
                return [
                    'id' => $asset['id'] ?? null,
                    'label' => ($subject['name_snapshot'] ?? 'Pihak '.($subjectIndex + 1)).' · '.(($asset['description'] ?? '') ?: 'Aset '.($assetIndex + 1)),
                ];
            });
        })->filter(fn ($asset) => filled($asset['id']))->values();
        $selectedTemplate = $templateVersions->firstWhere('id', (int) ($engagement['template_version_id'] ?? 0));
        $selectedCategory = $selectedTemplate?->template?->category;
        $selectedCategory = $selectedCategory instanceof \BackedEnum ? $selectedCategory->value : $selectedCategory;
    @endphp

    <div class="ui-page space-y-6">
        <nav aria-label="Breadcrumb">
            <a href="{{ route('offers.index') }}" wire:navigate class="ui-text-action -ml-2">
                <span aria-hidden="true">&larr;</span>
                Penawaran
            </a>
        </nav>

        <header class="ui-page-header">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="ui-badge {{ $workflowState === 'finalized' ? 'ui-badge-success' : ($workflowState === 'in_review' || $workflowState === 'approved' ? 'ui-badge-info' : 'ui-badge-neutral') }}">
                        {{ match($workflowState) {
                            'in_review' => 'Dalam review',
                            'approved' => 'Disetujui',
                            'finalized' => 'Final',
                            default => 'Draft dokumen',
                        } }}
                    </span>
                    <span class="font-mono text-xs text-ink-muted">{{ $offer->offer_no }}</span>
                </div>
                <h1 class="ui-page-title">Dokumen Penawaran</h1>
                <p class="ui-page-description">Lengkapi data surat, objek, biaya, dan persyaratan sebelum membuat pratinjau PDF.</p>
            </div>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        @if(!$domainReady)
            <div class="ui-surface-subtle border-l-4 border-amber-500 px-4 py-3 text-sm text-ink-secondary" role="status">
                Editor dapat ditinjau, tetapi penyimpanan menunggu fondasi domain dokumen penawaran tersedia.
            </div>
        @endif

        @if(!$canManage)
            <div class="ui-surface-subtle px-4 py-3 text-sm text-ink-secondary" role="status">
                Anda memiliki akses baca. Perubahan hanya dapat disimpan oleh pengguna dengan hak kelola draft.
            </div>
        @endif

        @if($errors->any())
            <div class="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300" role="alert" aria-labelledby="document-validation-heading">
                <h2 id="document-validation-heading" class="font-semibold">Periksa kembali data berikut</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form wire:submit="saveDraft">
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <fieldset class="min-w-0 space-y-10" @disabled(!$canManage)>
                    <legend class="sr-only">Data draft dokumen penawaran</legend>

                    <section aria-labelledby="document-master-heading">
                        <div class="mb-4">
                            <h2 id="document-master-heading" class="ui-section-heading">1. Pilih template penawaran</h2>
                            <p class="ui-section-description">Template menentukan tujuan, dasar nilai, SLA, pola fee, termin, persyaratan, dan blok khusus. Hanya versi approved, aktif, efektif, dan lolos checksum yang tampil.</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            @forelse($templateVersions->filter(fn ($version) => (int) $version->schema_version === 2 && $version->layout_version === 'offer-a4-v2') as $version)
                                @php
                                    $category = $version->template?->category;
                                    $category = $category instanceof \BackedEnum ? $category->value : $category;
                                    $defaults = data_get($version->clause_schema, 'defaults', []);
                                    $isSelected = (int) ($engagement['template_version_id'] ?? 0) === (int) $version->id;
                                    $categoryLabel = match($category) {
                                        'property_auction', 'property-auction' => 'Lelang properti',
                                        'property_rental', 'property-rental' => 'Nilai sewa',
                                        default => 'Penjaminan utang',
                                    };
                                    $feature = match($category) {
                                        'property_auction', 'property-auction' => 'Pasar + likuidasi · exposure · fee per aset',
                                        'property_rental', 'property-rental' => 'Nilai Sewa Pasar · transaksi sewa',
                                        default => 'Satu atau banyak aset · fee lumpsum',
                                    };
                                @endphp
                                <button
                                    type="button"
                                    wire:key="offer-template-card-{{ $version->id }}"
                                    wire:click="selectTemplate({{ $version->id }})"
                                    @if(!$isSelected && filled($engagement['template_version_id'] ?? null))
                                        wire:confirm="Ganti template? Penerima, pihak, aset, dokumen, nilai fee, dan catatan internal dipertahankan. Default lainnya akan diterapkan ulang."
                                    @endif
                                    class="border p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-brand {{ $isSelected ? 'border-brand bg-brand/5' : 'border-line bg-surface hover:border-line-strong' }}"
                                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                >
                                    <span class="ui-badge {{ $isSelected ? 'ui-badge-info' : 'ui-badge-neutral' }}">{{ $isSelected ? 'Dipilih' : $categoryLabel }}</span>
                                    <span class="mt-3 block font-semibold text-ink">{{ $version->template?->name }}</span>
                                    <span class="mt-1 block text-sm leading-5 text-ink-secondary">{{ data_get($defaults, 'purpose', $version->template?->purpose) }}</span>
                                    <span class="mt-3 block text-xs leading-5 text-ink-muted">{{ data_get($defaults, 'valuation_basis', 'Dasar nilai mengikuti template') }} · {{ $feature }} · v{{ $version->version_no }}</span>
                                </button>
                            @empty
                                <div class="ui-surface-subtle p-4 text-sm text-ink-secondary md:col-span-3">
                                    Belum ada template v2 yang approved dan efektif. Sysadmin perlu menyiapkan master, lalu Supervisor menyetujuinya.
                                </div>
                            @endforelse
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            <div>
                                <x-input-label for="document-template-version" value="Template terpilih" />
                                <x-select-input id="document-template-version" wire:model="draft.engagement.template_version_id" class="mt-1" disabled>
                                    <option value="">Pilih template approved</option>
                                    @foreach($templateVersions as $version)
                                        <option value="{{ $version->id }}">{{ $version->template?->name }} — v{{ $version->version_no }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label for="document-issuer-profile" value="Profil penerbit" />
                                <x-select-input id="document-issuer-profile" wire:model="draft.engagement.issuer_profile_version_id" class="mt-1">
                                    <option value="">Pilih profil approved</option>
                                    @foreach($issuerProfiles as $profile)
                                        <option value="{{ $profile->id }}">{{ $profile->legal_name }} — v{{ $profile->version_no }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label for="document-signer-version" value="Penandatangan" />
                                <x-select-input id="document-signer-version" wire:model="draft.engagement.signer_version_id" class="mt-1">
                                    <option value="">Pilih penandatangan approved</option>
                                    @foreach($signerVersions as $signer)
                                        <option value="{{ $signer->id }}">{{ $signer->full_name }} — {{ $signer->position }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="document-recipient-heading">
                        <div class="mb-4">
                            <h2 id="document-recipient-heading" class="ui-section-heading">2. Penerima dan referensi</h2>
                            <p class="ui-section-description">Informasi ini tampil pada bagian pembuka surat penawaran.</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label for="document-issue-city" value="Kota penerbit" />
                                <x-text-input id="document-issue-city" wire:model="draft.engagement.issue_city" class="mt-1" placeholder="Jakarta" />
                            </div>
                            <div>
                                <x-input-label for="document-recipient-attention" value="Ditujukan kepada (opsional)" />
                                <x-text-input id="document-recipient-attention" wire:model="draft.engagement.recipient_attention" class="mt-1" placeholder="Nama atau divisi penerima" />
                            </div>
                            <div>
                                <x-input-label for="document-recipient-organization" value="Organisasi penerima" />
                                <x-text-input id="document-recipient-organization" wire:model="draft.engagement.recipient_organization" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="document-recipient-city" value="Kota tujuan" />
                                <x-text-input id="document-recipient-city" wire:model="draft.engagement.recipient_city" class="mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="document-recipient-address" value="Alamat penerima" />
                                <x-textarea-input id="document-recipient-address" wire:model="draft.engagement.recipient_address" rows="3" class="mt-1"></x-textarea-input>
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="document-subject" value="Perihal" />
                                <x-text-input id="document-subject" wire:model="draft.engagement.subject" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="document-reference-type" value="Sumber permintaan" />
                                <x-select-input id="document-reference-type" wire:model="draft.engagement.request_reference_type" class="mt-1">
                                    <option value="none">Tanpa referensi</option>
                                    <option value="letter">Surat</option>
                                    <option value="email">Email</option>
                                    <option value="verbal">Lisan</option>
                                    <option value="other">Lainnya</option>
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label for="document-reference-number" value="Nomor referensi (opsional)" />
                                <x-text-input id="document-reference-number" wire:model="draft.engagement.request_reference_no" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="document-reference-date" value="Tanggal referensi (opsional)" />
                                <x-text-input id="document-reference-date" wire:model="draft.engagement.request_reference_date" type="date" class="mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="document-opening-context" value="Konteks pembuka (opsional)" />
                                <x-textarea-input id="document-opening-context" wire:model="draft.engagement.opening_context" rows="3" class="mt-1" placeholder="Keterangan tambahan tentang permintaan penilaian"></x-textarea-input>
                                <p class="ui-help">Hanya digunakan pada DRAF. PDF siap cetak selalu memakai pembuka dari template approved.</p>
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-line pt-8" aria-labelledby="document-scope-heading">
                        <div class="mb-4">
                                <h2 id="document-scope-heading" class="ui-section-heading">3. Lingkup dan keluaran</h2>
                            <p class="ui-section-description">Satu sumber data untuk tujuan, dasar nilai, bentuk laporan, dan durasi pekerjaan.</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <x-input-label for="document-ownership-form" value="Bentuk kepemilikan" />
                                <x-text-input id="document-ownership-form" wire:model="draft.engagement.ownership_form" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="document-currency" value="Mata uang" />
                                <x-select-input id="document-currency" wire:model="draft.engagement.currency" class="mt-1">
                                    <option value="IDR">IDR - Rupiah</option>
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label for="document-valuation-date" value="Tanggal penilaian" />
                                <x-text-input id="document-valuation-date" wire:model="draft.engagement.valuation_date" type="date" class="mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="document-purpose" value="Maksud dan tujuan penilaian" />
                                <x-text-input id="document-purpose" wire:model="draft.engagement.purpose" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="document-valuation-basis" value="Dasar nilai" />
                                <x-text-input id="document-valuation-basis" wire:model="draft.engagement.valuation_basis" class="mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="document-valuation-rule" value="Aturan tanggal penilaian" />
                                <x-text-input id="document-valuation-rule" wire:model="draft.engagement.valuation_date_rule" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="document-investigation-level" value="Tingkat investigasi" />
                                <x-select-input id="document-investigation-level" wire:model="draft.engagement.investigation_level" class="mt-1">
                                    <option value="desktop">Kajian dokumen</option>
                                    <option value="limited">Investigasi terbatas</option>
                                    <option value="full">Inspeksi penuh</option>
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label for="document-report-format" value="Bentuk laporan" />
                                <x-select-input id="document-report-format" wire:model="draft.engagement.report_format" class="mt-1">
                                    <option value="summary">Ringkas</option>
                                    <option value="complete">Lengkap</option>
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label for="document-report-language" value="Bahasa laporan" />
                                <x-select-input id="document-report-language" wire:model="draft.engagement.report_language" class="mt-1">
                                    <option value="id">Indonesia</option>
                                    <option value="en">Inggris</option>
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label for="document-report-copies" value="Jumlah eksemplar" />
                                <x-text-input id="document-report-copies" wire:model="draft.engagement.report_copies" type="number" min="1" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="document-completion-days" value="Durasi" />
                                <x-text-input id="document-completion-days" wire:model="draft.engagement.completion_days" type="number" min="1" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="document-completion-day-type" value="Jenis hari" />
                                <x-select-input id="document-completion-day-type" wire:model="draft.engagement.completion_day_type" class="mt-1">
                                    <option value="business">Hari kerja</option>
                                    <option value="calendar">Hari kalender</option>
                                </x-select-input>
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-line pt-8" aria-labelledby="document-subjects-heading">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 id="document-subjects-heading" class="ui-section-heading">4. Pihak dan objek penilaian</h2>
                                <p class="ui-section-description">Setiap objek berada di bawah pihak yang benar agar uraian dan biaya tetap konsisten.</p>
                            </div>
                            <x-secondary-button type="button" wire:click="addSubject">Tambah pihak</x-secondary-button>
                        </div>

                        <div class="space-y-6">
                            @foreach($subjects as $subjectIndex => $subject)
                                <article class="border border-line p-4 sm:p-5" wire:key="document-subject-{{ $subjectIndex }}">
                                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-semibold text-ink">Pihak {{ $subjectIndex + 1 }}</h3>
                                            @if($subject['is_primary'] ?? false)
                                                <span class="ui-badge ui-badge-info">Utama</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            @if(!($subject['is_primary'] ?? false))
                                                <button type="button" wire:click="setPrimarySubject({{ $subjectIndex }})" class="ui-text-action">Jadikan utama</button>
                                            @endif
                                            <button type="button" wire:click="removeSubject({{ $subjectIndex }})" class="ui-text-action-danger">Hapus pihak</button>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <x-input-label for="document-subject-name-{{ $subjectIndex }}" value="Nama pihak/debitur" />
                                            <x-text-input id="document-subject-name-{{ $subjectIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.name_snapshot" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label for="document-subject-identifier-{{ $subjectIndex }}" value="Nomor identitas (opsional)" />
                                            <x-text-input id="document-subject-identifier-{{ $subjectIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.identifier_snapshot" class="mt-1" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <x-input-label for="document-subject-address-{{ $subjectIndex }}" value="Alamat pihak" />
                                            <x-textarea-input id="document-subject-address-{{ $subjectIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.address_snapshot" rows="2" class="mt-1"></x-textarea-input>
                                        </div>
                                    </div>

                                    <div class="mt-6 border-t border-line pt-5">
                                        <div class="mb-4 flex items-center justify-between gap-3">
                                            <h4 class="text-sm font-semibold text-ink">Objek milik pihak ini</h4>
                                            <button type="button" wire:click="addAsset({{ $subjectIndex }})" class="ui-text-action">Tambah aset</button>
                                        </div>

                                        <div class="space-y-5">
                                            @forelse($subject['assets'] ?? [] as $assetIndex => $asset)
                                                <div class="ui-surface-subtle p-4" wire:key="document-asset-{{ $subjectIndex }}-{{ $assetIndex }}">
                                                    <div class="mb-3 flex items-center justify-between gap-3">
                                                        <h5 class="text-sm font-semibold text-ink">Aset {{ $assetIndex + 1 }}</h5>
                                                        <button type="button" wire:click="removeAsset({{ $subjectIndex }}, {{ $assetIndex }})" class="ui-text-action-danger">Hapus aset</button>
                                                    </div>

                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <div>
                                                            <x-input-label for="document-asset-type-{{ $subjectIndex }}-{{ $assetIndex }}" value="Jenis aset" />
                                                            <x-select-input id="document-asset-type-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.asset_type" class="mt-1">
                                                                <option value="tanah">Tanah</option>
                                                                <option value="bangunan">Bangunan</option>
                                                                <option value="mesin">Mesin/peralatan</option>
                                                                <option value="kendaraan">Kendaraan</option>
                                                                <option value="inventaris">Inventaris</option>
                                                                <option value="lainnya">Lainnya</option>
                                                            </x-select-input>
                                                        </div>
                                                        <div>
                                                            <x-input-label for="document-asset-description-{{ $subjectIndex }}-{{ $assetIndex }}" value="Uraian objek" />
                                                            <x-text-input id="document-asset-description-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.description" class="mt-1" />
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <x-input-label for="document-asset-address-{{ $subjectIndex }}-{{ $assetIndex }}" value="Alamat objek" />
                                                            <x-textarea-input id="document-asset-address-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.address" rows="2" class="mt-1"></x-textarea-input>
                                                        </div>
                                                        <div>
                                                            <x-input-label for="document-asset-city-{{ $subjectIndex }}-{{ $assetIndex }}" value="Kota" />
                                                            <x-text-input id="document-asset-city-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.city" class="mt-1" />
                                                        </div>
                                                        <div>
                                                            <x-input-label for="document-asset-province-{{ $subjectIndex }}-{{ $assetIndex }}" value="Provinsi" />
                                                            <x-text-input id="document-asset-province-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.province" class="mt-1" />
                                                        </div>
                                                        <div>
                                                            <x-input-label for="document-land-area-{{ $subjectIndex }}-{{ $assetIndex }}" value="Luas tanah (m²)" />
                                                            <x-text-input id="document-land-area-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.land_area_m2" type="number" min="0" step="0.01" class="mt-1" />
                                                        </div>
                                                        <div>
                                                            <x-input-label for="document-building-area-{{ $subjectIndex }}-{{ $assetIndex }}" value="Luas bangunan (m²)" />
                                                            <x-text-input id="document-building-area-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.building_area_m2" type="number" min="0" step="0.01" class="mt-1" />
                                                        </div>
                                                        @if(in_array($selectedCategory, ['property_auction', 'property-auction'], true))
                                                            <div class="md:col-span-2 mt-2 border-t border-line pt-4">
                                                                <p class="text-sm font-semibold text-ink">Data exposure dan indikasi nilai lelang</p>
                                                                <p class="ui-help">Wajib lengkap untuk setiap aset; tabel PDF mengambil data dari record aset yang sama.</p>
                                                            </div>
                                                            <div>
                                                                <x-input-label for="document-exposure-{{ $subjectIndex }}-{{ $assetIndex }}" value="Exposure (Rp)" />
                                                                <x-text-input id="document-exposure-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.exposure_amount" type="number" min="0" step="1" class="mt-1" />
                                                            </div>
                                                            <div>
                                                                <x-input-label for="document-market-value-{{ $subjectIndex }}-{{ $assetIndex }}" value="Referensi Nilai Pasar (Rp)" />
                                                                <x-text-input id="document-market-value-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.reference_market_value" type="number" min="0" step="1" class="mt-1" />
                                                            </div>
                                                            <div>
                                                                <x-input-label for="document-liquidation-value-{{ $subjectIndex }}-{{ $assetIndex }}" value="Referensi Nilai Likuidasi (Rp)" />
                                                                <x-text-input id="document-liquidation-value-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.reference_liquidation_value" type="number" min="0" step="1" class="mt-1" />
                                                            </div>
                                                            <div>
                                                                <x-input-label for="document-liquidation-discount-{{ $subjectIndex }}-{{ $assetIndex }}" value="Diskon likuidasi (bps)" />
                                                                <x-text-input id="document-liquidation-discount-{{ $subjectIndex }}-{{ $assetIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.liquidation_discount_bps" type="number" min="0" max="10000" step="1" class="mt-1" />
                                                                <p class="ui-help">3000 bps = 30%</p>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="mt-5 border-t border-line pt-4">
                                                        <div class="mb-3 flex items-center justify-between gap-3">
                                                            <h6 class="text-sm font-semibold text-ink">Dokumen kepemilikan</h6>
                                                            <button type="button" wire:click="addAssetDocument({{ $subjectIndex }}, {{ $assetIndex }})" class="ui-text-action">Tambah dokumen</button>
                                                        </div>

                                                        <div class="space-y-3">
                                                            @forelse($asset['documents'] ?? [] as $documentIndex => $document)
                                                                <div class="grid gap-3 border-t border-line pt-3 first:border-t-0 first:pt-0 md:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_auto]" wire:key="asset-document-{{ $subjectIndex }}-{{ $assetIndex }}-{{ $documentIndex }}">
                                                                    <div>
                                                                        <x-input-label for="asset-document-type-{{ $subjectIndex }}-{{ $assetIndex }}-{{ $documentIndex }}" value="Jenis dokumen" />
                                                                        <x-text-input id="asset-document-type-{{ $subjectIndex }}-{{ $assetIndex }}-{{ $documentIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.documents.{{ $documentIndex }}.document_type" class="mt-1" />
                                                                    </div>
                                                                    <div>
                                                                        <x-input-label for="asset-document-no-{{ $subjectIndex }}-{{ $assetIndex }}-{{ $documentIndex }}" value="Nomor dokumen" />
                                                                        <x-text-input id="asset-document-no-{{ $subjectIndex }}-{{ $assetIndex }}-{{ $documentIndex }}" wire:model="draft.subjects.{{ $subjectIndex }}.assets.{{ $assetIndex }}.documents.{{ $documentIndex }}.document_no" class="mt-1" />
                                                                    </div>
                                                                    <div class="flex items-end gap-1 pb-1">
                                                                        @if(!($document['is_primary'] ?? false))
                                                                            <button type="button" wire:click="setPrimaryAssetDocument({{ $subjectIndex }}, {{ $assetIndex }}, {{ $documentIndex }})" class="ui-text-action">Utama</button>
                                                                        @endif
                                                                        <button type="button" wire:click="removeAssetDocument({{ $subjectIndex }}, {{ $assetIndex }}, {{ $documentIndex }})" class="ui-text-action-danger" aria-label="Hapus dokumen kepemilikan {{ $documentIndex + 1 }}">Hapus</button>
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <p class="ui-help">Belum ada dokumen kepemilikan untuk aset ini.</p>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="ui-empty-state">Belum ada aset untuk pihak ini.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    <section class="border-t border-line pt-8" aria-labelledby="document-commercial-heading">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 id="document-commercial-heading" class="ui-section-heading">5. Biaya, pajak, dan termin</h2>
                                <p class="ui-section-description">Nilai dokumen dihitung ulang oleh service domain dan disimpan sebagai snapshot.</p>
                            </div>
                            <x-secondary-button type="button" wire:click="addFeeItem">Tambah komponen biaya</x-secondary-button>
                        </div>

                        <div class="mb-5 max-w-sm">
                            <x-input-label for="document-fee-presentation" value="Penyajian fee" />
                            <x-select-input id="document-fee-presentation" wire:model.live="draft.engagement.fee_presentation" class="mt-1">
                                <option value="lump_sum">Lumpsum</option>
                                <option value="per_asset">Per aset</option>
                            </x-select-input>
                            <p class="ui-help">Template lelang mewajibkan satu item fee untuk setiap aset.</p>
                        </div>

                        <div class="space-y-3">
                            @foreach($feeItems as $feeIndex => $feeItem)
                                <div class="grid items-end gap-3 border-b border-line pb-4 {{ ($engagement['fee_presentation'] ?? 'lump_sum') === 'per_asset' ? 'md:grid-cols-[minmax(0,1.2fr)_minmax(0,1.3fr)_6rem_minmax(0,1fr)_auto]' : 'md:grid-cols-[minmax(0,1.5fr)_7rem_minmax(0,1fr)_auto]' }}" wire:key="document-fee-{{ $feeIndex }}">
                                    @if(($engagement['fee_presentation'] ?? 'lump_sum') === 'per_asset')
                                        <div>
                                            <x-input-label for="document-fee-asset-{{ $feeIndex }}" value="Aset" />
                                            <x-select-input id="document-fee-asset-{{ $feeIndex }}" wire:model="draft.fee_items.{{ $feeIndex }}.offer_asset_id" class="mt-1">
                                                <option value="">Pilih aset</option>
                                                @foreach($assetOptions as $assetOption)
                                                    <option value="{{ $assetOption['id'] }}">{{ $assetOption['label'] }}</option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    @endif
                                    <div>
                                        <x-input-label for="document-fee-label-{{ $feeIndex }}" value="Komponen" />
                                        <x-text-input id="document-fee-label-{{ $feeIndex }}" wire:model="draft.fee_items.{{ $feeIndex }}.label" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="document-fee-quantity-{{ $feeIndex }}" value="Jumlah" />
                                        <x-text-input id="document-fee-quantity-{{ $feeIndex }}" wire:model="draft.fee_items.{{ $feeIndex }}.quantity" type="number" min="1" step="1" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="document-fee-amount-{{ $feeIndex }}" value="Nilai satuan (Rp)" />
                                        <x-text-input id="document-fee-amount-{{ $feeIndex }}" wire:model="draft.fee_items.{{ $feeIndex }}.unit_amount" type="number" min="0" step="1" class="mt-1" />
                                    </div>
                                    <button type="button" wire:click="removeFeeItem({{ $feeIndex }})" class="ui-text-action-danger mb-1">Hapus</button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            <div>
                                <x-input-label for="document-tax-inclusion" value="Perlakuan PPN" />
                                <x-select-input id="document-tax-inclusion" wire:model="draft.engagement.tax_inclusion" class="mt-1">
                                    <option value="excluded">Belum termasuk PPN</option>
                                    <option value="included">Sudah termasuk PPN</option>
                                    <option value="non_taxable">Tidak dikenakan PPN</option>
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label for="document-ppn-rate" value="Tarif PPN (bps)" />
                                <x-text-input id="document-ppn-rate" wire:model="draft.engagement.ppn_rate_bps" type="number" min="0" step="1" class="mt-1" />
                                <p class="ui-help">1100 bps = 11%</p>
                            </div>
                            <div>
                                <x-input-label for="document-pph-rate" value="Tarif PPh (bps, opsional)" />
                                <x-text-input id="document-pph-rate" wire:model="draft.engagement.pph_rate_bps" type="number" min="0" step="1" class="mt-1" />
                            </div>
                        </div>

                        <fieldset class="mt-5">
                            <legend class="text-sm font-medium text-ink">Biaya yang sudah termasuk</legend>
                            <div class="mt-2 flex flex-wrap gap-5 text-sm text-ink-secondary">
                                <label class="flex items-center gap-2">
                                    <input wire:model="draft.engagement.cost_inclusions" type="checkbox" value="transportasi" class="size-4 rounded border-line-strong text-brand focus:ring-brand">
                                    Transportasi
                                </label>
                                <label class="flex items-center gap-2">
                                    <input wire:model="draft.engagement.cost_inclusions" type="checkbox" value="akomodasi" class="size-4 rounded border-line-strong text-brand focus:ring-brand">
                                    Akomodasi
                                </label>
                            </div>
                        </fieldset>

                        <div class="mt-6 flex items-end justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-ink">Termin pembayaran</h3>
                                <p class="ui-help">Kosongkan bila dokumen tidak memerlukan pembagian termin.</p>
                            </div>
                            <button type="button" wire:click="addPaymentTerm" class="ui-text-action">Tambah termin</button>
                        </div>

                        <div class="mt-3 space-y-3">
                            @foreach($paymentTerms as $termIndex => $term)
                                <div class="grid items-end gap-3 md:grid-cols-[7rem_minmax(0,1fr)_8rem_auto]" wire:key="document-term-{{ $termIndex }}">
                                    <div>
                                        <x-input-label for="document-term-percent-{{ $termIndex }}" value="Persen (bps)" />
                                        <x-text-input id="document-term-percent-{{ $termIndex }}" wire:model="draft.payment_terms.{{ $termIndex }}.percentage_bps" type="number" min="0" max="10000" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="document-term-trigger-{{ $termIndex }}" value="Pemicu pembayaran" />
                                        <x-text-input id="document-term-trigger-{{ $termIndex }}" wire:model="draft.payment_terms.{{ $termIndex }}.trigger_text" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="document-term-due-{{ $termIndex }}" value="Jatuh tempo" />
                                        <x-text-input id="document-term-due-{{ $termIndex }}" wire:model="draft.payment_terms.{{ $termIndex }}.due_days" type="number" min="0" class="mt-1" />
                                    </div>
                                    <button type="button" wire:click="removePaymentTerm({{ $termIndex }})" class="ui-text-action-danger mb-1">Hapus</button>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="border-t border-line pt-8" aria-labelledby="document-requirements-heading">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 id="document-requirements-heading" class="ui-section-heading">6. Persyaratan dan catatan</h2>
                                <p class="ui-section-description">Daftar data awal masuk ke klausul permintaan data; catatan internal tidak dicetak.</p>
                            </div>
                            <x-secondary-button type="button" wire:click="addRequirement">Tambah persyaratan</x-secondary-button>
                        </div>

                        <div class="space-y-3">
                            @foreach($requirements as $requirementIndex => $requirement)
                                <div class="grid items-end gap-3 md:grid-cols-[minmax(0,1fr)_9rem_auto]" wire:key="document-requirement-{{ $requirementIndex }}">
                                    <div>
                                        <x-input-label for="document-requirement-{{ $requirementIndex }}" value="Deskripsi persyaratan" />
                                        <x-text-input id="document-requirement-{{ $requirementIndex }}" wire:model="draft.requirements.{{ $requirementIndex }}.description_snapshot" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="document-requirement-style-{{ $requirementIndex }}" value="Penekanan" />
                                        <x-select-input id="document-requirement-style-{{ $requirementIndex }}" wire:model="draft.requirements.{{ $requirementIndex }}.emphasis_style" class="mt-1">
                                            <option value="normal">Normal</option>
                                            <option value="bold">Tebal</option>
                                            <option value="italic">Miring</option>
                                            <option value="underline">Garis bawah</option>
                                        </x-select-input>
                                    </div>
                                    <button type="button" wire:click="removeRequirement({{ $requirementIndex }})" class="ui-text-action-danger mb-1">Hapus</button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label for="document-special-assumptions" value="Asumsi khusus (opsional)" />
                                <x-textarea-input id="document-special-assumptions" wire:model="draft.engagement.special_assumptions" rows="4" class="mt-1"></x-textarea-input>
                            </div>
                            <div>
                                <x-input-label for="document-internal-note" value="Catatan internal" />
                                <x-textarea-input id="document-internal-note" wire:model="draft.engagement.internal_note" rows="4" class="mt-1"></x-textarea-input>
                                <p class="ui-help">Catatan ini tidak masuk ke PDF.</p>
                            </div>
                        </div>
                    </section>
                </fieldset>

                <aside class="space-y-5 lg:sticky lg:top-20" aria-labelledby="document-summary-heading">
                    <section class="ui-surface p-5">
                        <h2 id="document-summary-heading" class="ui-section-heading">Ringkasan draft</h2>
                        <dl class="mt-4 divide-y divide-line text-sm">
                            <div class="flex justify-between gap-4 py-3 first:pt-0">
                                <dt class="text-ink-muted">Cabang</dt>
                                <dd class="text-right font-medium text-ink">{{ $offer->branch?->code ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-3">
                                <dt class="text-ink-muted">Pihak</dt>
                                <dd class="font-semibold text-ink tabular-nums">{{ count($subjects) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-3">
                                <dt class="text-ink-muted">Aset</dt>
                                <dd class="font-semibold text-ink tabular-nums">{{ $assetCount }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-3">
                                <dt class="text-ink-muted">Komponen biaya</dt>
                                <dd class="font-semibold text-ink tabular-nums">{{ count($feeItems) }}</dd>
                            </div>
                            <div class="py-3">
                                <dt class="text-ink-muted">Subtotal</dt>
                                <dd class="mt-1 text-lg font-semibold text-ink tabular-nums">Rp {{ number_format($feeTotal, 0, ',', '.') }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-3 last:pb-0">
                                <dt class="text-ink-muted">Total termin</dt>
                                <dd class="font-semibold {{ $paymentTerms && $termTotal !== 100.0 ? 'text-red-700 dark:text-red-300' : 'text-ink' }} tabular-nums">{{ number_format($termTotal, 2, ',', '.') }}%</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="ui-surface p-5" aria-labelledby="document-preflight-heading">
                        <div class="flex items-center justify-between gap-3">
                            <h2 id="document-preflight-heading" class="ui-section-heading">Preflight</h2>
                            @if($printReadyEligible)
                                <span class="ui-badge ui-badge-success">Siap dicetak</span>
                            @elseif($preflight['errors'] === [] && $preflight['warnings'] === [])
                                <span class="ui-badge ui-badge-neutral">Belum diperiksa</span>
                            @elseif($preflight['errors'] === [])
                                <span class="ui-badge ui-badge-success">Lolos</span>
                            @else
                                <span class="ui-badge ui-badge-danger">Perlu diperbaiki</span>
                            @endif
                        </div>

                        @if($preflight['errors'] !== [])
                            <div class="mt-4">
                                <h3 class="text-sm font-semibold text-red-700 dark:text-red-300">Error</h3>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                                    @foreach($preflight['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($preflight['warnings'] !== [])
                            <div class="mt-4">
                                <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-300">Peringatan</h3>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-ink-secondary">
                                    @foreach($preflight['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($preflight['errors'] === [] && $preflight['warnings'] === [])
                            <p class="mt-3 text-sm leading-6 text-ink-muted">Simpan perubahan, lalu jalankan pemeriksaan sebelum membuat PDF.</p>
                        @endif
                    </section>

                    <div class="space-y-2">
                        @if($canManage)
                            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="saveDraft" class="w-full">
                                <span wire:loading.remove wire:target="saveDraft">Simpan draft</span>
                                <span wire:loading wire:target="saveDraft">Menyimpan…</span>
                            </x-primary-button>
                        @endif

                        @if($canGenerateDraft)
                            <x-secondary-button type="button" wire:click="checkPreflight" wire:loading.attr="disabled" wire:target="checkPreflight" class="w-full">
                                <span wire:loading.remove wire:target="checkPreflight">Periksa kelengkapan</span>
                                <span wire:loading wire:target="checkPreflight">Memeriksa…</span>
                            </x-secondary-button>

                            @if($rendererReady)
                                <a href="{{ route('offers.documents.preview', $offer) }}" target="_blank" rel="noopener" class="ui-btn ui-btn-secondary w-full">Buka pratinjau PDF</a>
                                <a href="{{ route('offers.documents.download', $offer) }}" class="ui-btn ui-btn-secondary w-full">Unduh PDF draft</a>
                            @else
                                <p class="ui-help text-center">Renderer PDF belum tersedia.</p>
                            @endif
                        @endif

                        @if($canManage && $canGenerateDraft && !$currentReviewVersion)
                            <x-primary-button
                                type="button"
                                wire:click="submitForReview"
                                wire:confirm="Simpan draft dan ajukan snapshot ini untuk review Supervisor?"
                                wire:loading.attr="disabled"
                                wire:target="submitForReview"
                                class="w-full"
                            >
                                <span wire:loading.remove wire:target="submitForReview">Ajukan untuk review</span>
                                <span wire:loading wire:target="submitForReview">Membekukan snapshot…</span>
                            </x-primary-button>
                        @endif

                        @if($currentReviewVersion)
                            @php
                                $reviewState = $currentReviewVersion->version_state instanceof \BackedEnum
                                    ? $currentReviewVersion->version_state->value
                                    : $currentReviewVersion->version_state;
                                $reviewDraftArtifact = $currentReviewVersion->artifacts->first(fn ($artifact) =>
                                    ($artifact->artifact_type instanceof \BackedEnum ? $artifact->artifact_type->value : $artifact->artifact_type) === 'draft'
                                    && ($artifact->storage_status instanceof \BackedEnum ? $artifact->storage_status->value : $artifact->storage_status) === 'ready'
                                );
                            @endphp
                            <section class="ui-surface-subtle p-4 text-sm" aria-labelledby="active-review-heading">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 id="active-review-heading" class="font-semibold text-ink">Versi {{ $currentReviewVersion->version_no }}</h3>
                                    <span class="ui-badge {{ $reviewState === 'approved' ? 'ui-badge-success' : 'ui-badge-info' }}">
                                        {{ $reviewState === 'approved' ? 'Approved' : 'In review' }}
                                    </span>
                                </div>
                                <p class="mt-2 leading-5 text-ink-muted">Review dan finalisasi selalu memakai snapshot tersimpan, bukan data editor yang berubah.</p>
                                @if($reviewDraftArtifact)
                                    <a href="{{ route('offers.documents.artifacts.download', [$offer, $currentReviewVersion, $reviewDraftArtifact]) }}" class="ui-text-action mt-3 inline-flex">Unduh snapshot PDF draft</a>
                                @endif

                                @if($canGeneratePrintReady && $reviewState === 'in_review')
                                    <div class="mt-4 space-y-2 border-t border-line pt-4">
                                        <x-primary-button type="button" wire:click="approveCurrentVersion" wire:confirm="Setujui snapshot versi ini? Isi snapshot akan dikunci." wire:loading.attr="disabled" wire:target="approveCurrentVersion" class="w-full">
                                            Setujui snapshot
                                        </x-primary-button>
                                        <x-textarea-input wire:model="reviewReason" rows="3" placeholder="Catatan wajib bila ditolak"></x-textarea-input>
                                        <x-secondary-button type="button" wire:click="rejectCurrentVersion" wire:confirm="Tolak versi ini dan kembalikan ke Admin?" class="w-full">
                                            Tolak untuk revisi
                                        </x-secondary-button>
                                    </div>
                                @elseif($canGeneratePrintReady && $reviewState === 'approved' && $finalizationEnabled)
                                    <x-primary-button type="button" wire:click="finalizeCurrentVersion" wire:confirm="Buat dan arsipkan PDF final bersih dari snapshot approved ini?" wire:loading.attr="disabled" wire:target="finalizeCurrentVersion" class="mt-4 w-full">
                                        <span wire:loading.remove wire:target="finalizeCurrentVersion">Finalkan dan arsipkan PDF</span>
                                        <span wire:loading wire:target="finalizeCurrentVersion">Memfinalkan…</span>
                                    </x-primary-button>
                                @elseif($canGeneratePrintReady && $reviewState === 'approved')
                                    <p class="ui-help mt-4">Finalisasi belum diaktifkan. Selesaikan golden visual dan UAT cetak, lalu aktifkan konfigurasi finalisasi.</p>
                                @endif
                            </section>
                        @endif

                        @if($canGeneratePrintReady)
                            <x-secondary-button type="button" wire:click="checkPrintReady" wire:loading.attr="disabled" wire:target="checkPrintReady" class="w-full">
                                <span wire:loading.remove wire:target="checkPrintReady">Periksa PDF siap cetak</span>
                                <span wire:loading wire:target="checkPrintReady">Memeriksa…</span>
                            </x-secondary-button>

                            @if($printReadyEligible && $printReadyRouteReady)
                                <a href="{{ route('offers.documents.print-ready', $offer) }}" class="ui-btn ui-btn-primary w-full">Unduh PDF siap cetak</a>
                            @elseif(!$printReadyRouteReady)
                                <p class="ui-help text-center">Endpoint PDF siap cetak belum tersedia.</p>
                            @else
                                <p class="ui-help text-center">Tombol unduh muncul setelah snapshot disetujui dan artifact final berhasil diarsipkan.</p>
                            @endif
                        @endif
                    </div>

                    @if($documentVersions->isNotEmpty())
                        <section class="ui-surface p-5" aria-labelledby="document-version-history-heading">
                            <h2 id="document-version-history-heading" class="ui-section-heading">Riwayat versi</h2>
                            <div class="mt-3 divide-y divide-line text-sm">
                                @foreach($documentVersions as $version)
                                    @php
                                        $state = $version->version_state instanceof \BackedEnum ? $version->version_state->value : $version->version_state;
                                    @endphp
                                    <div class="py-3" wire:key="document-history-{{ $version->id }}">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="font-medium text-ink">Versi {{ $version->version_no }}</span>
                                            <span class="ui-badge ui-badge-neutral">{{ str_replace('_', ' ', $state) }}</span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-3">
                                            @foreach($version->artifacts as $artifact)
                                                @php
                                                    $storageState = $artifact->storage_status instanceof \BackedEnum ? $artifact->storage_status->value : $artifact->storage_status;
                                                    $artifactType = $artifact->artifact_type instanceof \BackedEnum ? $artifact->artifact_type->value : $artifact->artifact_type;
                                                @endphp
                                                @if($storageState === 'ready' && ($artifactType === 'draft' ? $canGenerateDraft : $canGeneratePrintReady))
                                                    <a href="{{ route('offers.documents.artifacts.download', [$offer, $version, $artifact]) }}" class="ui-text-action">
                                                        Unduh {{ $artifactType === 'final' ? 'final' : 'draft' }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </aside>
            </div>
        </form>
    </div>
</div>
