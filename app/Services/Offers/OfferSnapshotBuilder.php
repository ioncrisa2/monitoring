<?php

namespace App\Services\Offers;

use App\Enums\OfferTaxInclusion;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\Offer;
use App\Models\OfferTemplateVersion;
use DomainException;
use JsonException;
use OverflowException;

class OfferSnapshotBuilder
{
    private const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function __construct(
        private readonly OfferDocumentBootstrapper $bootstrapper,
        private readonly OfferFeeCalculator $feeCalculator,
        private readonly IndonesianAmountSpeller $amountSpeller,
    ) {}

    /**
     * Build a deterministic, query-free renderer payload. Authorization belongs to the caller.
     *
     * @return array<string, mixed>
     */
    public function build(Offer $offer): array
    {
        $offer->loadMissing([
            'branch',
            'debtor',
            'client',
            'reportUser',
            'creator',
            'currentNumberAllocation',
            'engagement.templateVersion.template',
            'engagement.issuerProfileVersion',
            'engagement.signerVersion',
        ]);

        $form = $this->bootstrapper->loadForm($offer);
        $engagement = $form['engagement'];
        $templateVersion = $this->templateVersion($offer, $engagement['template_version_id'] ?? null);
        $issuerProfile = $this->issuerProfile($offer, $engagement['issuer_profile_version_id'] ?? null);
        $signer = $this->signer($offer, $engagement['signer_version_id'] ?? null);
        $commercial = $this->commercial($form, $engagement);
        $issuer = $this->issuerSection($issuerProfile);
        $recipient = $this->recipientSection($offer, $engagement);
        $document = $this->documentSection($offer, $engagement, $templateVersion, $issuerProfile);
        $clauses = $this->clauses(
            $offer,
            $templateVersion,
            $engagement,
            $form['subjects'],
            $form['requirements'],
            $commercial,
        );
        $snapshotEngagement = $engagement;
        unset($snapshotEngagement['internal_note']);

        return [
            'document' => $document,
            'issuer' => $issuer,
            'recipient' => $recipient,
            'clauses' => $clauses,
            'signatures' => [
                'issuer_name' => $signer?->full_name ?? '[DRAF] Penandatangan belum dipilih',
                'issuer_title' => $signer?->position ?? '[DRAF] Jabatan penandatangan belum dipilih',
                'client_name' => $recipient['name'],
                'client_title' => $recipient['attention'],
            ],
            'subjects' => $form['subjects'],
            'commercial' => $commercial,
            'requirements' => $form['requirements'],
            'engagement' => $snapshotEngagement,
            'metadata' => [
                'schema_version' => 1,
                'offer_id' => $offer->getKey(),
                'number_allocation' => $offer->currentNumberAllocation ? [
                    'id' => $offer->currentNumberAllocation->getKey(),
                    'status' => $offer->currentNumberAllocation->status->value,
                    'scope_key' => $offer->currentNumberAllocation->scope_key,
                    'sequence_year' => $offer->currentNumberAllocation->sequence_year,
                    'sequence_no' => $offer->currentNumberAllocation->sequence_no,
                    'number_suffix' => $offer->currentNumberAllocation->number_suffix,
                    'full_number' => $offer->currentNumberAllocation->full_number,
                    'format_snapshot' => $offer->currentNumberAllocation->format_snapshot,
                ] : null,
                'template' => $templateVersion ? [
                    'id' => $templateVersion->getKey(),
                    'template_code' => $templateVersion->template?->code,
                    'version_no' => $templateVersion->version_no,
                    'schema_version' => $templateVersion->schema_version,
                    'layout_version' => $templateVersion->layout_version,
                    'status' => $templateVersion->status,
                    'checksum' => $templateVersion->checksum,
                ] : ['status' => 'provisional'],
                'issuer_profile' => $issuerProfile ? [
                    'id' => $issuerProfile->getKey(),
                    'version_no' => $issuerProfile->version_no,
                    'status' => $issuerProfile->status,
                    'checksum' => $issuerProfile->checksum,
                    'letterhead_sha256' => $issuerProfile->letterhead_sha256,
                ] : ['status' => 'provisional'],
                'signer' => $signer ? [
                    'id' => $signer->getKey(),
                    'signer_key' => $signer->signer_key,
                    'version_no' => $signer->version_no,
                    'status' => $signer->status,
                    'checksum' => $signer->checksum,
                ] : null,
                'renderer_profile' => [
                    'engine' => config('offer-documents.renderer.engine'),
                    'version' => config('offer-documents.renderer.version'),
                    'paper' => config('offer-documents.renderer.paper'),
                    'orientation' => config('offer-documents.renderer.orientation'),
                    'header_mode' => config('offer-documents.renderer.header_mode'),
                ],
                'uses_provisional_copy' => $templateVersion === null
                    || $templateVersion->status !== 'approved'
                    || $this->containsDraftMarker([$document, $clauses]),
                'uses_provisional_issuer' => $issuerProfile === null || $issuerProfile->status !== 'approved',
            ],
        ];
    }

    /**
     * Return the SHA-256 of canonical JSON. Associative keys are sorted recursively;
     * list order remains significant.
     *
     * @param  array<string, mixed>  $snapshot
     *
     * @throws JsonException
     */
    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($snapshot),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    private function templateVersion(Offer $offer, mixed $id): ?OfferTemplateVersion
    {
        $loaded = $offer->engagement?->templateVersion;

        if ($loaded instanceof OfferTemplateVersion && $offer->engagement->template_version_id === $id) {
            return $loaded;
        }

        return $id ? OfferTemplateVersion::query()->with('template')->find($id) : null;
    }

    private function issuerProfile(Offer $offer, mixed $id): ?IssuerProfileVersion
    {
        $loaded = $offer->engagement?->issuerProfileVersion;

        if ($loaded instanceof IssuerProfileVersion && $offer->engagement->issuer_profile_version_id === $id) {
            return $loaded;
        }

        return $id
            ? IssuerProfileVersion::query()->where('branch_id', $offer->branch_id)->find($id)
            : null;
    }

    private function signer(Offer $offer, mixed $id): ?DocumentSignerVersion
    {
        $loaded = $offer->engagement?->signerVersion;

        if ($loaded instanceof DocumentSignerVersion && $offer->engagement->signer_version_id === $id) {
            return $loaded;
        }

        return $id
            ? DocumentSignerVersion::query()->where('branch_id', $offer->branch_id)->find($id)
            : null;
    }

    /** @return array<string, mixed> */
    private function commercial(array $form, array $engagement): array
    {
        $configuredMode = $engagement['tax_inclusion'] ?? null;
        $calculationMode = $configuredMode ?? OfferTaxInclusion::NonTaxable->value;
        $ppnRate = (int) ($engagement['ppn_rate_bps'] ?? 0);
        $pphRate = (int) ($engagement['pph_rate_bps'] ?? 0);
        $calculationErrors = [];

        try {
            $calculated = $this->feeCalculator->calculate(
                $form['fee_items'],
                $calculationMode,
                $ppnRate,
                $pphRate,
                $form['payment_terms'],
            );
        } catch (DomainException|OverflowException $exception) {
            $calculationErrors[] = $exception->getMessage();
            try {
                $calculated = $this->feeCalculator->calculate(
                    $form['fee_items'],
                    $calculationMode,
                    $ppnRate,
                    $pphRate,
                );
            } catch (DomainException|OverflowException $fallbackException) {
                $calculationErrors[] = $fallbackException->getMessage();
                $calculated = [
                    'tax_inclusion' => $calculationMode,
                    'ppn_rate_bps' => $ppnRate,
                    'pph_rate_bps' => $pphRate,
                    'line_items' => [],
                    'quoted_amount' => 0,
                    'tax_base' => 0,
                    'ppn' => 0,
                    'pph' => 0,
                    'document_payable_total' => 0,
                ];
            }
            $calculated['payment_terms'] = $form['payment_terms'];
            $calculated['payment_term_bps_total'] = array_sum(array_map(
                static fn (array $term): int => (int) ($term['percentage_bps'] ?? 0),
                $form['payment_terms'],
            ));
        }

        try {
            $amountInWords = $this->amountSpeller->spell(
                $calculated['document_payable_total'],
                (string) ($engagement['currency'] ?? 'IDR'),
            );
        } catch (DomainException $exception) {
            $calculationErrors[] = $exception->getMessage();
            $amountInWords = '[DRAF] Terbilang belum tersedia';
        }

        return [
            ...$calculated,
            'configured_tax_inclusion' => $configuredMode,
            'calculation_is_provisional' => $configuredMode === null,
            'amount_in_words' => $amountInWords,
            'calculation_errors' => array_values(array_unique($calculationErrors)),
        ];
    }

    /** @return array<string, mixed> */
    private function documentSection(
        Offer $offer,
        array $engagement,
        ?OfferTemplateVersion $templateVersion,
        ?IssuerProfileVersion $issuerProfile,
    ): array {
        $templateDocument = $templateVersion?->clause_schema['document'] ?? [];

        return [
            'number' => $offer->currentNumberAllocation?->full_number
                ?? $offer->offer_no
                ?? 'DRAF-'.$offer->getKey(),
            'place' => $engagement['issue_city']
                ?? $issuerProfile?->city
                ?? $offer->branch?->name
                ?? 'Kota belum diisi',
            'date' => $this->formatDate($offer->offer_date),
            'subject' => $engagement['subject'] ?? 'Penawaran Jasa Penilaian',
            'opening' => $engagement['opening_context']
                ?? ($templateDocument['opening'] ?? null)
                ?? (string) config('offer-documents.provisional.opening'),
            'closing' => ($templateDocument['closing'] ?? null)
                ?? (string) config('offer-documents.provisional.closing'),
        ];
    }

    /** @return array{name: string, address_lines: list<string>, contact_lines: list<string>} */
    private function issuerSection(?IssuerProfileVersion $profile): array
    {
        if ($profile === null) {
            return [
                'name' => (string) config('offer-documents.provisional.issuer.name'),
                'address_lines' => $this->lines(config('offer-documents.provisional.issuer.address_lines')),
                'contact_lines' => $this->lines(config('offer-documents.provisional.issuer.contact_lines')),
            ];
        }

        return [
            'name' => $profile->legal_name,
            'address_lines' => $this->lines($profile->address),
            'contact_lines' => array_values(array_filter([
                $profile->phone,
                $profile->email,
                $profile->permit_no,
            ], static fn (?string $value): bool => $value !== null && trim($value) !== '')),
        ];
    }

    /** @return array{name: string, attention: ?string, address_lines: list<string>} */
    private function recipientSection(Offer $offer, array $engagement): array
    {
        return [
            'name' => $engagement['recipient_organization']
                ?? $offer->client?->name
                ?? '[DRAF] Penerima belum diisi',
            'attention' => $engagement['recipient_attention'],
            'address_lines' => $this->lines(
                $engagement['recipient_address']
                    ?? $offer->client?->address
                    ?? '[DRAF] Alamat penerima belum diisi',
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function clauses(
        Offer $offer,
        ?OfferTemplateVersion $templateVersion,
        array $engagement,
        array $subjects,
        array $requirements,
        array $commercial,
    ): array {
        $titles = (array) config('offer-documents.clause_titles', []);
        $provisional = (array) config('offer-documents.provisional.clause_paragraphs', []);
        $templateClauses = $this->templateClauses($templateVersion?->clause_schema ?? []);
        $clauses = [];

        foreach ($titles as $key => $title) {
            $templateClause = $templateClauses[$key] ?? [];
            $paragraphs = $this->textList($templateClause['paragraphs'] ?? []);
            $items = $this->textList($templateClause['items'] ?? []);
            $hasTemplateContent = $paragraphs !== [] || $items !== [];
            [$dynamicParagraphs, $dynamicItems] = $this->dynamicClause(
                $key,
                $offer,
                $engagement,
                $subjects,
                $requirements,
                $commercial,
            );

            if ($hasTemplateContent) {
                $dynamicParagraphs = $this->withoutDraftMarkers($dynamicParagraphs);
                $dynamicItems = $this->withoutDraftMarkers($dynamicItems);
            }

            $paragraphs = array_values(array_merge($paragraphs, $dynamicParagraphs));
            $items = array_values(array_merge($items, $dynamicItems));

            if ($paragraphs === [] && $items === []) {
                $paragraphs = [(string) ($provisional[$key] ?? "DRAF — Klausul {$title} belum disetujui.")];
            }

            $clauses[] = [
                'number' => count($clauses) + 1,
                'key' => $key,
                'title' => $title,
                'paragraphs' => $paragraphs,
                'items' => $items,
            ];
        }

        return $clauses;
    }

    /** @return array<string, array<string, mixed>> */
    private function templateClauses(array $schema): array
    {
        $source = $schema['clauses'] ?? $schema;
        $clauses = [];

        foreach ($source as $key => $clause) {
            if (! is_array($clause)) {
                continue;
            }

            $clauseKey = is_string($key) ? $key : ($clause['clause_key'] ?? $clause['key'] ?? null);

            if (is_string($clauseKey)) {
                $clauses[$clauseKey] = $clause;
            }
        }

        return $clauses;
    }

    /** @return array{list<string>, list<string>} */
    private function dynamicClause(
        string $key,
        Offer $offer,
        array $engagement,
        array $subjects,
        array $requirements,
        array $commercial,
    ): array {
        return match ($key) {
            'client' => [[
                $offer->client?->name ?? '[DRAF] Pemberi Tugas belum diisi',
            ], []],
            'report_user' => [[
                $offer->reportUser?->name ?? $offer->client?->name ?? '[DRAF] Pengguna Laporan belum diisi',
            ], []],
            'valuation_object' => [[], $this->assetDescriptions($subjects)],
            'ownership_form' => [[$engagement['ownership_form'] ?? '[DRAF] Bentuk kepemilikan belum diisi'], []],
            'currency' => [[(string) ($engagement['currency'] ?? 'IDR')], []],
            'purpose' => [[$engagement['purpose'] ?? '[DRAF] Tujuan penilaian belum diisi'], []],
            'basis_of_value' => [[$engagement['valuation_basis'] ?? '[DRAF] Dasar nilai belum diisi'], []],
            'valuation_date' => [[
                $engagement['valuation_date']
                    ?? $engagement['valuation_date_rule']
                    ?? '[DRAF] Tanggal penilaian belum diisi',
            ], []],
            'investigation_depth' => [[
                $engagement['investigation_level'] ?? '[DRAF] Tingkat investigasi belum diisi',
            ], []],
            'assumptions' => [[
                $engagement['special_assumptions'] ?? '[DRAF] Asumsi khusus belum diisi',
            ], []],
            'valuation_report' => [[trim(implode(' · ', array_filter([
                $engagement['report_format'] ?? null,
                $engagement['report_language'] ?? null,
                isset($engagement['report_copies']) ? $engagement['report_copies'].' eksemplar' : null,
            ]))) ?: '[DRAF] Format laporan belum diisi'], []],
            'professional_fee' => [[
                'Nilai penawaran: Rp '.number_format($commercial['document_payable_total'], 0, ',', '.')
                    .' ('.$commercial['amount_in_words'].').',
            ], []],
            'initial_data_request' => [[], array_values(array_map(
                static fn (array $requirement): string => (string) $requirement['description_snapshot'],
                $requirements,
            ))],
            'completion_time' => [[
                isset($engagement['completion_days'])
                    ? $engagement['completion_days'].' hari '.($engagement['completion_day_type'] ?? '')
                    : '[DRAF] Durasi penyelesaian belum diisi',
            ], []],
            default => [[], []],
        };
    }

    /** @return list<string> */
    private function assetDescriptions(array $subjects): array
    {
        $items = [];

        foreach ($subjects as $subject) {
            foreach ($subject['assets'] ?? [] as $asset) {
                $documents = array_values(array_filter(array_map(
                    static fn (array $document): string => trim(implode(' ', array_filter([
                        $document['document_type'] ?? null,
                        $document['document_no'] ?? null,
                    ]))),
                    $asset['documents'] ?? [],
                )));
                $items[] = trim(implode(' — ', array_filter([
                    $subject['name_snapshot'] ?? null,
                    $asset['description'] ?? $asset['asset_type'] ?? null,
                    $asset['address'] ?? null,
                    $documents === [] ? null : implode(', ', $documents),
                ])));
            }
        }

        return array_values(array_filter($items));
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/\R/u', (string) $value);

        return array_values(array_filter(array_map(
            static fn (mixed $line): string => trim((string) $line),
            $values ?: [],
        ), static fn (string $line): bool => $line !== ''));
    }

    /** @return list<string> */
    private function textList(mixed $value): array
    {
        if (is_string($value)) {
            return trim($value) === '' ? [] : [trim($value)];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value,
        ), static fn (string $item): bool => $item !== ''));
    }

    /** @param list<string> $values
     * @return list<string>
     */
    private function withoutDraftMarkers(array $values): array
    {
        return array_values(array_filter(
            $values,
            static fn (string $value): bool => ! str_contains(mb_strtoupper($value), 'DRAF'),
        ));
    }

    private function formatDate($date): string
    {
        if ($date === null) {
            return '[DRAF] Tanggal belum diisi';
        }

        return $date->day.' '.self::MONTHS[$date->month].' '.$date->year;
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

    private function containsDraftMarker(mixed $value): bool
    {
        if (is_string($value)) {
            return str_contains(mb_strtoupper($value), 'DRAF');
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsDraftMarker($item)) {
                return true;
            }
        }

        return false;
    }
}
