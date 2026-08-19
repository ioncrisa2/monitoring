<?php

namespace App\Services\Offers;

use DomainException;

class OfferPreflightValidator
{
    public const MODE_DRAFT = 'draft';

    public const MODE_PRINT_READY = 'print_ready';

    /** @deprecated Use MODE_PRINT_READY. */
    public const MODE_REVIEW = self::MODE_PRINT_READY;

    /** @deprecated Use MODE_PRINT_READY. */
    public const MODE_FINAL = self::MODE_PRINT_READY;

    private const BLOCK_TYPES = [
        'text', 'bullets', 'dynamic', 'asset_list', 'fee_summary',
        'fee_table', 'payment_terms', 'requirements', 'exposure_table',
    ];

    public function __construct(
        private readonly OfferFeeCalculator $feeCalculator,
        private readonly IndonesianAmountSpeller $amountSpeller,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validate(array $snapshot, string $mode = self::MODE_DRAFT): array
    {
        if (! in_array($mode, [self::MODE_DRAFT, self::MODE_PRINT_READY], true)) {
            throw new DomainException('Mode preflight tidak valid.');
        }

        $strict = $mode !== self::MODE_DRAFT;
        $errors = [];
        $warnings = [];
        $flag = function (bool $condition, string $message) use (&$errors, &$warnings, $strict): void {
            if (! $condition) {
                return;
            }

            if ($strict) {
                $errors[] = $message;
            } else {
                $warnings[] = $message;
            }
        };

        foreach (['document', 'issuer', 'recipient', 'clauses', 'signatures'] as $section) {
            if (! isset($snapshot[$section]) || ! is_array($snapshot[$section])) {
                $errors[] = "Bagian snapshot {$section} tidak tersedia.";
            }
        }

        if ($errors !== []) {
            return ['errors' => array_values(array_unique($errors)), 'warnings' => []];
        }

        foreach (['number', 'place', 'date', 'subject', 'opening', 'closing'] as $field) {
            if (! $this->filled($snapshot['document'][$field] ?? null)) {
                $errors[] = "Nilai document.{$field} wajib tersedia.";
            }
        }

        $titles = (array) config('offer-documents.clause_titles', []);
        $clauses = $snapshot['clauses'];

        if (! array_is_list($clauses) || count($clauses) !== 25 || count($titles) !== 25) {
            $errors[] = 'Snapshot harus memiliki tepat 25 klausul terurut.';
        } else {
            foreach (array_values($titles) as $index => $title) {
                $clause = $clauses[$index] ?? null;
                $key = array_keys($titles)[$index];

                if (! is_array($clause)
                    || ($clause['number'] ?? null) !== $index + 1
                    || ($clause['key'] ?? null) !== $key
                    || ($clause['title'] ?? null) !== $title
                    || (($clause['paragraphs'] ?? []) === []
                        && ($clause['items'] ?? []) === []
                        && ($clause['blocks'] ?? []) === [])) {
                    $errors[] = 'Klausul '.($index + 1).' tidak memenuhi kontrak urutan dan konten.';

                    continue;
                }

                $contentRows = array_merge($clause['paragraphs'] ?? [], $clause['items'] ?? []);

                if (count($contentRows) > 200) {
                    $errors[] = 'Klausul '.($index + 1).' melebihi batas 200 baris konten.';
                }

                foreach ($contentRows as $content) {
                    if (! is_string($content) || mb_strlen($content) > 2000) {
                        $errors[] = 'Konten klausul '.($index + 1).' harus berupa teks maksimal 2000 karakter per baris.';
                        break;
                    }
                }

                $this->validateClauseBlocks($clause['blocks'] ?? null, $index + 1, $errors);
            }
        }

        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $engagement = is_array($snapshot['engagement'] ?? null) ? $snapshot['engagement'] : [];
        $subjects = is_array($snapshot['subjects'] ?? null) ? $snapshot['subjects'] : [];
        $commercial = is_array($snapshot['commercial'] ?? null) ? $snapshot['commercial'] : [];
        $requirements = is_array($snapshot['requirements'] ?? null) ? $snapshot['requirements'] : [];
        $templateMetadata = is_array($metadata['template'] ?? null) ? $metadata['template'] : [];
        $templateSchemaVersion = (int) ($templateMetadata['schema_version'] ?? $metadata['schema_version'] ?? 1);
        $templateCategory = $templateMetadata['category'] ?? null;
        $constraints = is_array($templateMetadata['constraints'] ?? null)
            ? $templateMetadata['constraints']
            : [];

        $flag(($metadata['number_allocation']['status'] ?? null) !== 'allocated', 'Nomor surat resmi belum dialokasikan.');
        $this->validateApprovedMaster(
            $templateMetadata,
            'Versi template legal',
            $flag,
            true,
        );
        $this->validateApprovedMaster(
            is_array($metadata['issuer_profile'] ?? null) ? $metadata['issuer_profile'] : [],
            'Profil penerbit',
            $flag,
        );
        $this->validateApprovedMaster(
            is_array($metadata['signer'] ?? null) ? $metadata['signer'] : [],
            'Profil penandatangan',
            $flag,
        );
        $flag(! $this->filled($engagement['recipient_organization'] ?? null), 'Nama organisasi penerima belum diisi.');
        $flag(! $this->filled($engagement['recipient_address'] ?? null), 'Alamat penerima belum diisi.');
        $flag(! $this->filled($engagement['recipient_city'] ?? null), 'Kota penerima belum diisi.');
        $flag(! $this->filled($engagement['issue_city'] ?? null), 'Kota penerbitan belum diisi.');
        $flag(! $this->filled($engagement['purpose'] ?? null), 'Tujuan penilaian belum diisi.');
        $flag(! $this->filled($engagement['valuation_basis'] ?? null), 'Dasar nilai belum diisi.');
        $flag(
            ! $this->filled($engagement['valuation_date'] ?? null)
                && ! $this->filled($engagement['valuation_date_rule'] ?? null),
            'Tanggal penilaian atau aturan tanggal penilaian belum diisi.',
        );
        $flag(! $this->filled($engagement['ownership_form'] ?? null), 'Bentuk kepemilikan belum diisi.');
        $flag(! $this->filled($engagement['investigation_level'] ?? null), 'Tingkat investigasi belum diisi.');
        $flag(! $this->filled($engagement['report_format'] ?? null), 'Format laporan belum diisi.');
        $flag(
            ! is_int($engagement['report_copies'] ?? null) || $engagement['report_copies'] < 1,
            'Jumlah eksemplar laporan harus sedikitnya satu.',
        );
        $flag(! $this->filled($engagement['completion_days'] ?? null), 'Durasi penyelesaian belum diisi.');
        $flag(! $this->filled($engagement['completion_day_type'] ?? null), 'Jenis hari penyelesaian belum diisi.');
        $flag(! $this->filled($engagement['tax_inclusion'] ?? null), 'Mode pajak belum dipilih.');
        $flag(! $this->filled($snapshot['issuer']['name'] ?? null), 'Nama penerbit belum diisi.');
        $flag(! $this->filledTextList($snapshot['issuer']['address_lines'] ?? null), 'Alamat penerbit belum diisi.');
        $flag(! $this->filled($snapshot['signatures']['issuer_name'] ?? null), 'Nama penandatangan belum diisi.');
        $flag(! $this->filled($snapshot['signatures']['issuer_title'] ?? null), 'Jabatan penandatangan belum diisi.');

        if ($templateSchemaVersion === OfferTemplateSchemaV2::SCHEMA_VERSION) {
            $letterhead = is_array($snapshot['issuer']['letterhead'] ?? null)
                ? $snapshot['issuer']['letterhead']
                : [];
            $flag(($letterhead['configured'] ?? false) !== true, 'Letterhead resmi profil penerbit belum diunggah.');
            $flag(($letterhead['verified'] ?? false) !== true, 'Hash, MIME, atau dimensi letterhead resmi tidak valid.');
            $flag(($metadata['issuer_profile']['letterhead_verified'] ?? false) !== true, 'Verifikasi letterhead tidak tercatat pada snapshot profil penerbit.');
        }

        foreach ((array) ($constraints['required_engagement_fields'] ?? []) as $requiredField) {
            if ($requiredField === 'valuation_date_or_rule') {
                $flag(
                    ! $this->filled($engagement['valuation_date'] ?? null)
                        && ! $this->filled($engagement['valuation_date_rule'] ?? null),
                    'Field wajib template valuation_date_or_rule belum diisi.',
                );

                continue;
            }

            if (is_string($requiredField)) {
                $flag(! $this->filled($engagement[$requiredField] ?? null), "Field wajib template {$requiredField} belum diisi.");
            }
        }

        if ($this->filled($constraints['purpose_must_equal'] ?? null)) {
            $flag(
                ($engagement['purpose'] ?? null) !== $constraints['purpose_must_equal'],
                'Tujuan penilaian tidak sesuai dengan template yang dipilih.',
            );
        }

        if ($this->filled($constraints['valuation_basis_must_equal'] ?? null)) {
            $flag(
                ($engagement['valuation_basis'] ?? null) !== $constraints['valuation_basis_must_equal'],
                'Dasar nilai tidak sesuai dengan template yang dipilih.',
            );
        }

        if (in_array($engagement['request_reference_type'] ?? 'none', ['letter', 'email'], true)) {
            $flag(! $this->filled($engagement['request_reference_no'] ?? null), 'Nomor referensi permintaan belum diisi.');
            $flag(! $this->filled($engagement['request_reference_date'] ?? null), 'Tanggal referensi permintaan belum diisi.');
        }

        $primarySubjects = 0;
        $assetCount = 0;
        $assetIds = [];
        $documentKeys = [];

        foreach ($subjects as $subject) {
            if (! is_array($subject)) {
                $errors[] = 'Struktur subject tidak valid.';

                continue;
            }

            $primarySubjects += ($subject['is_primary'] ?? false) ? 1 : 0;

            foreach (($subject['assets'] ?? []) as $asset) {
                if (! is_array($asset)) {
                    $errors[] = 'Struktur aset tidak valid.';

                    continue;
                }

                $assetCount++;
                $assetId = $asset['id'] ?? null;

                if ($assetId !== null) {
                    $assetIds[] = (string) $assetId;
                }

                $flag(! $this->filled($asset['asset_type'] ?? null), 'Jenis aset belum diisi.');
                $flag(! $this->filled($asset['address'] ?? null), 'Alamat aset belum diisi.');
                $documents = is_array($asset['documents'] ?? null) ? $asset['documents'] : [];
                if (($constraints['required_asset_document'] ?? true) === true) {
                    $flag($documents === [], 'Dokumen kepemilikan aset belum diisi.');
                }

                if (($constraints['requires_liquidation_value'] ?? false) === true
                    || $templateCategory === 'property-auction') {
                    $this->validateAuctionAsset($asset, $flag, $errors);
                }

                foreach ($documents as $document) {
                    if (! is_array($document)) {
                        $errors[] = 'Struktur dokumen kepemilikan tidak valid.';

                        continue;
                    }

                    $flag(! $this->filled($document['document_type'] ?? null), 'Jenis dokumen kepemilikan belum diisi.');
                    $flag(! $this->filled($document['document_no'] ?? null), 'Nomor dokumen kepemilikan belum diisi.');

                    $key = mb_strtolower(trim((string) ($document['document_type'] ?? '')).'|'.trim((string) ($document['document_no'] ?? '')));

                    if (isset($documentKeys[$key])) {
                        $errors[] = 'Nomor dokumen kepemilikan terduplikasi dalam penawaran.';
                    }

                    $documentKeys[$key] = true;
                }
            }
        }

        $flag($subjects === [], 'Penawaran belum memiliki subject.');
        $flag($primarySubjects !== 1, 'Penawaran harus memiliki tepat satu subject utama.');
        $flag($assetCount === 0, 'Penawaran belum memiliki aset.');
        $flag($requirements === [], 'Permintaan data awal belum diisi.');

        foreach (($commercial['calculation_errors'] ?? []) as $calculationError) {
            $errors[] = 'Perhitungan komersial: '.$calculationError;
        }

        $this->validateCommercialCalculation($commercial, $engagement, $errors);

        if (($commercial['payment_terms'] ?? []) !== []
            && ($commercial['payment_term_bps_total'] ?? null) !== 10_000) {
            $errors[] = 'Total persentase termin harus tepat 100%.';
        }

        if (($commercial['quoted_amount'] ?? 0) < 0
            || ($commercial['tax_base'] ?? 0) < 0
            || ($commercial['ppn'] ?? 0) < 0
            || ($commercial['document_payable_total'] ?? 0) < 0) {
            $errors[] = 'Nilai komersial tidak boleh negatif.';
        }

        $flag(($commercial['line_items'] ?? []) === [], 'Item fee belum diisi.');
        $flag(($commercial['document_payable_total'] ?? 0) === 0, 'Total nilai penawaran masih nol.');

        $requiresFeePerAsset = ($constraints['require_fee_per_asset'] ?? false) === true
            || $templateCategory === 'property-auction';

        if ($requiresFeePerAsset) {
            $flag(
                ($engagement['fee_presentation'] ?? $commercial['fee_presentation'] ?? null) !== 'per_asset',
                'Template lelang wajib memakai presentasi fee per aset.',
            );
            $this->validateOneFeePerAsset($assetIds, (array) ($commercial['line_items'] ?? []), $flag, $errors);
        }

        if (($constraints['requires_exposure_table'] ?? false) === true
            || $templateCategory === 'property-auction') {
            $exposureRows = is_array($commercial['exposure_rows'] ?? null) ? $commercial['exposure_rows'] : [];
            $flag(count($exposureRows) !== $assetCount, 'Tabel exposure harus memiliki tepat satu baris untuk setiap aset.');
        }

        if ($templateSchemaVersion === OfferTemplateSchemaV2::SCHEMA_VERSION) {
            $this->validateCompletionSource($snapshot['clauses'], $engagement, $errors);
        }

        if ($strict && ($metadata['uses_provisional_copy'] ?? true)) {
            $errors[] = 'Redaksi provisional DRAF tidak boleh digunakan untuk PDF siap cetak.';
        }

        if ($strict && ($metadata['uses_provisional_issuer'] ?? true)) {
            $errors[] = 'Profil penerbit provisional tidak boleh digunakan untuk PDF siap cetak.';
        }

        if ($strict && OfferDocumentContentGuard::containsProvisionalMarker([
            $snapshot['document'],
            $snapshot['issuer'],
            $snapshot['recipient'],
            $snapshot['clauses'],
            $snapshot['signatures'],
        ])) {
            $errors[] = 'PDF siap cetak tidak boleh memuat penanda DRAF atau redaksi provisional.';
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @param list<string> $errors */
    private function validateClauseBlocks(mixed $blocks, int $clauseNumber, array &$errors): void
    {
        // Legacy standard-v1 snapshots have paragraphs/items only.
        if ($blocks === null) {
            return;
        }

        if (! is_array($blocks) || ! array_is_list($blocks) || $blocks === [] || count($blocks) > 50) {
            $errors[] = "Klausul {$clauseNumber} wajib memiliki 1-50 blok terurut.";

            return;
        }

        foreach ($blocks as $index => $block) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, self::BLOCK_TYPES, true)) {
                $errors[] = "Klausul {$clauseNumber} memiliki tipe blok tidak dikenal pada urutan ".($index + 1).'.';

                continue;
            }

            $type = $block['type'];

            if ($type === 'text' && ! $this->filled($block['text'] ?? null)) {
                $errors[] = "Blok teks klausul {$clauseNumber} tidak terisi.";
            } elseif ($type === 'bullets' && ! $this->filledTextList($block['items'] ?? null)) {
                $errors[] = "Blok bullet klausul {$clauseNumber} tidak terisi.";
            } elseif ($type === 'dynamic') {
                $hasText = $this->filled($block['text'] ?? null);
                $hasItems = $this->filledTextList($block['items'] ?? null);

                if ($hasText === $hasItems) {
                    $errors[] = "Blok dinamis klausul {$clauseNumber} harus memiliki tepat satu hasil text atau items.";
                }
            } elseif (! in_array($type, ['text', 'bullets', 'dynamic'], true)) {
                $rows = $block['rows'] ?? null;

                if (! is_array($rows) || ! array_is_list($rows)) {
                    $errors[] = "Blok tabel klausul {$clauseNumber} tidak memiliki rows yang valid.";
                }
            }

            if ($this->containsUnsafeResolvedContent($block)) {
                $errors[] = "Blok klausul {$clauseNumber} masih memuat token, HTML, Blade, atau PHP mentah.";
            }
        }
    }

    private function containsUnsafeResolvedContent(mixed $value): bool
    {
        if (is_string($value)) {
            return str_contains($value, '{{')
                || str_contains($value, '}}')
                || preg_match('/<\?(?:php|=)?|\?>|{!!|!!}|@(?:php|endphp|inject|include|extends|section|yield|component|livewire)\b/i', $value) === 1
                || preg_match('/<\s*\/?\s*(?:script|iframe|object|embed|svg|img|style)[^>]*>/i', $value) === 1;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsUnsafeResolvedContent($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  callable(bool, string): void  $flag
     * @param  list<string>  $errors
     */
    private function validateAuctionAsset(array $asset, callable $flag, array &$errors): void
    {
        foreach ([
            'exposure_amount' => 'Exposure aset lelang belum diisi.',
            'reference_market_value' => 'Nilai Pasar referensi aset lelang belum diisi.',
            'reference_liquidation_value' => 'Nilai Likuidasi referensi aset lelang belum diisi.',
            'liquidation_discount_bps' => 'Diskon likuidasi aset lelang belum diisi.',
        ] as $field => $message) {
            $flag(! $this->integerish($asset[$field] ?? null), $message);
        }

        if (! $this->integerish($asset['reference_market_value'] ?? null)
            || ! $this->integerish($asset['reference_liquidation_value'] ?? null)
            || ! $this->integerish($asset['liquidation_discount_bps'] ?? null)) {
            return;
        }

        $market = (int) $asset['reference_market_value'];
        $liquidation = (int) $asset['reference_liquidation_value'];
        $discount = (int) $asset['liquidation_discount_bps'];

        if ($market < 0 || $liquidation < 0 || $discount < 0 || $discount > 10_000) {
            $errors[] = 'Nilai exposure/lelang tidak boleh negatif dan diskon harus 0%-100%.';

            return;
        }

        if ($liquidation > $market) {
            $errors[] = 'Nilai Likuidasi tidak boleh melebihi Nilai Pasar referensi.';
        }

        if ($market > 0) {
            $derivedDiscount = (int) round((($market - $liquidation) / $market) * 10_000);

            if (abs($derivedDiscount - $discount) > 1) {
                $errors[] = 'Diskon likuidasi tidak konsisten dengan Nilai Pasar dan Nilai Likuidasi.';
            }
        }
    }

    /**
     * @param  list<string>  $assetIds
     * @param  callable(bool, string): void  $flag
     * @param  list<string>  $errors
     */
    private function validateOneFeePerAsset(array $assetIds, array $lineItems, callable $flag, array &$errors): void
    {
        $counts = array_fill_keys($assetIds, 0);

        foreach ($lineItems as $lineItem) {
            if (! is_array($lineItem)) {
                $errors[] = 'Struktur item fee tidak valid.';

                continue;
            }

            $assetId = $lineItem['offer_asset_id'] ?? null;

            if ($assetId === null || ! array_key_exists((string) $assetId, $counts)) {
                $errors[] = 'Setiap item fee lelang wajib menunjuk satu aset dalam penawaran.';

                continue;
            }

            $counts[(string) $assetId]++;
        }

        $flag(count($assetIds) === 0, 'Aset lelang belum tersimpan sehingga pemetaan fee belum dapat diverifikasi.');

        foreach ($counts as $count) {
            if ($count !== 1) {
                $errors[] = 'Template lelang mewajibkan tepat satu item fee untuk setiap aset.';
                break;
            }
        }
    }

    /** @param list<string> $errors */
    private function validateCommercialCalculation(array $commercial, array $engagement, array &$errors): void
    {
        $taxMode = $commercial['tax_inclusion'] ?? $engagement['tax_inclusion'] ?? null;

        if (! is_string($taxMode) || ($commercial['line_items'] ?? []) === []) {
            return;
        }

        try {
            $calculated = $this->feeCalculator->calculate(
                (array) $commercial['line_items'],
                $taxMode,
                (int) ($commercial['ppn_rate_bps'] ?? 0),
                (int) ($commercial['pph_rate_bps'] ?? 0),
                (array) ($commercial['payment_terms'] ?? []),
            );
        } catch (\Throwable $exception) {
            $errors[] = 'Perhitungan komersial snapshot tidak dapat diverifikasi: '.$exception->getMessage();

            return;
        }

        foreach (['quoted_amount', 'tax_base', 'ppn', 'pph', 'document_payable_total', 'payment_term_bps_total'] as $field) {
            if (($commercial[$field] ?? null) !== $calculated[$field]) {
                $errors[] = "Nilai komersial {$field} tidak konsisten dengan item fee, pajak, atau termin.";
            }
        }

        try {
            $amountInWords = $this->amountSpeller->spell(
                $calculated['document_payable_total'],
                (string) ($engagement['currency'] ?? 'IDR'),
            );

            if (($commercial['amount_in_words'] ?? null) !== $amountInWords) {
                $errors[] = 'Nilai terbilang tidak konsisten dengan jumlah penawaran.';
            }
        } catch (DomainException $exception) {
            $errors[] = 'Nilai terbilang tidak dapat diverifikasi: '.$exception->getMessage();
        }
    }

    /** @param list<string> $errors */
    private function validateCompletionSource(array $clauses, array $engagement, array &$errors): void
    {
        if (! isset($engagement['completion_days']) || ! is_string($engagement['completion_day_type'] ?? null)) {
            return;
        }

        $dayType = match ($engagement['completion_day_type']) {
            'business' => 'hari kerja',
            'calendar' => 'hari kalender',
            default => null,
        };

        if ($dayType === null) {
            return;
        }

        $expected = ((int) $engagement['completion_days']).' '.$dayType;

        foreach ($clauses as $clause) {
            foreach (is_array($clause['blocks'] ?? null) ? $clause['blocks'] : [] as $block) {
                if (is_array($block)
                    && ($block['type'] ?? null) === 'dynamic'
                    && ($block['source'] ?? null) === 'completion_time'
                    && ($block['text'] ?? null) !== $expected) {
                    $errors[] = 'Teks SLA tidak konsisten dengan angka dan jenis hari penyelesaian.';
                }
            }
        }
    }

    private function integerish(mixed $value): bool
    {
        return (is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/\A\d+\z/', $value) === 1);
    }

    private function filled(mixed $value): bool
    {
        return ! ($value === null || $value === '' || (is_string($value) && trim($value) === ''));
    }

    private function filledTextList(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $master
     * @param  callable(bool, string): void  $flag
     */
    private function validateApprovedMaster(
        array $master,
        string $label,
        callable $flag,
        bool $requiresActiveTemplate = false,
    ): void {
        $approved = ($master['status'] ?? null) === 'approved';
        $flag(! $approved, "{$label} belum disetujui.");

        if (! $approved) {
            return;
        }

        $flag(! $this->filled($master['approved_by'] ?? null), "{$label} belum memiliki identitas penyetuju.");
        $flag(! $this->filled($master['approved_at'] ?? null), "{$label} belum memiliki waktu persetujuan.");
        $flag(! $this->validChecksum($master['checksum'] ?? null), "Checksum {$label} tidak valid.");
        $flag(($master['integrity_valid'] ?? false) !== true, "Integritas isi {$label} tidak sesuai dengan checksum persetujuan.");
        $flag(($master['is_effective'] ?? false) !== true, "{$label} belum atau tidak lagi berlaku pada tanggal penawaran.");

        if ($requiresActiveTemplate) {
            $flag(($master['template_active'] ?? false) !== true, 'Template penawaran tidak aktif.');
            $flag(($master['schema_valid'] ?? false) !== true, 'Schema template penawaran resmi tidak valid atau tidak lengkap.');
        }
    }

    private function validChecksum(mixed $checksum): bool
    {
        return is_string($checksum) && preg_match('/\A[a-f0-9]{64}\z/i', $checksum) === 1;
    }
}
