<?php

namespace App\Services\Offers;

use App\Enums\OfferDocumentOutputMode;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use LogicException;

class OfferDocumentRenderer
{
    private const LAYOUT_V1 = 'standard-v1';

    private const LAYOUT_V2 = 'offer-a4-v2';

    private const BLOCK_TYPES = [
        'text', 'bullets', 'dynamic', 'asset_list', 'fee_summary',
        'fee_table', 'payment_terms', 'requirements', 'exposure_table',
    ];

    private const LETTERHEAD_MIMES = ['image/png', 'image/jpeg'];

    public function render(
        array $snapshot,
        OfferDocumentOutputMode $mode = OfferDocumentOutputMode::Draft,
    ): string {
        $this->assertPrintOnlyContract();
        $snapshot = $this->validatedSnapshot($snapshot);
        $this->assertOutputModeContract($snapshot, $mode);

        $dompdf = new Dompdf($this->options());
        $dompdf->setBasePath(resource_path('views/pdf/offers'));
        $dompdf->loadHtml($this->renderValidatedHtml($snapshot, $mode), 'UTF-8');
        $dompdf->setPaper(
            (string) config('offer-documents.renderer.paper', 'a4'),
            (string) config('offer-documents.renderer.orientation', 'portrait'),
        );
        $dompdf->render();
        $this->decorateDocumentMetadata($dompdf, $snapshot, $mode);

        return $dompdf->output([
            'compress' => (bool) config('offer-documents.renderer.compress', true),
        ]);
    }

    public function renderHtml(
        array $snapshot,
        OfferDocumentOutputMode $mode = OfferDocumentOutputMode::Draft,
    ): string {
        $this->assertPrintOnlyContract();
        $snapshot = $this->validatedSnapshot($snapshot);
        $this->assertOutputModeContract($snapshot, $mode);

        return $this->renderValidatedHtml($snapshot, $mode);
    }

    private function assertPrintOnlyContract(): void
    {
        $output = (array) config('offer-documents.output', []);

        if (($output['workflow'] ?? null) !== 'physical_print'
            || ($output['embedded_signature'] ?? null) !== false
            || ($output['embedded_stamp'] ?? null) !== false
            || ($output['signed_scan'] ?? null) !== false
            || ($output['digital_delivery'] ?? null) !== false) {
            throw new LogicException('Renderer penawaran hanya mendukung PDF tanpa tanda tangan/stempel digital untuk proses cetak fisik.');
        }
    }

    private function renderValidatedHtml(array $snapshot, OfferDocumentOutputMode $mode): string
    {
        return view('pdf.offers.standard', [
            'snapshot' => $snapshot,
            'printCss' => File::get(resource_path('views/pdf/offers/print.css')),
            'showDraftWatermark' => $mode === OfferDocumentOutputMode::Draft,
        ])->render();
    }

    private function options(): Options
    {
        $tempPath = (string) config('offer-documents.renderer.temp_path');
        $fontCachePath = (string) config('offer-documents.renderer.font_cache_path');
        $approvedAssetPath = (string) config('offer-documents.renderer.approved_asset_path');

        foreach ([$tempPath, $fontCachePath, $approvedAssetPath] as $path) {
            File::ensureDirectoryExists($path);
        }

        $options = new Options;
        $options->setDefaultMediaType('print');
        $options->setDefaultPaperSize((string) config('offer-documents.renderer.paper', 'a4'));
        $options->setDefaultPaperOrientation((string) config('offer-documents.renderer.orientation', 'portrait'));
        $options->setDefaultFont((string) config('offer-documents.renderer.default_font', 'DejaVu Sans'));
        $options->setDpi((int) config('offer-documents.renderer.dpi', 96));
        $options->setTempDir($tempPath);
        $options->setFontCache($fontCachePath);
        $options->setChroot([resource_path('views/pdf/offers'), $approvedAssetPath]);
        $options->setAllowedProtocols(['file://']);
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);

        return $options;
    }

    /**
     * Letterheads are fixed HTML elements, repeated by Dompdf on every page.
     * The canvas only receives metadata: no footer or page number is emitted.
     */
    private function decorateDocumentMetadata(
        Dompdf $dompdf,
        array $snapshot,
        OfferDocumentOutputMode $mode,
    ): void {
        $canvas = $dompdf->getCanvas();
        $canvas->add_info('Title', $snapshot['document']['subject'].' - '.$snapshot['document']['number']);
        $canvas->add_info('Author', $snapshot['issuer']['name']);
        $canvas->add_info(
            'Subject',
            $mode === OfferDocumentOutputMode::Draft
                ? 'Draf dokumen penawaran jasa penilaian'
                : 'Dokumen penawaran jasa penilaian siap cetak',
        );
    }

    private function assertOutputModeContract(array $snapshot, OfferDocumentOutputMode $mode): void
    {
        if ($mode !== OfferDocumentOutputMode::PrintReady) {
            return;
        }

        $metadata = $snapshot['metadata'];
        $requiresLetterhead = ($metadata['template']['schema_version'] ?? null) === 2
            || ($metadata['template']['layout_version'] ?? null) === self::LAYOUT_V2
            || ($metadata['schema_version'] ?? null) === 2;

        if (($metadata['number_allocation']['status'] ?? null) !== 'allocated'
            || ! $this->isApprovedMaster($metadata['template'] ?? null, true)
            || ! $this->isApprovedMaster($metadata['issuer_profile'] ?? null)
            || ! $this->isApprovedMaster($metadata['signer'] ?? null)
            || ($metadata['uses_provisional_copy'] ?? true) !== false
            || ($metadata['uses_provisional_issuer'] ?? true) !== false
            || ($requiresLetterhead && ($snapshot['issuer']['letterhead']['verified'] ?? false) !== true)
            || OfferDocumentContentGuard::containsProvisionalMarker([
                $snapshot['document'],
                $snapshot['issuer']['name'],
                $snapshot['issuer']['address_lines'],
                $snapshot['issuer']['contact_lines'],
                $snapshot['recipient'],
                $snapshot['clauses'],
                $snapshot['signatures'],
            ])) {
            throw new InvalidArgumentException(
                'PDF siap cetak memerlukan nomor, template, profil penerbit, letterhead resmi, dan penandatangan resmi tanpa konten provisional.',
            );
        }
    }

    private function isApprovedMaster(mixed $master, bool $requiresActiveTemplate = false): bool
    {
        if (! is_array($master)
            || ($master['status'] ?? null) !== 'approved'
            || empty($master['approved_by'])
            || empty($master['approved_at'])
            || ($master['is_effective'] ?? false) !== true
            || ($master['integrity_valid'] ?? false) !== true
            || ! is_string($master['checksum'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/i', $master['checksum']) !== 1) {
            return false;
        }

        return ! $requiresActiveTemplate || (
            ($master['template_active'] ?? false) === true
            && ($master['schema_valid'] ?? false) === true
            && in_array($master['layout_version'] ?? self::LAYOUT_V1, [self::LAYOUT_V1, self::LAYOUT_V2], true)
        );
    }

    /** @return array<string, mixed> */
    private function validatedSnapshot(array $snapshot): array
    {
        foreach (['document', 'issuer', 'recipient', 'clauses', 'signatures'] as $section) {
            if (! isset($snapshot[$section]) || ! is_array($snapshot[$section])) {
                throw new InvalidArgumentException("Snapshot penawaran memerlukan bagian {$section}.");
            }
        }

        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $layout = $metadata['template']['layout_version'] ?? self::LAYOUT_V1;

        if (! is_string($layout) || ! in_array($layout, [self::LAYOUT_V1, self::LAYOUT_V2], true)) {
            throw new InvalidArgumentException('Versi layout snapshot penawaran tidak didukung.');
        }

        $document = $this->strings($snapshot['document'], [
            'number', 'place', 'date', 'subject', 'opening', 'closing',
        ], 'document');
        $issuer = [
            ...$this->strings($snapshot['issuer'], ['name'], 'issuer'),
            'address_lines' => $this->stringList($snapshot['issuer']['address_lines'] ?? null, 'issuer.address_lines'),
            'contact_lines' => $this->stringList($snapshot['issuer']['contact_lines'] ?? [], 'issuer.contact_lines', false),
            'letterhead' => $this->validatedLetterhead($snapshot['issuer']['letterhead'] ?? null),
        ];
        $recipient = [
            ...$this->strings($snapshot['recipient'], ['name'], 'recipient'),
            'attention' => $this->optionalString($snapshot['recipient']['attention'] ?? null, 'recipient.attention'),
            'address_lines' => $this->stringList($snapshot['recipient']['address_lines'] ?? null, 'recipient.address_lines'),
        ];
        $signatures = [
            ...$this->strings($snapshot['signatures'], ['issuer_name', 'issuer_title', 'client_name'], 'signatures'),
            'issuer_permit_no' => $this->optionalString($snapshot['signatures']['issuer_permit_no'] ?? null, 'signatures.issuer_permit_no'),
            'issuer_registration_no' => $this->optionalString($snapshot['signatures']['issuer_registration_no'] ?? null, 'signatures.issuer_registration_no'),
            'client_title' => $this->optionalString($snapshot['signatures']['client_title'] ?? null, 'signatures.client_title'),
        ];

        $metadata['schema_version'] = (int) ($metadata['schema_version'] ?? ($metadata['template']['schema_version'] ?? 1));
        $metadata['template'] = is_array($metadata['template'] ?? null) ? $metadata['template'] : [];
        $metadata['template']['layout_version'] = $layout;

        return [
            'document' => $document,
            'issuer' => $issuer,
            'recipient' => $recipient,
            'clauses' => $this->validatedClauses($snapshot['clauses']),
            'signatures' => $signatures,
            'metadata' => $metadata,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function validatedClauses(array $clauses): array
    {
        $expectedTitles = (array) config('offer-documents.clause_titles', []);

        if (! array_is_list($clauses) || count($clauses) !== count($expectedTitles) || count($clauses) !== 25) {
            throw new InvalidArgumentException('Snapshot penawaran harus memuat tepat 25 klausul terurut.');
        }

        $validated = [];

        foreach (array_values($expectedTitles) as $index => $expectedTitle) {
            $clause = $clauses[$index];
            $expectedKey = array_keys($expectedTitles)[$index];
            $expectedNumber = $index + 1;

            if (! is_array($clause)
                || ($clause['number'] ?? null) !== $expectedNumber
                || ($clause['key'] ?? null) !== $expectedKey
                || ($clause['title'] ?? null) !== $expectedTitle) {
                throw new InvalidArgumentException("Klausul {$expectedNumber} tidak sesuai urutan template.");
            }

            $paragraphs = $this->stringList($clause['paragraphs'] ?? [], "clauses.{$expectedKey}.paragraphs", false);
            $items = $this->stringList($clause['items'] ?? [], "clauses.{$expectedKey}.items", false);
            $blocks = $clause['blocks'] ?? null;

            if ($blocks === null) {
                $blocks = array_map(static fn (string $text): array => ['type' => 'text', 'text' => $text], $paragraphs);

                if ($items !== []) {
                    $blocks[] = ['type' => 'bullets', 'items' => $items];
                }
            }

            if (! is_array($blocks) || ! array_is_list($blocks) || $blocks === []) {
                throw new InvalidArgumentException("Klausul {$expectedNumber} harus memiliki blok isi.");
            }

            $validatedBlocks = [];

            foreach ($blocks as $blockIndex => $block) {
                $validatedBlocks[] = $this->validatedBlock($block, "clauses.{$expectedKey}.blocks.{$blockIndex}");
            }

            $validated[] = [
                'number' => $expectedNumber,
                'key' => $expectedKey,
                'title' => $expectedTitle,
                'paragraphs' => $paragraphs,
                'items' => $items,
                'blocks' => $validatedBlocks,
            ];
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function validatedBlock(mixed $block, string $path): array
    {
        if (! is_array($block) || ! is_string($block['type'] ?? null)) {
            throw new InvalidArgumentException("Nilai {$path} wajib berupa blok bertipe.");
        }

        $type = $block['type'];

        if (! in_array($type, self::BLOCK_TYPES, true)) {
            throw new InvalidArgumentException("Tipe blok {$path} tidak didukung.");
        }

        return match ($type) {
            'text' => ['type' => $type, 'text' => $this->requiredString($block['text'] ?? null, "{$path}.text")],
            'bullets' => ['type' => $type, 'items' => $this->stringList($block['items'] ?? null, "{$path}.items")],
            'dynamic' => $this->validatedDynamicBlock($block, $path),
            'asset_list' => ['type' => $type, 'rows' => $this->validatedRows($block['rows'] ?? null, $path, ['number', 'subject', 'asset', 'location', 'documents'])],
            'fee_summary' => [
                'type' => $type,
                'rows' => $this->validatedRows($block['rows'] ?? null, $path, ['label', 'value']),
                'amount_in_words' => $this->optionalString($block['amount_in_words'] ?? null, "{$path}.amount_in_words"),
            ],
            'fee_table' => ['type' => $type, 'rows' => $this->validatedRows($block['rows'] ?? null, $path, ['number', 'asset', 'label', 'quantity', 'unit_amount', 'line_total'])],
            'payment_terms' => ['type' => $type, 'rows' => $this->validatedRows($block['rows'] ?? null, $path, ['number', 'percentage', 'trigger', 'due', 'amount'])],
            'requirements' => ['type' => $type, 'rows' => $this->validatedRows($block['rows'] ?? null, $path, ['number', 'code', 'description', 'emphasis'])],
            'exposure_table' => [
                'type' => $type,
                'rows' => $this->validatedRows($block['rows'] ?? null, $path, ['number', 'asset', 'exposure', 'market_value', 'liquidation_value', 'discount'], false),
                'empty_message' => $this->optionalString($block['empty_message'] ?? null, "{$path}.empty_message")
                    ?? 'Data exposure dan nilai likuidasi belum tersedia.',
            ],
        };
    }

    /** @return array<string, mixed> */
    private function validatedDynamicBlock(array $block, string $path): array
    {
        $source = $this->requiredString($block['source'] ?? null, "{$path}.source");
        $hasText = is_string($block['text'] ?? null) && trim($block['text']) !== '';
        $hasItems = is_array($block['items'] ?? null) && $block['items'] !== [];

        if ($hasText === $hasItems) {
            throw new InvalidArgumentException("Blok {$path} wajib memiliki tepat salah satu hasil text atau items.");
        }

        return $hasText
            ? ['type' => 'dynamic', 'source' => $source, 'text' => $this->requiredString($block['text'], "{$path}.text")]
            : ['type' => 'dynamic', 'source' => $source, 'items' => $this->stringList($block['items'], "{$path}.items")];
    }

    /** @return list<array<string, string>> */
    private function validatedRows(mixed $rows, string $path, array $keys, bool $required = true): array
    {
        if (! is_array($rows) || ! array_is_list($rows) || ($required && $rows === [])) {
            throw new InvalidArgumentException("Nilai {$path}.rows wajib berupa daftar baris".($required ? ' terisi.' : '.'));
        }

        if (count($rows) > 500) {
            throw new InvalidArgumentException("Nilai {$path}.rows melebihi batas 500 baris.");
        }

        $validated = [];

        foreach ($rows as $rowIndex => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException("Nilai {$path}.rows.{$rowIndex} wajib berupa object.");
            }

            $validatedRow = [];

            foreach ($keys as $key) {
                $value = $row[$key] ?? '';

                if (! is_scalar($value) && $value !== null) {
                    throw new InvalidArgumentException("Nilai {$path}.rows.{$rowIndex}.{$key} wajib berupa teks.");
                }

                $value = trim((string) ($value ?? ''));

                if (mb_strlen($value) > 2000) {
                    throw new InvalidArgumentException("Nilai {$path}.rows.{$rowIndex}.{$key} terlalu panjang.");
                }

                $validatedRow[$key] = $value;
            }

            $validated[] = $validatedRow;
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function validatedLetterhead(mixed $letterhead): array
    {
        $empty = [
            'configured' => false, 'verified' => false, 'path' => null, 'uri' => null,
            'mime' => null, 'sha256' => null, 'width' => null, 'height' => null,
        ];

        if ($letterhead === null || $letterhead === []) {
            return $empty;
        }

        if (! is_array($letterhead)) {
            throw new InvalidArgumentException('Metadata letterhead wajib berupa object.');
        }

        $relativePath = $letterhead['path'] ?? null;
        $expectedMime = $letterhead['mime'] ?? null;
        $expectedHash = $letterhead['sha256'] ?? null;

        if (! is_string($relativePath) || trim($relativePath) === '') {
            return [...$empty, 'configured' => true];
        }

        if (! is_string($expectedMime)
            || ! in_array(mb_strtolower($expectedMime), self::LETTERHEAD_MIMES, true)
            || ! is_string($expectedHash)
            || preg_match('/\A[a-f0-9]{64}\z/i', $expectedHash) !== 1) {
            return [...$empty, 'configured' => true, 'path' => $relativePath];
        }

        $resolved = $this->resolveApprovedAsset($relativePath);

        if ($resolved === null) {
            return [...$empty, 'configured' => true, 'path' => $relativePath, 'mime' => $expectedMime, 'sha256' => $expectedHash];
        }

        $actualMime = File::mimeType($resolved);
        $dimensions = @getimagesize($resolved);
        $actualHash = hash_file('sha256', $resolved);
        $verified = is_string($actualMime)
            && in_array(mb_strtolower($actualMime), self::LETTERHEAD_MIMES, true)
            && hash_equals(mb_strtolower($expectedMime), mb_strtolower($actualMime))
            && is_string($actualHash)
            && hash_equals(mb_strtolower($expectedHash), mb_strtolower($actualHash))
            && is_array($dimensions)
            && ($dimensions[0] ?? 0) > 0
            && ($dimensions[1] ?? 0) > 0;

        return [
            'configured' => true,
            'verified' => $verified,
            'path' => $relativePath,
            'uri' => $verified ? $this->fileUri($resolved) : null,
            'mime' => $expectedMime,
            'sha256' => $expectedHash,
            'width' => $verified ? (int) $dimensions[0] : null,
            'height' => $verified ? (int) $dimensions[1] : null,
        ];
    }

    private function resolveApprovedAsset(string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath));

        if ($relativePath === ''
            || str_starts_with($relativePath, '/')
            || preg_match('/\A[a-z][a-z0-9+.-]*:/i', $relativePath) === 1
            || in_array('..', explode('/', $relativePath), true)) {
            return null;
        }

        $root = realpath((string) config('offer-documents.renderer.approved_asset_path'));
        $candidate = $root === false ? false : realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if ($root === false || $candidate === false || ! is_file($candidate)) {
            return null;
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $root), '/').'/';
        $candidatePath = str_replace('\\', '/', $candidate);
        $insideRoot = DIRECTORY_SEPARATOR === '\\'
            ? str_starts_with(mb_strtolower($candidatePath), mb_strtolower($rootPrefix))
            : str_starts_with($candidatePath, $rootPrefix);

        return $insideRoot ? $candidate : null;
    }

    private function fileUri(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, '/') ? 'file://'.$normalized : 'file:///'.$normalized;
    }

    /** @return array<string, string> */
    private function strings(array $source, array $keys, string $section): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->requiredString($source[$key] ?? null, "{$section}.{$key}");
        }

        return $values;
    }

    private function requiredString(mixed $value, string $path): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Nilai {$path} wajib berupa teks.");
        }

        $value = trim($value);

        if (mb_strlen($value) > 10_000) {
            throw new InvalidArgumentException("Nilai {$path} terlalu panjang.");
        }

        return $value;
    }

    private function optionalString(mixed $value, string $path): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("Nilai {$path} harus berupa teks atau null.");
        }

        return trim($value);
    }

    /** @return list<string> */
    private function stringList(mixed $values, string $path, bool $required = true): array
    {
        if (! is_array($values) || ! array_is_list($values) || ($required && $values === [])) {
            throw new InvalidArgumentException("Nilai {$path} harus berupa daftar teks.");
        }

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 2000) {
                throw new InvalidArgumentException("Setiap nilai {$path} wajib berupa teks maksimal 2000 karakter.");
            }
        }

        return array_map(static fn (string $value): string => trim($value), $values);
    }
}
