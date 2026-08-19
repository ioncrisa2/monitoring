<?php

namespace App\Services\Offers;

use App\Enums\OfferDocumentMasterReviewStatus;
use App\Enums\OfferFeePresentation;
use App\Enums\OfferTaxInclusion;
use App\Enums\OfferTemplateBlockType;
use App\Enums\OfferTemplateCategory;
use App\Enums\OfferTemplateCondition;
use App\Enums\OfferTemplateDynamicSource;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\OfferTemplateVersion;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use JsonException;

final class OfferDocumentMasterIntegrityService
{
    /**
     * Return a deterministic checksum of content fields. Review/status metadata
     * is deliberately excluded because it describes the workflow event.
     */
    public function checksum(Model $master): string
    {
        try {
            return hash('sha256', json_encode(
                $this->canonicalize($this->contentPayload($master)),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            ));
        } catch (JsonException $exception) {
            throw new DomainException('Konten master dokumen tidak dapat dibuatkan checksum.', previous: $exception);
        }
    }

    public function verify(Model $master): bool
    {
        $stored = $master->getAttribute('checksum');

        return is_string($stored)
            && preg_match('/\A[a-f0-9]{64}\z/i', $stored) === 1
            && hash_equals(mb_strtolower($stored), $this->checksum($master));
    }

    public function assertApprovedIntegrity(Model $master): void
    {
        $this->assertSupported($master);

        if ($master->getAttribute('status') !== OfferDocumentMasterReviewStatus::Approved->value
            || empty($master->getAttribute('approved_by'))
            || empty($master->getAttribute('approved_at'))) {
            throw new DomainException('Master approved wajib memiliki penyetuju dan waktu persetujuan.');
        }

        if ($master->getAttribute('submitted_at') !== null
            && (empty($master->getAttribute('submitted_by'))
                || empty($master->getAttribute('reviewed_by'))
                || empty($master->getAttribute('reviewed_at'))
                || (int) $master->getAttribute('approved_by') !== (int) $master->getAttribute('reviewed_by'))) {
            throw new DomainException('Master v2 approved wajib memiliki jejak pengajuan dan review yang lengkap.');
        }

        if ($master->getAttribute('submitted_at') !== null) {
            $this->assertApprovable($master);
        } else {
            $this->assertContentValid($master);
        }

        if (! $this->verify($master)) {
            throw new DomainException('Checksum master approved tidak sesuai dengan kontennya.');
        }
    }

    /** Legacy schema v1 remains valid here for reads and checksum verification. */
    public function assertContentValid(Model $master): void
    {
        $this->assertSupported($master);

        if ($master->effective_from === null) {
            throw new DomainException('Tanggal mulai berlaku master wajib diisi sebelum master disetujui.');
        }

        if ($master->effective_until !== null && $master->effective_until->lt($master->effective_from)) {
            throw new DomainException('Tanggal akhir berlaku master tidak boleh mendahului tanggal mulai berlaku.');
        }

        if ($master instanceof OfferTemplateVersion) {
            $errors = $this->templateSchemaErrorsFor($master);

            if ($errors !== []) {
                throw new DomainException('Schema template tidak valid: '.implode(' ', $errors));
            }

            return;
        }

        $requiredFields = $master instanceof IssuerProfileVersion
            ? [
                'legal_name' => 'Nama legal penerbit',
                'address' => 'Alamat penerbit',
                'city' => 'Kota penerbit',
            ]
            : [
                'signer_key' => 'Kode penandatangan',
                'full_name' => 'Nama penandatangan',
                'position' => 'Jabatan penandatangan',
            ];

        foreach ($requiredFields as $field => $label) {
            if (! is_string($master->getAttribute($field)) || trim($master->getAttribute($field)) === '') {
                throw new DomainException("{$label} wajib diisi sebelum master disetujui.");
            }
        }
    }

    /** Validate requirements for a new approval rather than a legacy read. */
    public function assertApprovable(Model $master, bool $allowLegacy = false): void
    {
        $this->assertContentValid($master);

        if ($master instanceof OfferTemplateVersion) {
            $isV2 = $master->schema_version === OfferTemplateSchemaV2::SCHEMA_VERSION
                && $master->layout_version === OfferTemplateSchemaV2::LAYOUT_VERSION;

            if (! $isV2 && ! $allowLegacy) {
                throw new DomainException('Template baru hanya dapat disetujui dengan schema_version 2 dan layout offer-a4-v2.');
            }

            return;
        }

        if ($allowLegacy) {
            return;
        }

        if ($master instanceof IssuerProfileVersion) {
            $this->assertOfficialLetterhead($master);

            return;
        }

        foreach (['signature_path', 'signature_sha256', 'signature_mime', 'stamp_path', 'stamp_sha256', 'stamp_mime'] as $field) {
            if ($master->getAttribute($field) !== null && $master->getAttribute($field) !== '') {
                throw new DomainException('Master penandatangan tidak boleh menyimpan gambar tanda tangan atau stempel.');
            }
        }
    }

    /** @return list<string> */
    public function templateSchemaErrorsFor(OfferTemplateVersion $master): array
    {
        $errors = $this->templateSchemaErrors(
            (array) $master->clause_schema,
            is_array($master->condition_schema) ? $master->condition_schema : null,
            $master->schema_version,
        );

        if ($master->schema_version === 1) {
            if ($master->layout_version !== 'standard-v1') {
                $errors[] = 'Schema v1 hanya mendukung layout standard-v1.';
            }

            if (! in_array($master->header_mode, ['odd_pages', 'all_pages'], true)) {
                $errors[] = 'Mode header template tidak didukung.';
            }

            return array_values(array_unique($errors));
        }

        if ($master->schema_version !== OfferTemplateSchemaV2::SCHEMA_VERSION) {
            $errors[] = 'Versi schema template belum didukung.';
        }

        if ($master->layout_version !== OfferTemplateSchemaV2::LAYOUT_VERSION) {
            $errors[] = 'Schema v2 hanya mendukung layout offer-a4-v2.';
        }

        if ($master->header_mode !== OfferTemplateSchemaV2::HEADER_MODE) {
            $errors[] = 'Layout offer-a4-v2 wajib merender kop pada semua halaman.';
        }

        $category = $master->template?->category;
        $categoryValue = $category instanceof OfferTemplateCategory ? $category->value : $category;

        if (! in_array($categoryValue, array_column(OfferTemplateCategory::cases(), 'value'), true)) {
            $errors[] = 'Kategori template v2 tidak valid.';
        }

        $defaults = is_array($master->clause_schema['defaults'] ?? null)
            ? $master->clause_schema['defaults']
            : [];
        $expectedSemantics = [
            OfferTemplateCategory::PropertyCollateral->value => [
                'purpose' => 'Penjaminan utang',
                'valuation_basis' => 'Nilai Pasar',
                'fee_presentation' => OfferFeePresentation::LumpSum->value,
            ],
            OfferTemplateCategory::PropertyAuction->value => [
                'purpose' => 'Pelaksanaan lelang',
                'valuation_basis' => 'Nilai Pasar dan Nilai Likuidasi',
                'fee_presentation' => OfferFeePresentation::PerAsset->value,
            ],
            OfferTemplateCategory::PropertyRental->value => [
                'purpose' => 'Penentuan nilai sewa pasar',
                'valuation_basis' => 'Nilai Sewa Pasar',
                'fee_presentation' => OfferFeePresentation::LumpSum->value,
            ],
        ];

        foreach ($expectedSemantics[$categoryValue] ?? [] as $field => $expectedValue) {
            if (($defaults[$field] ?? null) !== $expectedValue) {
                $errors[] = "defaults.{$field} tidak sesuai dengan kategori template {$categoryValue}.";
            }
        }

        $constraints = is_array($master->clause_schema['constraints'] ?? null)
            ? $master->clause_schema['constraints']
            : [];
        $blockTypes = $this->schemaBlockTypes((array) ($master->clause_schema['clauses'] ?? []));
        $auction = $categoryValue === OfferTemplateCategory::PropertyAuction->value;

        if (($constraints['required_asset_document'] ?? false) !== true) {
            $errors[] = 'Template v2 wajib mensyaratkan dokumen kepemilikan setiap aset.';
        }

        foreach (['require_fee_per_asset', 'requires_liquidation_value', 'requires_exposure_table'] as $field) {
            if (($constraints[$field] ?? null) !== $auction) {
                $errors[] = "constraints.{$field} tidak sesuai dengan kategori template {$categoryValue}.";
            }
        }

        foreach ([
            OfferTemplateBlockType::AssetList->value,
            OfferTemplateBlockType::PaymentTerms->value,
            OfferTemplateBlockType::Requirements->value,
        ] as $requiredBlockType) {
            if (! in_array($requiredBlockType, $blockTypes, true)) {
                $errors[] = "Template v2 wajib memiliki blok {$requiredBlockType}.";
            }
        }

        if ($auction) {
            if (($defaults['fee_presentation'] ?? null) !== OfferFeePresentation::PerAsset->value
                || ($constraints['require_fee_per_asset'] ?? false) !== true) {
                $errors[] = 'Template lelang wajib menggunakan fee per aset.';
            }

            if (($constraints['requires_liquidation_value'] ?? false) !== true
                || ($constraints['requires_exposure_table'] ?? false) !== true
                || ! in_array(OfferTemplateBlockType::ExposureTable->value, $blockTypes, true)) {
                $errors[] = 'Template lelang wajib memiliki data Nilai Likuidasi dan tabel exposure.';
            }

            if (! in_array(OfferTemplateBlockType::FeeTable->value, $blockTypes, true)) {
                $errors[] = 'Template lelang wajib memiliki tabel fee per aset.';
            }
        } elseif (! in_array(OfferTemplateBlockType::FeeSummary->value, $blockTypes, true)) {
            $errors[] = 'Template non-lelang wajib memiliki ringkasan fee lump sum.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Backward-compatible entry point. Without an explicit version, root shape
     * distinguishes v1 from v2 for existing snapshot/UI callers.
     *
     * @return list<string>
     */
    public function templateSchemaErrors(
        array $schema,
        ?array $conditionSchema = null,
        ?int $schemaVersion = null,
    ): array {
        $schemaVersion ??= array_key_exists('defaults', $schema) || array_key_exists('constraints', $schema) ? 2 : 1;

        return $schemaVersion === 2
            ? $this->templateSchemaV2Errors($schema, $conditionSchema)
            : $this->templateSchemaV1Errors($schema, $conditionSchema);
    }

    /** @return list<string> */
    private function templateSchemaV1Errors(array $schema, ?array $conditionSchema): array
    {
        $errors = [];
        $document = $schema['document'] ?? null;
        $clauses = $schema['clauses'] ?? null;
        $expectedKeys = array_keys((array) config('offer-documents.clause_titles', []));

        if (count($expectedKeys) !== 25) {
            $errors[] = 'Konfigurasi aplikasi wajib mendefinisikan tepat 25 klausul.';
        }

        $unknownRootKeys = array_values(array_diff(array_keys($schema), ['document', 'clauses']));

        if ($unknownRootKeys !== []) {
            $errors[] = 'Field schema tidak dikenal: '.implode(', ', $unknownRootKeys).'.';
        }

        if (! is_array($document)) {
            $errors[] = 'Bagian document wajib berupa object.';
        } else {
            $unknownDocumentKeys = array_values(array_diff(array_keys($document), ['opening', 'closing']));

            if ($unknownDocumentKeys !== []) {
                $errors[] = 'Field document tidak dikenal: '.implode(', ', $unknownDocumentKeys).'.';
            }

            foreach (['opening', 'closing'] as $field) {
                $value = $document[$field] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    $errors[] = "document.{$field} wajib berupa teks yang terisi.";
                } elseif (mb_strlen($value) > 5000) {
                    $errors[] = "document.{$field} maksimal 5000 karakter.";
                }
            }
        }

        if ($conditionSchema !== null && $conditionSchema !== []) {
            $errors[] = 'condition_schema belum didukung oleh renderer dan harus kosong.';
        }

        if (! is_array($clauses)) {
            $errors[] = 'Bagian clauses wajib berupa object dengan tepat 25 klausul.';
        } else {
            $actualKeys = array_keys($clauses);
            $missing = array_values(array_diff($expectedKeys, $actualKeys));
            $unknown = array_values(array_diff($actualKeys, $expectedKeys));

            if ($missing !== []) {
                $errors[] = 'Klausul wajib belum tersedia: '.implode(', ', $missing).'.';
            }

            if ($unknown !== []) {
                $errors[] = 'Klausul tidak dikenal: '.implode(', ', $unknown).'.';
            }

            foreach ($expectedKeys as $key) {
                if (array_key_exists($key, $clauses)) {
                    $this->validateLegacyClause($key, $clauses[$key], $errors);
                }
            }
        }

        if (OfferDocumentContentGuard::containsProvisionalMarker($schema)) {
            $errors[] = 'Schema template approved tidak boleh memuat penanda DRAF atau provisional.';
        }

        return array_values(array_unique($errors));
    }

    /** @return list<string> */
    private function templateSchemaV2Errors(array $schema, ?array $conditionSchema): array
    {
        $errors = [];
        $expectedClauseKeys = array_keys((array) config('offer-documents.clause_titles', []));

        if (count($expectedClauseKeys) !== 25) {
            $errors[] = 'Konfigurasi aplikasi wajib mendefinisikan tepat 25 klausul.';
        }

        $this->validateExactKeys('root', $schema, OfferTemplateSchemaV2::ROOT_KEYS, $errors);

        if ($conditionSchema !== null && $conditionSchema !== []) {
            $errors[] = 'condition_schema v2 harus kosong; blok hanya boleh memakai kondisi bawaan yang di-whitelist.';
        }

        $document = $schema['document'] ?? null;

        if (! is_array($document) || array_is_list($document)) {
            $errors[] = 'Bagian document wajib berupa object.';
        } else {
            $this->validateExactKeys('document', $document, OfferTemplateSchemaV2::DOCUMENT_KEYS, $errors);

            foreach (OfferTemplateSchemaV2::DOCUMENT_KEYS as $field) {
                $this->validateSafeText($document[$field] ?? null, "document.{$field}", 5000, $errors);
            }
        }

        $defaults = $schema['defaults'] ?? null;

        if (! is_array($defaults) || array_is_list($defaults)) {
            $errors[] = 'Bagian defaults wajib berupa object.';
        } else {
            $this->validateExactKeys('defaults', $defaults, OfferTemplateSchemaV2::DEFAULT_KEYS, $errors);
            $this->validateDefaults($defaults, $errors);
        }

        $clauses = $schema['clauses'] ?? null;

        if (! is_array($clauses) || array_is_list($clauses)) {
            $errors[] = 'Bagian clauses wajib berupa object dengan tepat 25 klausul.';
        } else {
            $actualKeys = array_keys($clauses);
            $missing = array_values(array_diff($expectedClauseKeys, $actualKeys));
            $unknown = array_values(array_diff($actualKeys, $expectedClauseKeys));

            if (count($actualKeys) !== 25 || $missing !== []) {
                $errors[] = 'Schema v2 wajib memiliki tepat 25 klausul: '.implode(', ', $missing).'.';
            }

            if ($unknown !== []) {
                $errors[] = 'Klausul tidak dikenal: '.implode(', ', $unknown).'.';
            }

            foreach ($expectedClauseKeys as $key) {
                if (array_key_exists($key, $clauses)) {
                    $this->validateV2Clause($key, $clauses[$key], $errors);
                }
            }
        }

        $constraints = $schema['constraints'] ?? null;

        if (! is_array($constraints) || array_is_list($constraints)) {
            $errors[] = 'Bagian constraints wajib berupa object.';
        } else {
            $this->validateExactKeys('constraints', $constraints, OfferTemplateSchemaV2::CONSTRAINT_KEYS, $errors);
            $this->validateConstraints($constraints, $defaults, $errors);
        }

        if (OfferDocumentContentGuard::containsProvisionalMarker($schema)) {
            $errors[] = 'Schema template approved tidak boleh memuat penanda DRAF atau provisional.';
        }

        return array_values(array_unique($errors));
    }

    /** @param list<string> $errors */
    private function validateLegacyClause(string $key, mixed $clause, array &$errors): void
    {
        if (! is_array($clause)) {
            $errors[] = "Klausul {$key} wajib berupa object.";

            return;
        }

        $unknownKeys = array_values(array_diff(array_keys($clause), ['paragraphs', 'items']));

        if ($unknownKeys !== []) {
            $errors[] = "Field klausul {$key} tidak dikenal: ".implode(', ', $unknownKeys).'.';
        }

        $paragraphs = $clause['paragraphs'] ?? [];
        $items = $clause['items'] ?? [];

        if (! is_array($paragraphs) || ! array_is_list($paragraphs)) {
            $errors[] = "Klausul {$key}.paragraphs wajib berupa list teks.";
            $paragraphs = [];
        }

        if (! is_array($items) || ! array_is_list($items)) {
            $errors[] = "Klausul {$key}.items wajib berupa list teks.";
            $items = [];
        }

        if ($paragraphs === [] && $items === []) {
            $errors[] = "Klausul {$key} wajib memiliki paragraph atau item.";
        }

        foreach (array_merge($paragraphs, $items) as $content) {
            if (! is_string($content) || trim($content) === '' || mb_strlen($content) > 2000) {
                $errors[] = "Konten klausul {$key} wajib berupa teks 1-2000 karakter.";
                break;
            }
        }
    }

    /** @param list<string> $errors */
    private function validateDefaults(array $defaults, array &$errors): void
    {
        foreach (['subject', 'ownership_form', 'purpose', 'valuation_basis'] as $field) {
            $this->validateSafeText($defaults[$field] ?? null, "defaults.{$field}", 1000, $errors);
        }

        if (! is_string($defaults['currency'] ?? null)
            || preg_match('/\A[A-Z]{3}\z/', $defaults['currency']) !== 1) {
            $errors[] = 'defaults.currency wajib berupa kode mata uang tiga huruf kapital.';
        }

        $this->validateEnumDefault($defaults, 'investigation_level', ['desktop', 'limited', 'full'], $errors);
        $this->validateEnumDefault($defaults, 'report_format', ['summary', 'complete'], $errors);
        $this->validateEnumDefault($defaults, 'report_language', ['id', 'en'], $errors);
        $this->validateEnumDefault($defaults, 'completion_day_type', ['business', 'calendar'], $errors);
        $this->validateEnumDefault($defaults, 'tax_inclusion', array_column(OfferTaxInclusion::cases(), 'value'), $errors);
        $this->validateEnumDefault($defaults, 'fee_presentation', array_column(OfferFeePresentation::cases(), 'value'), $errors);

        foreach ([
            'report_copies' => [1, 100],
            'completion_days' => [1, 365],
            'ppn_rate_bps' => [0, 10_000],
            'pph_rate_bps' => [0, 10_000],
        ] as $field => [$minimum, $maximum]) {
            $value = $defaults[$field] ?? null;

            if (! is_int($value) || $value < $minimum || $value > $maximum) {
                $errors[] = "defaults.{$field} wajib berupa integer {$minimum}-{$maximum}.";
            }
        }

        $this->validateSafeTextList($defaults['cost_inclusions'] ?? null, 'defaults.cost_inclusions', 50, $errors, true);

        if (($defaults['special_assumptions'] ?? null) !== null) {
            $this->validateSafeText($defaults['special_assumptions'], 'defaults.special_assumptions', 5000, $errors);
        }

        $this->validatePaymentTerms($defaults['payment_terms'] ?? null, $errors);
        $this->validateRequirements($defaults['requirements'] ?? null, $errors);
    }

    /** @param list<string> $errors */
    private function validatePaymentTerms(mixed $terms, array &$errors): void
    {
        if (! is_array($terms) || ! array_is_list($terms) || $terms === [] || count($terms) > 20) {
            $errors[] = 'defaults.payment_terms wajib berupa list berisi 1-20 termin.';

            return;
        }

        $total = 0;

        foreach ($terms as $index => $term) {
            if (! is_array($term) || array_is_list($term)) {
                $errors[] = "defaults.payment_terms.{$index} wajib berupa object.";

                continue;
            }

            $this->validateAllowedAndRequiredKeys(
                "defaults.payment_terms.{$index}",
                $term,
                ['percentage_bps', 'trigger_text', 'due_days'],
                ['percentage_bps', 'trigger_text'],
                $errors,
            );
            $percentage = $term['percentage_bps'] ?? null;

            if (! is_int($percentage) || $percentage < 1 || $percentage > 10_000) {
                $errors[] = "defaults.payment_terms.{$index}.percentage_bps wajib 1-10000.";
            } else {
                $total += $percentage;
            }

            $this->validateSafeText($term['trigger_text'] ?? null, "defaults.payment_terms.{$index}.trigger_text", 1000, $errors);

            if (array_key_exists('due_days', $term)
                && $term['due_days'] !== null
                && (! is_int($term['due_days']) || $term['due_days'] < 0 || $term['due_days'] > 3650)) {
                $errors[] = "defaults.payment_terms.{$index}.due_days wajib null atau integer 0-3650.";
            }
        }

        if ($total !== 10_000) {
            $errors[] = 'Total defaults.payment_terms harus tepat 10000 basis point.';
        }
    }

    /** @param list<string> $errors */
    private function validateRequirements(mixed $requirements, array &$errors): void
    {
        if (! is_array($requirements) || ! array_is_list($requirements) || $requirements === [] || count($requirements) > 100) {
            $errors[] = 'defaults.requirements wajib berupa list berisi 1-100 persyaratan.';

            return;
        }

        foreach ($requirements as $index => $requirement) {
            if (! is_array($requirement) || array_is_list($requirement)) {
                $errors[] = "defaults.requirements.{$index} wajib berupa object.";

                continue;
            }

            $this->validateAllowedAndRequiredKeys(
                "defaults.requirements.{$index}",
                $requirement,
                ['requirement_code', 'description', 'emphasis_style'],
                ['description', 'emphasis_style'],
                $errors,
            );

            if (($requirement['requirement_code'] ?? null) !== null) {
                $this->validateSafeText($requirement['requirement_code'], "defaults.requirements.{$index}.requirement_code", 64, $errors);
            }

            $this->validateSafeText($requirement['description'] ?? null, "defaults.requirements.{$index}.description", 2000, $errors);

            if (! in_array($requirement['emphasis_style'] ?? null, ['normal', 'bold', 'italic', 'underline'], true)) {
                $errors[] = "defaults.requirements.{$index}.emphasis_style tidak valid.";
            }
        }
    }

    /** @param list<string> $errors */
    private function validateV2Clause(string $key, mixed $clause, array &$errors): void
    {
        if (! is_array($clause) || array_is_list($clause)) {
            $errors[] = "Klausul {$key} wajib berupa object.";

            return;
        }

        $this->validateExactKeys("clauses.{$key}", $clause, ['blocks'], $errors);
        $blocks = $clause['blocks'] ?? null;

        if (! is_array($blocks) || ! array_is_list($blocks) || $blocks === [] || count($blocks) > 50) {
            $errors[] = "clauses.{$key}.blocks wajib berupa list berisi 1-50 blok.";

            return;
        }

        foreach ($blocks as $index => $block) {
            $this->validateBlock($key, $index, $block, $errors);
        }
    }

    /** @param list<string> $errors */
    private function validateBlock(string $clauseKey, int $index, mixed $block, array &$errors): void
    {
        $path = "clauses.{$clauseKey}.blocks.{$index}";

        if (! is_array($block) || array_is_list($block)) {
            $errors[] = "{$path} wajib berupa object.";

            return;
        }

        $type = $block['type'] ?? null;
        $allowedTypes = array_column(OfferTemplateBlockType::cases(), 'value');

        if (! is_string($type) || ! in_array($type, $allowedTypes, true)) {
            $errors[] = "{$path}.type tidak dikenal.";

            return;
        }

        if (array_key_exists('when', $block)
            && ! in_array($block['when'], array_column(OfferTemplateCondition::cases(), 'value'), true)) {
            $errors[] = "{$path}.when tidak dikenal.";
        }

        $baseKeys = ['type', 'when'];

        if ($type === OfferTemplateBlockType::Text->value) {
            $this->validateAllowedAndRequiredKeys($path, $block, [...$baseKeys, 'text'], ['type', 'text'], $errors);
            $this->validateSafeText($block['text'] ?? null, "{$path}.text", 5000, $errors);

            return;
        }

        if ($type === OfferTemplateBlockType::Bullets->value) {
            $this->validateAllowedAndRequiredKeys($path, $block, [...$baseKeys, 'items'], ['type', 'items'], $errors);
            $this->validateSafeTextList($block['items'] ?? null, "{$path}.items", 100, $errors);

            return;
        }

        if ($type === OfferTemplateBlockType::Dynamic->value) {
            $this->validateAllowedAndRequiredKeys($path, $block, [...$baseKeys, 'source'], ['type', 'source'], $errors);

            if (! in_array($block['source'] ?? null, array_column(OfferTemplateDynamicSource::cases(), 'value'), true)) {
                $errors[] = "{$path}.source tidak dikenal.";
            }

            return;
        }

        $this->validateAllowedAndRequiredKeys($path, $block, $baseKeys, ['type'], $errors);
    }

    /** @param list<string> $errors */
    private function validateConstraints(array $constraints, mixed $defaults, array &$errors): void
    {
        $fields = $constraints['required_engagement_fields'] ?? null;

        if (! is_array($fields) || ! array_is_list($fields) || $fields === []) {
            $errors[] = 'constraints.required_engagement_fields wajib berupa list yang terisi.';
        } else {
            $unknown = array_values(array_diff($fields, OfferTemplateSchemaV2::REQUIRED_ENGAGEMENT_FIELDS));
            $missing = array_values(array_diff(OfferTemplateSchemaV2::REQUIRED_ENGAGEMENT_FIELDS, $fields));

            if ($unknown !== []) {
                $errors[] = 'Field engagement wajib tidak dikenal: '.implode(', ', $unknown).'.';
            }

            if ($missing !== []) {
                $errors[] = 'Field engagement wajib belum lengkap: '.implode(', ', $missing).'.';
            }

            if (count($fields) !== count(array_unique($fields))) {
                $errors[] = 'constraints.required_engagement_fields tidak boleh duplikat.';
            }
        }

        foreach (['purpose_must_equal', 'valuation_basis_must_equal'] as $field) {
            $this->validateSafeText($constraints[$field] ?? null, "constraints.{$field}", 1000, $errors);
        }

        if (is_array($defaults)) {
            if (($constraints['purpose_must_equal'] ?? null) !== ($defaults['purpose'] ?? null)) {
                $errors[] = 'constraints.purpose_must_equal harus sama dengan defaults.purpose.';
            }

            if (($constraints['valuation_basis_must_equal'] ?? null) !== ($defaults['valuation_basis'] ?? null)) {
                $errors[] = 'constraints.valuation_basis_must_equal harus sama dengan defaults.valuation_basis.';
            }
        }

        foreach (['required_asset_document', 'require_fee_per_asset', 'requires_liquidation_value', 'requires_exposure_table'] as $field) {
            if (! is_bool($constraints[$field] ?? null)) {
                $errors[] = "constraints.{$field} wajib berupa boolean.";
            }
        }
    }

    /** @param list<string> $errors */
    private function validateExactKeys(string $path, array $value, array $expected, array &$errors): void
    {
        $missing = array_values(array_diff($expected, array_keys($value)));
        $unknown = array_values(array_diff(array_keys($value), $expected));

        if ($missing !== []) {
            $errors[] = "{$path} belum memiliki field wajib: ".implode(', ', $missing).'.';
        }

        if ($unknown !== []) {
            $errors[] = "{$path} memiliki field tidak dikenal: ".implode(', ', $unknown).'.';
        }
    }

    /** @param list<string> $errors */
    private function validateAllowedAndRequiredKeys(
        string $path,
        array $value,
        array $allowed,
        array $required,
        array &$errors,
    ): void {
        $missing = array_values(array_diff($required, array_keys($value)));
        $unknown = array_values(array_diff(array_keys($value), $allowed));

        if ($missing !== []) {
            $errors[] = "{$path} belum memiliki field wajib: ".implode(', ', $missing).'.';
        }

        if ($unknown !== []) {
            $errors[] = "{$path} memiliki field tidak dikenal: ".implode(', ', $unknown).'.';
        }
    }

    /** @param list<string> $errors */
    private function validateEnumDefault(array $defaults, string $field, array $allowed, array &$errors): void
    {
        if (! in_array($defaults[$field] ?? null, $allowed, true)) {
            $errors[] = "defaults.{$field} tidak valid.";
        }
    }

    /** @param list<string> $errors */
    private function validateSafeText(mixed $value, string $path, int $maximum, array &$errors): void
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > $maximum) {
            $errors[] = "{$path} wajib berupa teks 1-{$maximum} karakter.";

            return;
        }

        if (preg_match('/<\?(?:php|=)?|\?>|{!!|!!}|@(?:php|endphp|inject|include|extends|section|yield|component|livewire)\b/i', $value) === 1
            || preg_match('/<\s*\/?\s*[a-z!][^>]*>/i', $value) === 1) {
            $errors[] = "{$path} tidak boleh memuat HTML, Blade, atau PHP.";
        }

        preg_match_all('/{{\s*([^{}]+?)\s*}}/u', $value, $matches);

        foreach ($matches[1] ?? [] as $token) {
            if (! in_array($token, OfferTemplateSchemaV2::TOKENS, true)) {
                $errors[] = "{$path} memakai token tidak dikenal: {$token}.";
            }
        }

        $withoutTokenSyntax = preg_replace('/{{\s*[^{}]+?\s*}}/u', '', $value);

        if (is_string($withoutTokenSyntax)
            && (str_contains($withoutTokenSyntax, '{{') || str_contains($withoutTokenSyntax, '}}'))) {
            $errors[] = "{$path} memiliki sintaks token yang tidak valid.";
        }
    }

    /** @param list<string> $errors */
    private function validateSafeTextList(
        mixed $value,
        string $path,
        int $maximumItems,
        array &$errors,
        bool $allowEmpty = false,
    ): void {
        if (! is_array($value) || ! array_is_list($value) || (! $allowEmpty && $value === []) || count($value) > $maximumItems) {
            $errors[] = "{$path} wajib berupa list teks dengan maksimal {$maximumItems} item.";

            return;
        }

        foreach ($value as $index => $item) {
            $this->validateSafeText($item, "{$path}.{$index}", 2000, $errors);
        }
    }

    /** @return list<string> */
    private function schemaBlockTypes(array $clauses): array
    {
        $types = [];

        foreach ($clauses as $clause) {
            foreach (is_array($clause['blocks'] ?? null) ? $clause['blocks'] : [] as $block) {
                if (is_array($block) && is_string($block['type'] ?? null)) {
                    $types[] = $block['type'];
                }
            }
        }

        return array_values(array_unique($types));
    }

    private function assertOfficialLetterhead(IssuerProfileVersion $master): void
    {
        $path = $master->letterhead_path;

        if (! is_string($path)
            || trim($path) === ''
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('/\A[A-Za-z]:/', $path) === 1
            || in_array('..', explode('/', $path), true)) {
            throw new DomainException('Path letterhead wajib berupa path relatif yang aman pada penyimpanan privat.');
        }

        if (! is_string($master->letterhead_sha256)
            || preg_match('/\A[a-f0-9]{64}\z/i', $master->letterhead_sha256) !== 1) {
            throw new DomainException('Hash SHA-256 letterhead tidak valid.');
        }

        if (! in_array($master->letterhead_mime, ['image/png', 'image/jpeg'], true)) {
            throw new DomainException('Letterhead wajib berformat PNG atau JPEG.');
        }

        if (! is_int($master->letterhead_width_px)
            || $master->letterhead_width_px < 300
            || $master->letterhead_width_px > 10_000
            || ! is_int($master->letterhead_height_px)
            || $master->letterhead_height_px < 50
            || $master->letterhead_height_px > 5_000) {
            throw new DomainException('Dimensi letterhead tidak valid.');
        }

        if (! is_int($master->letterhead_size_bytes)
            || $master->letterhead_size_bytes < 1
            || $master->letterhead_size_bytes > 10 * 1024 * 1024) {
            throw new DomainException('Ukuran letterhead harus berada dalam rentang 1 byte sampai 10 MB.');
        }

        $configuredRoot = config('offer-documents.renderer.approved_asset_path');
        $root = is_string($configuredRoot) ? realpath($configuredRoot) : false;

        if ($root === false || ! is_dir($root)) {
            throw new DomainException('Direktori aset master dokumen privat belum tersedia.');
        }

        $absolutePath = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/').'/';
        $normalizedPath = $absolutePath === false ? '' : str_replace('\\', '/', $absolutePath);
        $insideRoot = DIRECTORY_SEPARATOR === '\\'
            ? str_starts_with(mb_strtolower($normalizedPath), mb_strtolower($normalizedRoot))
            : str_starts_with($normalizedPath, $normalizedRoot);

        if ($absolutePath === false || ! $insideRoot || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new DomainException('File letterhead tidak ditemukan pada penyimpanan privat yang disetujui.');
        }

        $actualSize = filesize($absolutePath);
        $actualHash = hash_file('sha256', $absolutePath);
        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
        $imageInfo = @getimagesize($absolutePath);

        if (! is_int($actualSize) || $actualSize !== $master->letterhead_size_bytes) {
            throw new DomainException('Ukuran file letterhead tidak sesuai dengan metadata master.');
        }

        if (! is_string($actualHash) || ! hash_equals(mb_strtolower($master->letterhead_sha256), $actualHash)) {
            throw new DomainException('Hash file letterhead tidak sesuai dengan metadata master.');
        }

        if (! is_array($imageInfo)
            || $actualMime !== $master->letterhead_mime
            || ($imageInfo['mime'] ?? null) !== $master->letterhead_mime
            || ($imageInfo[0] ?? null) !== $master->letterhead_width_px
            || ($imageInfo[1] ?? null) !== $master->letterhead_height_px) {
            throw new DomainException('MIME atau dimensi file letterhead tidak sesuai dengan metadata master.');
        }
    }

    /** @return array<string, mixed> */
    private function contentPayload(Model $master): array
    {
        if ($master instanceof OfferTemplateVersion) {
            // This prefix is byte-for-byte compatible with the legacy checksum.
            $payload = [
                'type' => 'offer_template_version',
                'offer_template_id' => $master->offer_template_id,
                'version_no' => $master->version_no,
                'schema_version' => $master->schema_version,
                'clause_schema' => $master->clause_schema,
                'condition_schema' => $master->condition_schema,
                'layout_version' => $master->layout_version,
                'header_mode' => $master->header_mode,
                'effective_from' => $master->effective_from?->format('Y-m-d'),
            ];

            if ($master->schema_version === 1) {
                return $payload;
            }

            $category = $master->template?->category;

            return [
                ...$payload,
                'template_code' => $master->template?->code,
                'template_category' => $category instanceof OfferTemplateCategory ? $category->value : $category,
                'effective_until' => $master->effective_until?->format('Y-m-d'),
            ];
        }

        if ($master instanceof IssuerProfileVersion) {
            $payload = [
                'type' => 'issuer_profile_version',
                'branch_id' => $master->branch_id,
                'version_no' => $master->version_no,
                'legal_name' => $master->legal_name,
                'permit_no' => $master->permit_no,
                'office_label' => $master->office_label,
                'address' => $master->address,
                'city' => $master->city,
                'phone' => $master->phone,
                'email' => $master->email,
                'letterhead_path' => $master->letterhead_path,
                'letterhead_sha256' => $master->letterhead_sha256,
                'letterhead_mime' => $master->letterhead_mime,
                'effective_from' => $master->effective_from?->format('Y-m-d'),
                'effective_until' => $master->effective_until?->format('Y-m-d'),
            ];

            // Do not append nullable v2 fields to a legacy payload: existing
            // approved checksums must continue to verify after the migration.
            if ($master->letterhead_width_px !== null
                || $master->letterhead_height_px !== null
                || $master->letterhead_size_bytes !== null) {
                $payload['letterhead_width_px'] = $master->letterhead_width_px;
                $payload['letterhead_height_px'] = $master->letterhead_height_px;
                $payload['letterhead_size_bytes'] = $master->letterhead_size_bytes;
            }

            return $payload;
        }

        if ($master instanceof DocumentSignerVersion) {
            return [
                'type' => 'document_signer_version',
                'branch_id' => $master->branch_id,
                'signer_key' => $master->signer_key,
                'version_no' => $master->version_no,
                'full_name' => $master->full_name,
                'title_suffix' => $master->title_suffix,
                'position' => $master->position,
                'permit_no' => $master->permit_no,
                'registration_no' => $master->registration_no,
                'phone' => $master->phone,
                'email' => $master->email,
                'effective_from' => $master->effective_from?->format('Y-m-d'),
                'effective_until' => $master->effective_until?->format('Y-m-d'),
            ];
        }

        throw new DomainException('Jenis master dokumen tidak didukung.');
    }

    private function assertSupported(Model $master): void
    {
        if (! $master instanceof OfferTemplateVersion
            && ! $master instanceof IssuerProfileVersion
            && ! $master instanceof DocumentSignerVersion) {
            throw new DomainException('Jenis master dokumen tidak didukung.');
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
