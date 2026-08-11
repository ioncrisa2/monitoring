<?php

namespace App\Services\Offers;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class OfferDocumentRenderer
{
    public function render(array $snapshot): string
    {
        $snapshot = $this->validatedSnapshot($snapshot);
        $dompdf = new Dompdf($this->options());
        $dompdf->setBasePath(resource_path('views/pdf/offers'));
        $dompdf->loadHtml($this->renderValidatedHtml($snapshot), 'UTF-8');
        $dompdf->setPaper(
            (string) config('offer-documents.renderer.paper', 'a4'),
            (string) config('offer-documents.renderer.orientation', 'portrait'),
        );
        $dompdf->render();

        $this->decoratePages($dompdf, $snapshot);

        return $dompdf->output([
            'compress' => (bool) config('offer-documents.renderer.compress', true),
        ]);
    }

    public function renderHtml(array $snapshot): string
    {
        return $this->renderValidatedHtml($this->validatedSnapshot($snapshot));
    }

    private function renderValidatedHtml(array $snapshot): string
    {
        return view('pdf.offers.standard', [
            'snapshot' => $snapshot,
            'printCss' => File::get(resource_path('views/pdf/offers/print.css')),
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
        $options->setChroot([
            resource_path('views/pdf/offers'),
            $approvedAssetPath,
        ]);
        $options->setAllowedProtocols(['file://']);
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);

        return $options;
    }

    private function decoratePages(Dompdf $dompdf, array $snapshot): void
    {
        $canvas = $dompdf->getCanvas();
        $canvas->add_info('Title', $snapshot['document']['subject'].' - '.$snapshot['document']['number']);
        $canvas->add_info('Author', $snapshot['issuer']['name']);
        $canvas->add_info('Subject', 'Draf dokumen penawaran jasa penilaian');

        $headerMode = (string) config('offer-documents.renderer.header_mode', 'odd_pages');
        $issuerName = $this->singleLine($snapshot['issuer']['name']);
        $issuerAddress = $this->singleLine(implode(' | ', $snapshot['issuer']['address_lines']));
        $issuerContact = $this->singleLine(implode(' | ', $snapshot['issuer']['contact_lines']));

        $canvas->page_script(static function (
            int $pageNumber,
            int $pageCount,
            Canvas $pageCanvas,
            FontMetrics $fontMetrics,
        ) use ($headerMode, $issuerName, $issuerAddress, $issuerContact): void {
            if ($headerMode === 'odd_pages' && $pageNumber % 2 === 0) {
                return;
            }

            $boldFont = $fontMetrics->getFont('DejaVu Sans', 'bold');
            $regularFont = $fontMetrics->getFont('DejaVu Sans', 'normal');

            $pageCanvas->text(
                self::millimetres(25),
                self::millimetres(8),
                mb_strimwidth($issuerName, 0, 88, '…'),
                $boldFont,
                12,
            );

            if ($issuerAddress !== '') {
                $pageCanvas->text(
                    self::millimetres(25),
                    self::millimetres(14),
                    mb_strimwidth($issuerAddress, 0, 125, '…'),
                    $regularFont,
                    7.5,
                );
            }

            if ($issuerContact !== '') {
                $pageCanvas->text(
                    self::millimetres(25),
                    self::millimetres(18),
                    mb_strimwidth($issuerContact, 0, 125, '…'),
                    $regularFont,
                    7.5,
                );
            }

            $pageCanvas->line(
                self::millimetres(25),
                self::millimetres(23),
                $pageCanvas->get_width() - self::millimetres(25),
                self::millimetres(23),
                [0, 0, 0],
                0.8,
            );
        });
    }

    private function validatedSnapshot(array $snapshot): array
    {
        foreach (['document', 'issuer', 'recipient', 'clauses', 'signatures'] as $section) {
            if (! isset($snapshot[$section]) || ! is_array($snapshot[$section])) {
                throw new InvalidArgumentException("Snapshot penawaran memerlukan bagian {$section}.");
            }
        }

        $document = $this->strings($snapshot['document'], [
            'number',
            'place',
            'date',
            'subject',
            'opening',
            'closing',
        ], 'document');

        $issuer = [
            ...$this->strings($snapshot['issuer'], ['name'], 'issuer'),
            'address_lines' => $this->stringList($snapshot['issuer']['address_lines'] ?? null, 'issuer.address_lines'),
            'contact_lines' => $this->stringList($snapshot['issuer']['contact_lines'] ?? [], 'issuer.contact_lines', false),
        ];

        $recipient = [
            ...$this->strings($snapshot['recipient'], ['name'], 'recipient'),
            'attention' => $this->optionalString($snapshot['recipient']['attention'] ?? null, 'recipient.attention'),
            'address_lines' => $this->stringList($snapshot['recipient']['address_lines'] ?? null, 'recipient.address_lines'),
        ];

        $signatures = [
            ...$this->strings($snapshot['signatures'], [
                'issuer_name',
                'issuer_title',
                'client_name',
            ], 'signatures'),
            'client_title' => $this->optionalString($snapshot['signatures']['client_title'] ?? null, 'signatures.client_title'),
        ];

        return [
            'document' => $document,
            'issuer' => $issuer,
            'recipient' => $recipient,
            'clauses' => $this->validatedClauses($snapshot['clauses']),
            'signatures' => $signatures,
        ];
    }

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
                throw new InvalidArgumentException("Klausul {$expectedNumber} tidak sesuai urutan template draf.");
            }

            $paragraphs = $this->stringList($clause['paragraphs'] ?? [], "clauses.{$expectedKey}.paragraphs", false);
            $items = $this->stringList($clause['items'] ?? [], "clauses.{$expectedKey}.items", false);

            if ($paragraphs === [] && $items === []) {
                throw new InvalidArgumentException("Klausul {$expectedNumber} harus memiliki isi draf.");
            }

            $validated[] = [
                'number' => $expectedNumber,
                'key' => $expectedKey,
                'title' => $expectedTitle,
                'paragraphs' => $paragraphs,
                'items' => $items,
            ];
        }

        return $validated;
    }

    private function strings(array $source, array $keys, string $section): array
    {
        $values = [];

        foreach ($keys as $key) {
            $value = $source[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException("Nilai {$section}.{$key} wajib berupa teks.");
            }

            $values[$key] = trim($value);
        }

        return $values;
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

    private function stringList(mixed $values, string $path, bool $required = true): array
    {
        if (! is_array($values) || ! array_is_list($values) || ($required && $values === [])) {
            throw new InvalidArgumentException("Nilai {$path} harus berupa daftar teks.");
        }

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException("Setiap nilai {$path} wajib berupa teks.");
            }
        }

        return array_map(static fn (string $value): string => trim($value), $values);
    }

    private function singleLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function millimetres(float $millimetres): float
    {
        return $millimetres * 72 / 25.4;
    }
}
