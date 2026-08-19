<?php

namespace App\Services\Offers;

use App\Enums\OfferFeePresentation;
use App\Enums\OfferTaxInclusion;
use App\Enums\OfferTemplateCondition;
use App\Enums\OfferTemplateDynamicSource;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\Offer;
use App\Models\OfferTemplateVersion;
use DomainException;
use Illuminate\Support\Facades\File;
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
        private readonly OfferDocumentMasterIntegrityService $masterIntegrity,
    ) {}

    /**
     * Build a deterministic, query-free renderer payload. Authorization belongs to the caller.
     *
     * @return array<string, mixed>
     */
    public function build(Offer $offer): array
    {
        $connection = $offer->getConnection();
        $build = fn (): array => $this->buildLocked($offer->getKey());

        if ($connection->transactionLevel() > 0) {
            return $build();
        }

        return $connection->transaction($build, 5);
    }

    /** @return array<string, mixed> */
    private function buildLocked(mixed $offerKey): array
    {
        $offer = Offer::query()->lockForUpdate()->findOrFail($offerKey);

        $offer->load([
            'branch',
            'debtor',
            'client',
            'reportUser',
            'creator',
            'currentNumberAllocation',
            'engagement.templateVersion.template',
            'engagement.issuerProfileVersion',
            'engagement.signerVersion',
            'subjects.assets.documents',
            'feeItems',
            'paymentTerms',
            'requirements',
        ]);

        $form = $this->bootstrapper->loadForm($offer);
        $engagement = $form['engagement'];
        $templateVersion = $this->templateVersion($offer, $engagement['template_version_id'] ?? null);
        $form = $this->withTemplateDefaults($form, $templateVersion);
        $engagement = $form['engagement'];
        $issuerProfile = $this->issuerProfile($offer, $engagement['issuer_profile_version_id'] ?? null);
        $signer = $this->signer($offer, $engagement['signer_version_id'] ?? null);
        $commercial = $this->commercial($form, $engagement);
        $issuer = $this->issuerSection($issuerProfile);
        $recipient = $this->recipientSection($offer, $engagement);
        $document = $this->documentSection($offer, $engagement, $templateVersion, $issuerProfile);
        $document = $this->resolveDocumentTokens($document, $engagement, $issuer, $recipient, $signer, $commercial);
        $clauses = $this->clauses(
            $offer,
            $templateVersion,
            $engagement,
            $form['subjects'],
            $form['requirements'],
            $commercial,
            $document,
            $issuer,
            $recipient,
            $signer,
        );
        $snapshotEngagement = $engagement;
        // Workflow bookkeeping changes when the snapshot is submitted or
        // approved. It is intentionally excluded so the business-content hash
        // remains stable while the exact submitted payload stays immutable.
        unset(
            $snapshotEngagement['internal_note'],
            $snapshotEngagement['workflow_state'],
            $snapshotEngagement['lock_version'],
        );

        return [
            'document' => $document,
            'issuer' => $issuer,
            'recipient' => $recipient,
            'clauses' => $clauses,
            'signatures' => [
                'issuer_name' => $signer
                    ? trim($signer->full_name.' '.($signer->title_suffix ?? ''))
                    : '[DRAF] Penandatangan belum dipilih',
                'issuer_title' => $signer?->position ?? '[DRAF] Jabatan penandatangan belum dipilih',
                'issuer_permit_no' => $signer?->permit_no,
                'issuer_registration_no' => $signer?->registration_no,
                'client_name' => $recipient['name'],
                'client_title' => $recipient['attention'],
            ],
            'subjects' => $form['subjects'],
            'commercial' => $commercial,
            'requirements' => $form['requirements'],
            'engagement' => $snapshotEngagement,
            'metadata' => [
                'schema_version' => $templateVersion?->schema_version ?? 1,
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
                    'category' => $templateVersion->template?->category?->value
                        ?? $templateVersion->template?->category,
                    'template_active' => $templateVersion->template?->active === true,
                    'version_no' => $templateVersion->version_no,
                    'schema_version' => $templateVersion->schema_version,
                    'layout_version' => $templateVersion->layout_version,
                    'status' => $templateVersion->status,
                    'checksum' => $templateVersion->checksum,
                    'approved_by' => $templateVersion->approved_by,
                    'approved_at' => $templateVersion->approved_at?->toIso8601String(),
                    'effective_from' => $templateVersion->effective_from?->format('Y-m-d'),
                    'effective_until' => $templateVersion->effective_until?->format('Y-m-d'),
                    'is_effective' => $this->isEffectiveOn(
                        $offer->offer_date?->format('Y-m-d'),
                        $templateVersion->effective_from?->format('Y-m-d'),
                        $templateVersion->effective_until?->format('Y-m-d'),
                    ),
                    'integrity_valid' => $this->masterIntegrity->verify($templateVersion),
                    'schema_valid' => $this->masterIntegrity->templateSchemaErrorsFor($templateVersion) === [],
                    'constraints' => $templateVersion->schema_version === OfferTemplateSchemaV2::SCHEMA_VERSION
                        ? (array) ($templateVersion->clause_schema['constraints'] ?? [])
                        : [],
                ] : ['status' => 'provisional'],
                'issuer_profile' => $issuerProfile ? [
                    'id' => $issuerProfile->getKey(),
                    'version_no' => $issuerProfile->version_no,
                    'status' => $issuerProfile->status,
                    'checksum' => $issuerProfile->checksum,
                    'letterhead_sha256' => $issuerProfile->letterhead_sha256,
                    'letterhead_path' => $issuerProfile->letterhead_path,
                    'letterhead_mime' => $issuerProfile->letterhead_mime,
                    'letterhead_verified' => ($issuer['letterhead']['verified'] ?? false) === true,
                    'approved_by' => $issuerProfile->approved_by,
                    'approved_at' => $issuerProfile->approved_at?->toIso8601String(),
                    'effective_from' => $issuerProfile->effective_from?->format('Y-m-d'),
                    'effective_until' => $issuerProfile->effective_until?->format('Y-m-d'),
                    'is_effective' => $this->isEffectiveOn(
                        $offer->offer_date?->format('Y-m-d'),
                        $issuerProfile->effective_from?->format('Y-m-d'),
                        $issuerProfile->effective_until?->format('Y-m-d'),
                    ),
                    'integrity_valid' => $this->masterIntegrity->verify($issuerProfile),
                ] : ['status' => 'provisional'],
                'signer' => $signer ? [
                    'id' => $signer->getKey(),
                    'signer_key' => $signer->signer_key,
                    'version_no' => $signer->version_no,
                    'status' => $signer->status,
                    'checksum' => $signer->checksum,
                    'approved_by' => $signer->approved_by,
                    'approved_at' => $signer->approved_at?->toIso8601String(),
                    'effective_from' => $signer->effective_from?->format('Y-m-d'),
                    'effective_until' => $signer->effective_until?->format('Y-m-d'),
                    'is_effective' => $this->isEffectiveOn(
                        $offer->offer_date?->format('Y-m-d'),
                        $signer->effective_from?->format('Y-m-d'),
                        $signer->effective_until?->format('Y-m-d'),
                    ),
                    'integrity_valid' => $this->masterIntegrity->verify($signer),
                ] : null,
                'renderer_profile' => [
                    'engine' => config('offer-documents.renderer.engine'),
                    'version' => config('offer-documents.renderer.version'),
                    'paper' => config('offer-documents.renderer.paper'),
                    'orientation' => config('offer-documents.renderer.orientation'),
                    'header_mode' => $templateVersion?->schema_version === OfferTemplateSchemaV2::SCHEMA_VERSION
                        ? OfferTemplateSchemaV2::HEADER_MODE
                        : ($templateVersion?->status === 'approved'
                            ? $templateVersion->header_mode
                            : config('offer-documents.renderer.header_mode')),
                ],
                'uses_provisional_copy' => $templateVersion === null
                    || $templateVersion->status !== 'approved'
                    || ! $this->masterIntegrity->verify($templateVersion)
                    || $this->masterIntegrity->templateSchemaErrorsFor($templateVersion) !== []
                    || $this->containsDraftMarker([$document, $clauses]),
                'uses_provisional_issuer' => $issuerProfile === null
                    || $issuerProfile->status !== 'approved'
                    || ! $this->masterIntegrity->verify($issuerProfile)
                    || ($templateVersion?->schema_version === OfferTemplateSchemaV2::SCHEMA_VERSION
                        && ($issuer['letterhead']['verified'] ?? false) !== true),
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
    private function withTemplateDefaults(array $form, ?OfferTemplateVersion $templateVersion): array
    {
        if ($templateVersion?->schema_version !== OfferTemplateSchemaV2::SCHEMA_VERSION) {
            return $form;
        }

        $defaults = is_array($templateVersion->clause_schema['defaults'] ?? null)
            ? $templateVersion->clause_schema['defaults']
            : [];
        $engagement = is_array($form['engagement'] ?? null) ? $form['engagement'] : [];

        foreach (array_diff(OfferTemplateSchemaV2::DEFAULT_KEYS, ['payment_terms', 'requirements']) as $field) {
            $current = $engagement[$field] ?? null;

            if (($current === null || $current === '' || ($current === [] && $field === 'cost_inclusions'))
                && array_key_exists($field, $defaults)) {
                $engagement[$field] = $defaults[$field];
            }
        }

        $form['engagement'] = $engagement;

        if (($form['payment_terms'] ?? []) === [] && is_array($defaults['payment_terms'] ?? null)) {
            $form['payment_terms'] = array_map(
                static fn (array $term, int $index): array => [
                    'id' => null,
                    'sequence' => $index + 1,
                    'percentage_bps' => $term['percentage_bps'],
                    'trigger_text' => $term['trigger_text'],
                    'due_days' => $term['due_days'] ?? null,
                ],
                array_values($defaults['payment_terms']),
                array_keys(array_values($defaults['payment_terms'])),
            );
        }

        if (($form['requirements'] ?? []) === [] && is_array($defaults['requirements'] ?? null)) {
            $form['requirements'] = array_map(
                static fn (array $requirement, int $index): array => [
                    'id' => null,
                    'requirement_code' => $requirement['requirement_code'] ?? null,
                    'description_snapshot' => $requirement['description'],
                    'emphasis_style' => $requirement['emphasis_style'],
                    'sort_order' => $index,
                ],
                array_values($defaults['requirements']),
                array_keys(array_values($defaults['requirements'])),
            );
        }

        return $form;
    }

    /** @return array<string, mixed> */
    private function commercial(array $form, array $engagement): array
    {
        $feeItems = array_values(is_array($form['fee_items'] ?? null) ? $form['fee_items'] : []);
        $configuredMode = $engagement['tax_inclusion'] ?? null;
        $calculationMode = $configuredMode ?? OfferTaxInclusion::NonTaxable->value;
        $ppnRate = (int) ($engagement['ppn_rate_bps'] ?? 0);
        $pphRate = (int) ($engagement['pph_rate_bps'] ?? 0);
        $calculationErrors = [];

        try {
            $calculated = $this->feeCalculator->calculate(
                $feeItems,
                $calculationMode,
                $ppnRate,
                $pphRate,
                $form['payment_terms'],
            );
        } catch (DomainException|OverflowException $exception) {
            $calculationErrors[] = $exception->getMessage();
            try {
                $calculated = $this->feeCalculator->calculate(
                    $feeItems,
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

        foreach ($calculated['line_items'] as $index => &$lineItem) {
            $source = is_array($feeItems[$index] ?? null) ? $feeItems[$index] : [];
            $lineItem['offer_subject_id'] = $source['offer_subject_id'] ?? null;
            $lineItem['offer_asset_id'] = $source['offer_asset_id'] ?? null;
        }
        unset($lineItem);

        $feePresentation = $engagement['fee_presentation'] ?? OfferFeePresentation::LumpSum->value;

        if ($feePresentation instanceof OfferFeePresentation) {
            $feePresentation = $feePresentation->value;
        }

        return [
            ...$calculated,
            'fee_presentation' => $feePresentation,
            'configured_tax_inclusion' => $configuredMode,
            'calculation_is_provisional' => $configuredMode === null,
            'amount_in_words' => $amountInWords,
            'calculation_errors' => array_values(array_unique($calculationErrors)),
            'exposure_rows' => $this->exposureRows((array) ($form['subjects'] ?? [])),
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
        $templateDefaults = $templateVersion?->schema_version === OfferTemplateSchemaV2::SCHEMA_VERSION
            ? (array) ($templateVersion->clause_schema['defaults'] ?? [])
            : [];

        return [
            'number' => $offer->currentNumberAllocation?->full_number
                ?? $offer->offer_no
                ?? 'DRAF-'.$offer->getKey(),
            'place' => $engagement['issue_city']
                ?? $issuerProfile?->city
                ?? $offer->branch?->name
                ?? 'Kota belum diisi',
            'date' => $this->formatDate($offer->offer_date),
            'subject' => $engagement['subject'] ?? $templateDefaults['subject'] ?? 'Penawaran Jasa Penilaian',
            'opening' => $templateVersion?->schema_version === OfferTemplateSchemaV2::SCHEMA_VERSION
                ? (($templateDocument['opening'] ?? null)
                    ?? (string) config('offer-documents.provisional.opening'))
                : ($templateVersion?->status === 'approved'
                ? (($templateDocument['opening'] ?? null)
                    ?? (string) config('offer-documents.provisional.opening'))
                : ($engagement['opening_context']
                    ?? ($templateDocument['opening'] ?? null)
                    ?? (string) config('offer-documents.provisional.opening'))),
            'closing' => ($templateDocument['closing'] ?? null)
                ?? (string) config('offer-documents.provisional.closing'),
        ];
    }

    /** @return array<string, string> */
    private function resolveDocumentTokens(
        array $document,
        array $engagement,
        array $issuer,
        array $recipient,
        ?DocumentSignerVersion $signer,
        array $commercial,
    ): array {
        $tokens = $this->tokenValues($document, $engagement, $issuer, $recipient, $signer, $commercial);
        $document['opening'] = $this->resolveTokens((string) $document['opening'], $tokens);
        $document['closing'] = $this->resolveTokens((string) $document['closing'], $tokens);

        return $document;
    }

    /** @return array<string, mixed> */
    private function issuerSection(?IssuerProfileVersion $profile): array
    {
        if ($profile === null) {
            return [
                'name' => (string) config('offer-documents.provisional.issuer.name'),
                'address_lines' => $this->lines(config('offer-documents.provisional.issuer.address_lines')),
                'contact_lines' => $this->lines(config('offer-documents.provisional.issuer.contact_lines')),
                'office_label' => null,
                'permit_no' => null,
                'phone' => null,
                'email' => null,
                'letterhead' => $this->letterheadAsset(null),
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
            'office_label' => $profile->office_label,
            'permit_no' => $profile->permit_no,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'letterhead' => $this->letterheadAsset($profile),
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
        array $document,
        array $issuer,
        array $recipient,
        ?DocumentSignerVersion $signer,
    ): array {
        if ($templateVersion?->schema_version === OfferTemplateSchemaV2::SCHEMA_VERSION) {
            return $this->v2Clauses(
                $offer,
                $templateVersion,
                $engagement,
                $subjects,
                $requirements,
                $commercial,
                $document,
                $issuer,
                $recipient,
                $signer,
            );
        }

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
                'blocks' => [
                    ...array_map(
                        static fn (string $text): array => ['type' => 'text', 'text' => $text],
                        $paragraphs,
                    ),
                    ...($items === [] ? [] : [['type' => 'bullets', 'items' => $items]]),
                ],
            ];
        }

        return $clauses;
    }

    /** @return list<array<string, mixed>> */
    private function v2Clauses(
        Offer $offer,
        OfferTemplateVersion $templateVersion,
        array $engagement,
        array $subjects,
        array $requirements,
        array $commercial,
        array $document,
        array $issuer,
        array $recipient,
        ?DocumentSignerVersion $signer,
    ): array {
        $titles = (array) config('offer-documents.clause_titles', []);
        $schemaClauses = (array) ($templateVersion->clause_schema['clauses'] ?? []);
        $tokenValues = $this->tokenValues(
            $document,
            $engagement,
            $issuer,
            $recipient,
            $signer,
            $commercial,
        );
        $assetRows = $this->assetTableRows($subjects);
        $feeRows = $this->feeTableRows($commercial, $subjects);
        $paymentRows = $this->paymentTermRows($commercial);
        $requirementRows = $this->requirementRows($requirements);
        $exposureRows = $this->exposureTableRows($commercial['exposure_rows'] ?? []);
        $clauses = [];

        foreach ($titles as $key => $title) {
            $blocks = [];
            $configuredBlocks = $schemaClauses[$key]['blocks'] ?? [];

            foreach (is_array($configuredBlocks) ? $configuredBlocks : [] as $block) {
                if (! is_array($block) || ! $this->blockEnabled($block['when'] ?? null, $engagement, $subjects, $commercial)) {
                    continue;
                }

                $resolved = $this->resolveV2Block(
                    $block,
                    $offer,
                    $engagement,
                    $tokenValues,
                    $assetRows,
                    $feeRows,
                    $paymentRows,
                    $requirementRows,
                    $exposureRows,
                );

                if ($resolved !== null) {
                    $blocks[] = $resolved;
                }
            }

            if ($blocks === []) {
                $blocks[] = [
                    'type' => 'text',
                    'text' => '[DRAF] Klausul '.$title.' belum memiliki blok yang berlaku.',
                ];
            }

            [$paragraphs, $items] = $this->legacyContentFromBlocks($blocks);
            $clauses[] = [
                'number' => count($clauses) + 1,
                'key' => $key,
                'title' => $title,
                'paragraphs' => $paragraphs,
                'items' => $items,
                'blocks' => $blocks,
            ];
        }

        return $clauses;
    }

    /** @return array<string, mixed>|null */
    private function resolveV2Block(
        array $block,
        Offer $offer,
        array $engagement,
        array $tokenValues,
        array $assetRows,
        array $feeRows,
        array $paymentRows,
        array $requirementRows,
        array $exposureRows,
    ): ?array {
        return match ($block['type'] ?? null) {
            'text' => [
                'type' => 'text',
                'text' => $this->resolveTokens((string) ($block['text'] ?? ''), $tokenValues),
            ],
            'bullets' => [
                'type' => 'bullets',
                'items' => array_map(
                    fn (mixed $item): string => $this->resolveTokens((string) $item, $tokenValues),
                    is_array($block['items'] ?? null) ? $block['items'] : [],
                ),
            ],
            'dynamic' => $this->dynamicV2Block(
                (string) ($block['source'] ?? ''),
                $offer,
                $engagement,
                $tokenValues,
            ),
            'asset_list' => ['type' => 'asset_list', 'rows' => $assetRows],
            'fee_summary' => [
                'type' => 'fee_summary',
                'rows' => $this->feeSummaryRows($tokenValues, $engagement),
                'amount_in_words' => (string) ($tokenValues['commercial.amount_in_words'] ?? ''),
            ],
            'fee_table' => ['type' => 'fee_table', 'rows' => $feeRows],
            'payment_terms' => ['type' => 'payment_terms', 'rows' => $paymentRows],
            'requirements' => ['type' => 'requirements', 'rows' => $requirementRows],
            'exposure_table' => [
                'type' => 'exposure_table',
                'rows' => $exposureRows,
                'empty_message' => '[DRAF] Data exposure, Nilai Pasar, dan Nilai Likuidasi belum lengkap.',
            ],
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function dynamicV2Block(string $source, Offer $offer, array $engagement, array $tokens): array
    {
        $text = match ($source) {
            OfferTemplateDynamicSource::AppraiserStatus->value => trim(implode(' — ', array_filter([
                $tokens['issuer.name'] ?? 'Kantor Jasa Penilai Publik',
                isset($tokens['issuer.permit_no']) && $tokens['issuer.permit_no'] !== ''
                    ? 'Izin '.$tokens['issuer.permit_no']
                    : null,
            ]))),
            OfferTemplateDynamicSource::Client->value => $offer->client?->name
                ?? '[DRAF] Pemberi Tugas belum diisi',
            OfferTemplateDynamicSource::ReportUser->value => $offer->reportUser?->name
                ?? $offer->client?->name
                ?? '[DRAF] Pengguna Laporan belum diisi',
            OfferTemplateDynamicSource::OwnershipForm->value => $this->humanText(
                $engagement['ownership_form'] ?? null,
                'Bentuk kepemilikan belum diisi',
            ),
            OfferTemplateDynamicSource::Currency->value => $this->currencyLabel($engagement['currency'] ?? null),
            OfferTemplateDynamicSource::Purpose->value => $this->humanText(
                $engagement['purpose'] ?? null,
                'Tujuan penilaian belum diisi',
            ),
            OfferTemplateDynamicSource::ValuationBasis->value => $this->humanText(
                $engagement['valuation_basis'] ?? null,
                'Dasar nilai belum diisi',
            ),
            OfferTemplateDynamicSource::ValuationDate->value => $this->formatDateValue(
                $engagement['valuation_date'] ?? null,
                $engagement['valuation_date_rule'] ?? '[DRAF] Tanggal penilaian belum diisi',
            ),
            OfferTemplateDynamicSource::InvestigationLevel->value => $this->investigationLabel(
                $engagement['investigation_level'] ?? null,
            ),
            OfferTemplateDynamicSource::SpecialAssumptions->value => $this->humanText(
                $engagement['special_assumptions'] ?? null,
                'Asumsi khusus belum diisi',
            ),
            OfferTemplateDynamicSource::ReportSpecification->value => $this->reportSpecification($engagement),
            OfferTemplateDynamicSource::CompletionTime->value => $this->completionTimeLabel($engagement),
            default => '[DRAF] Sumber data dinamis tidak dikenali.',
        };

        return ['type' => 'dynamic', 'source' => $source, 'text' => $text];
    }

    private function blockEnabled(
        mixed $condition,
        array $engagement,
        array $subjects,
        array $commercial,
    ): bool {
        if ($condition === null || $condition === '') {
            return true;
        }

        $assetCount = count($this->flattenAssets($subjects));
        $taxMode = $commercial['tax_inclusion'] ?? $engagement['tax_inclusion'] ?? null;
        $feePresentation = $commercial['fee_presentation'] ?? $engagement['fee_presentation'] ?? null;

        return match ($condition) {
            OfferTemplateCondition::HasRequestReference->value => in_array(
                $engagement['request_reference_type'] ?? 'none',
                ['letter', 'email'],
                true,
            ) && trim((string) ($engagement['request_reference_no'] ?? '')) !== '',
            OfferTemplateCondition::HasMultipleAssets->value => $assetCount > 1,
            OfferTemplateCondition::HasSpecialAssumptions->value => trim((string) ($engagement['special_assumptions'] ?? '')) !== '',
            OfferTemplateCondition::TaxIncluded->value => $taxMode === OfferTaxInclusion::Included->value,
            OfferTemplateCondition::TaxExcluded->value => $taxMode === OfferTaxInclusion::Excluded->value,
            OfferTemplateCondition::FeeLumpSum->value => $feePresentation === OfferFeePresentation::LumpSum->value,
            OfferTemplateCondition::FeePerAsset->value => $feePresentation === OfferFeePresentation::PerAsset->value,
            default => false,
        };
    }

    /** @return array<string, string> */
    private function tokenValues(
        array $document,
        array $engagement,
        array $issuer,
        array $recipient,
        ?DocumentSignerVersion $signer,
        array $commercial,
    ): array {
        return [
            'document.number' => (string) ($document['number'] ?? ''),
            'document.place' => (string) ($document['place'] ?? ''),
            'document.date' => (string) ($document['date'] ?? ''),
            'document.subject' => (string) ($document['subject'] ?? ''),
            'recipient.name' => (string) ($recipient['name'] ?? ''),
            'recipient.attention' => (string) ($recipient['attention'] ?? ''),
            'recipient.address' => implode(', ', (array) ($recipient['address_lines'] ?? [])),
            'issuer.name' => (string) ($issuer['name'] ?? ''),
            'issuer.address' => implode(', ', (array) ($issuer['address_lines'] ?? [])),
            'issuer.phone' => (string) ($issuer['phone'] ?? ''),
            'issuer.email' => (string) ($issuer['email'] ?? ''),
            'issuer.permit_no' => (string) ($issuer['permit_no'] ?? ''),
            'request.reference_no' => (string) ($engagement['request_reference_no'] ?? ''),
            'request.reference_date' => $this->formatDateValue($engagement['request_reference_date'] ?? null, ''),
            'engagement.ownership_form' => (string) ($engagement['ownership_form'] ?? ''),
            'engagement.currency' => $this->currencyLabel($engagement['currency'] ?? null),
            'engagement.purpose' => (string) ($engagement['purpose'] ?? ''),
            'engagement.valuation_basis' => (string) ($engagement['valuation_basis'] ?? ''),
            'engagement.valuation_date' => $this->formatDateValue(
                $engagement['valuation_date'] ?? null,
                (string) ($engagement['valuation_date_rule'] ?? ''),
            ),
            'engagement.investigation_level' => $this->investigationLabel($engagement['investigation_level'] ?? null),
            'engagement.report_format' => $this->reportFormatLabel($engagement['report_format'] ?? null),
            'engagement.report_language' => $this->languageLabel($engagement['report_language'] ?? null),
            'engagement.report_copies' => isset($engagement['report_copies'])
                ? number_format((int) $engagement['report_copies'], 0, ',', '.').' eksemplar'
                : '',
            'engagement.completion_days' => isset($engagement['completion_days'])
                ? (string) (int) $engagement['completion_days']
                : '',
            'engagement.completion_day_type' => $this->dayTypeLabel($engagement['completion_day_type'] ?? null),
            'engagement.special_assumptions' => (string) ($engagement['special_assumptions'] ?? ''),
            'commercial.quoted_amount' => $this->money((int) ($commercial['quoted_amount'] ?? 0), $engagement['currency'] ?? 'IDR'),
            'commercial.document_payable_total' => $this->money((int) ($commercial['document_payable_total'] ?? 0), $engagement['currency'] ?? 'IDR'),
            'commercial.amount_in_words' => (string) ($commercial['amount_in_words'] ?? ''),
            'signer.full_name' => $signer ? trim($signer->full_name.' '.($signer->title_suffix ?? '')) : '',
            'signer.position' => (string) ($signer?->position ?? ''),
            'signer.permit_no' => (string) ($signer?->permit_no ?? ''),
            'signer.registration_no' => (string) ($signer?->registration_no ?? ''),
            '_quoted_amount' => (int) ($commercial['quoted_amount'] ?? 0),
            '_tax_base' => (int) ($commercial['tax_base'] ?? 0),
            '_ppn' => (int) ($commercial['ppn'] ?? 0),
            '_pph' => (int) ($commercial['pph'] ?? 0),
            '_document_payable_total' => (int) ($commercial['document_payable_total'] ?? 0),
            '_ppn_rate_bps' => (int) ($commercial['ppn_rate_bps'] ?? 0),
            '_pph_rate_bps' => (int) ($commercial['pph_rate_bps'] ?? 0),
            '_tax_inclusion' => (string) ($commercial['tax_inclusion'] ?? ''),
        ];
    }

    private function resolveTokens(string $text, array $values): string
    {
        return trim((string) preg_replace_callback(
            '/{{\s*([^{}]+?)\s*}}/u',
            static function (array $match) use ($values): string {
                $token = trim($match[1]);

                if (! in_array($token, OfferTemplateSchemaV2::TOKENS, true)) {
                    return '[DRAF] Token tidak dikenal';
                }

                $value = trim((string) ($values[$token] ?? ''));

                return $value !== '' ? $value : '[DRAF] Data '.$token.' belum diisi';
            },
            $text,
        ));
    }

    /** @return array{list<string>, list<string>} */
    private function legacyContentFromBlocks(array $blocks): array
    {
        $paragraphs = [];
        $items = [];

        foreach ($blocks as $block) {
            if (in_array($block['type'] ?? null, ['text', 'dynamic'], true) && isset($block['text'])) {
                $paragraphs[] = $block['text'];
            }

            if (in_array($block['type'] ?? null, ['bullets', 'dynamic'], true) && isset($block['items'])) {
                $items = [...$items, ...$block['items']];
            }
        }

        return [$paragraphs, $items];
    }

    /** @return list<array<string, mixed>> */
    private function flattenAssets(array $subjects): array
    {
        $rows = [];

        foreach ($subjects as $subjectIndex => $subject) {
            if (! is_array($subject)) {
                continue;
            }

            foreach (is_array($subject['assets'] ?? null) ? $subject['assets'] : [] as $assetIndex => $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                $rows[] = [
                    ...$asset,
                    '_subject' => $subject,
                    '_subject_index' => $subjectIndex,
                    '_asset_index' => $assetIndex,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array<string, string>> */
    private function assetTableRows(array $subjects): array
    {
        $rows = [];

        foreach ($this->flattenAssets($subjects) as $asset) {
            $rows[] = [
                'number' => (string) (count($rows) + 1),
                'subject' => (string) ($asset['_subject']['name_snapshot'] ?? '—'),
                'asset' => $this->assetLabel($asset),
                'location' => $this->assetLocation($asset),
                'documents' => $this->assetDocumentLabel((array) ($asset['documents'] ?? [])),
            ];
        }

        return $rows !== [] ? $rows : [[
            'number' => '—',
            'subject' => '[DRAF] Subjek belum diisi',
            'asset' => '[DRAF] Aset belum diisi',
            'location' => '[DRAF] Lokasi belum diisi',
            'documents' => '[DRAF] Dokumen aset belum diisi',
        ]];
    }

    /** @return list<array<string, string>> */
    private function feeTableRows(array $commercial, array $subjects): array
    {
        $assets = [];

        foreach ($this->flattenAssets($subjects) as $asset) {
            if (isset($asset['id'])) {
                $assets[(string) $asset['id']] = $this->assetLabel($asset);
            }
        }

        $rows = [];

        foreach ((array) ($commercial['line_items'] ?? []) as $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            $assetId = $lineItem['offer_asset_id'] ?? null;
            $rows[] = [
                'number' => (string) (count($rows) + 1),
                'asset' => $assetId !== null
                    ? ($assets[(string) $assetId] ?? '[DRAF] Aset fee tidak ditemukan')
                    : 'Lump sum',
                'label' => (string) ($lineItem['label'] ?? 'Jasa Penilaian'),
                'quantity' => number_format((int) ($lineItem['quantity'] ?? 0), 0, ',', '.'),
                'unit_amount' => $this->money((int) ($lineItem['unit_amount'] ?? 0)),
                'line_total' => $this->money((int) ($lineItem['line_total'] ?? 0)),
            ];
        }

        return $rows !== [] ? $rows : [[
            'number' => '—',
            'asset' => '[DRAF] Aset belum dipetakan',
            'label' => '[DRAF] Item fee belum diisi',
            'quantity' => '—',
            'unit_amount' => '—',
            'line_total' => '—',
        ]];
    }

    /** @return list<array<string, string>> */
    private function feeSummaryRows(array $tokens, array $engagement): array
    {
        $currency = (string) ($engagement['currency'] ?? 'IDR');
        $quoted = (int) ($tokens['_quoted_amount'] ?? 0);
        $taxBase = (int) ($tokens['_tax_base'] ?? $quoted);
        $ppn = (int) ($tokens['_ppn'] ?? 0);
        $pph = (int) ($tokens['_pph'] ?? 0);
        $payable = (int) ($tokens['_document_payable_total'] ?? 0);
        $taxMode = $tokens['_tax_inclusion'] ?? null;
        $rows = [['label' => 'Biaya jasa penilaian', 'value' => $this->money($quoted, $currency)]];

        if ($taxMode !== OfferTaxInclusion::NonTaxable->value) {
            $rows[] = ['label' => 'Dasar pengenaan pajak', 'value' => $this->money($taxBase, $currency)];
        }

        if ($ppn > 0) {
            $rows[] = [
                'label' => 'PPN '.$this->percentage((int) ($tokens['_ppn_rate_bps'] ?? 0)),
                'value' => $this->money($ppn, $currency),
            ];
        }

        if ($pph > 0) {
            $rows[] = [
                'label' => 'PPh '.$this->percentage((int) ($tokens['_pph_rate_bps'] ?? 0)).' (dipotong oleh Pemberi Tugas)',
                'value' => $this->money($pph, $currency),
            ];
        }

        $rows[] = ['label' => 'Jumlah penawaran', 'value' => $this->money($payable, $currency)];

        return $rows;
    }

    /** @return list<array<string, string>> */
    private function paymentTermRows(array $commercial): array
    {
        $rows = [];

        foreach ((array) ($commercial['payment_terms'] ?? []) as $term) {
            if (! is_array($term)) {
                continue;
            }

            $dueDays = $term['due_days'] ?? null;
            $rows[] = [
                'number' => (string) ($term['sequence'] ?? count($rows) + 1),
                'percentage' => $this->percentage((int) ($term['percentage_bps'] ?? 0)),
                'trigger' => (string) ($term['trigger_text'] ?? '—'),
                'due' => $dueDays === null ? '—' : ((int) $dueDays).' hari kalender',
                'amount' => $this->money((int) ($term['amount'] ?? 0)),
            ];
        }

        return $rows !== [] ? $rows : [[
            'number' => '—',
            'percentage' => '—',
            'trigger' => '[DRAF] Termin pembayaran belum diisi',
            'due' => '—',
            'amount' => '—',
        ]];
    }

    /** @return list<array<string, string>> */
    private function requirementRows(array $requirements): array
    {
        $rows = [];

        foreach ($requirements as $requirement) {
            if (! is_array($requirement)) {
                continue;
            }

            $rows[] = [
                'number' => (string) (count($rows) + 1),
                'code' => (string) ($requirement['requirement_code'] ?? '—'),
                'description' => (string) ($requirement['description_snapshot'] ?? '[DRAF] Deskripsi belum diisi'),
                'emphasis' => in_array($requirement['emphasis_style'] ?? null, ['normal', 'bold', 'italic', 'underline'], true)
                    ? $requirement['emphasis_style']
                    : 'normal',
            ];
        }

        return $rows !== [] ? $rows : [[
            'number' => '—',
            'code' => '—',
            'description' => '[DRAF] Permintaan data awal belum diisi',
            'emphasis' => 'normal',
        ]];
    }

    /** @return list<array<string, string>> */
    private function exposureTableRows(array $sourceRows): array
    {
        $rows = [];

        foreach ($sourceRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'number' => (string) (count($rows) + 1),
                'asset' => (string) ($row['asset_label'] ?? '—'),
                'exposure' => $this->nullableMoney($row['exposure_amount'] ?? null),
                'market_value' => $this->nullableMoney($row['reference_market_value'] ?? null),
                'liquidation_value' => $this->nullableMoney($row['reference_liquidation_value'] ?? null),
                'discount' => isset($row['liquidation_discount_bps'])
                    ? $this->percentage((int) $row['liquidation_discount_bps'])
                    : '—',
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function exposureRows(array $subjects): array
    {
        $rows = [];

        foreach ($this->flattenAssets($subjects) as $asset) {
            $rows[] = [
                'offer_asset_id' => $asset['id'] ?? null,
                'asset_label' => $this->assetLabel($asset),
                'exposure_amount' => $asset['exposure_amount'] ?? null,
                'reference_market_value' => $asset['reference_market_value'] ?? null,
                'reference_liquidation_value' => $asset['reference_liquidation_value'] ?? null,
                'liquidation_discount_bps' => $asset['liquidation_discount_bps'] ?? null,
            ];
        }

        return $rows;
    }

    private function assetLabel(array $asset): string
    {
        $description = trim((string) ($asset['description'] ?? ''));
        $type = trim(str_replace('_', ' ', (string) ($asset['asset_type'] ?? '')));
        $label = $description !== '' ? $description : ($type !== '' ? mb_convert_case($type, MB_CASE_TITLE, 'UTF-8') : 'Aset');
        $areas = [];

        if (($asset['land_area_m2'] ?? null) !== null) {
            $areas[] = 'LT '.$this->decimal($asset['land_area_m2']).' m²';
        }

        if (($asset['building_area_m2'] ?? null) !== null) {
            $areas[] = 'LB '.$this->decimal($asset['building_area_m2']).' m²';
        }

        return $areas === [] ? $label : $label.' ('.implode('; ', $areas).')';
    }

    private function assetLocation(array $asset): string
    {
        $location = trim(implode(', ', array_filter([
            $asset['address'] ?? null,
            $asset['city'] ?? null,
            $asset['province'] ?? null,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        return $location !== '' ? $location : '[DRAF] Lokasi aset belum diisi';
    }

    private function assetDocumentLabel(array $documents): string
    {
        return implode('; ', array_values(array_filter(array_map(
            static fn (mixed $document): string => is_array($document)
                ? trim(implode(' ', array_filter([
                    $document['document_type'] ?? null,
                    $document['document_no'] ?? null,
                ])))
                : '',
            $documents,
        ))));
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

    /** @return array<string, mixed> */
    private function letterheadAsset(?IssuerProfileVersion $profile): array
    {
        $empty = [
            'configured' => false,
            'verified' => false,
            'path' => null,
            'mime' => null,
            'sha256' => null,
            'width' => null,
            'height' => null,
        ];

        if ($profile === null) {
            return $empty;
        }

        $path = $profile->letterhead_path;
        $mime = $profile->letterhead_mime;
        $sha256 = $profile->letterhead_sha256;

        if (! is_string($path) || trim($path) === '') {
            return [...$empty, 'configured' => false];
        }

        $asset = [...$empty, 'configured' => true, 'path' => $path, 'mime' => $mime, 'sha256' => $sha256];

        if (! is_string($mime)
            || ! in_array(mb_strtolower($mime), ['image/png', 'image/jpeg'], true)
            || ! is_string($sha256)
            || preg_match('/\A[a-f0-9]{64}\z/i', $sha256) !== 1) {
            return $asset;
        }

        $resolved = $this->resolveApprovedAsset($path);

        if ($resolved === null) {
            return $asset;
        }

        $actualMime = File::mimeType($resolved);
        $actualHash = hash_file('sha256', $resolved);
        $dimensions = @getimagesize($resolved);
        $verified = is_string($actualMime)
            && hash_equals(mb_strtolower($mime), mb_strtolower($actualMime))
            && is_string($actualHash)
            && hash_equals(mb_strtolower($sha256), mb_strtolower($actualHash))
            && is_array($dimensions)
            && ($dimensions[0] ?? 0) > 0
            && ($dimensions[1] ?? 0) > 0;

        return [
            ...$asset,
            'verified' => $verified,
            'width' => $verified ? (int) $dimensions[0] : null,
            'height' => $verified ? (int) $dimensions[1] : null,
        ];
    }

    private function resolveApprovedAsset(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === ''
            || str_starts_with($path, '/')
            || preg_match('/\A[a-z][a-z0-9+.-]*:/i', $path) === 1
            || in_array('..', explode('/', $path), true)) {
            return null;
        }

        $root = realpath((string) config('offer-documents.renderer.approved_asset_path'));
        $candidate = $root === false ? false : realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));

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

    private function formatDateValue(mixed $date, string $fallback): string
    {
        if ($date instanceof \DateTimeInterface) {
            return (int) $date->format('j').' '.self::MONTHS[(int) $date->format('n')].' '.$date->format('Y');
        }

        if (is_string($date) && preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return (int) $parts[3].' '.self::MONTHS[(int) $parts[2]].' '.$parts[1];
        }

        return $fallback;
    }

    private function humanText(mixed $value, string $missingLabel): string
    {
        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : '[DRAF] '.$missingLabel;
    }

    private function currencyLabel(mixed $currency): string
    {
        return match ($currency) {
            'IDR' => 'Rupiah (IDR)',
            'USD' => 'Dolar Amerika Serikat (USD)',
            'SGD' => 'Dolar Singapura (SGD)',
            default => is_string($currency) && trim($currency) !== ''
                ? strtoupper(trim($currency))
                : '[DRAF] Mata uang belum diisi',
        };
    }

    private function investigationLabel(mixed $value): string
    {
        return match ($value) {
            'desktop' => 'Investigasi desktop tanpa inspeksi fisik',
            'limited' => 'Investigasi terbatas',
            'full' => 'Investigasi penuh dan inspeksi fisik',
            default => '[DRAF] Tingkat investigasi belum diisi',
        };
    }

    private function reportFormatLabel(mixed $value): string
    {
        return match ($value) {
            'summary' => 'Laporan ringkas',
            'complete' => 'Laporan lengkap',
            default => '[DRAF] Format laporan belum diisi',
        };
    }

    private function languageLabel(mixed $value): string
    {
        return match ($value) {
            'id' => 'Bahasa Indonesia',
            'en' => 'Bahasa Inggris',
            default => '[DRAF] Bahasa laporan belum diisi',
        };
    }

    private function dayTypeLabel(mixed $value): string
    {
        return match ($value) {
            'business' => 'hari kerja',
            'calendar' => 'hari kalender',
            default => '[DRAF] jenis hari belum diisi',
        };
    }

    private function reportSpecification(array $engagement): string
    {
        $parts = [
            $this->reportFormatLabel($engagement['report_format'] ?? null),
            $this->languageLabel($engagement['report_language'] ?? null),
            isset($engagement['report_copies'])
                ? number_format((int) $engagement['report_copies'], 0, ',', '.').' eksemplar'
                : '[DRAF] jumlah eksemplar belum diisi',
        ];

        return implode(' · ', $parts);
    }

    private function completionTimeLabel(array $engagement): string
    {
        if (! isset($engagement['completion_days'])) {
            return '[DRAF] Durasi penyelesaian belum diisi';
        }

        return number_format((int) $engagement['completion_days'], 0, ',', '.').' '
            .$this->dayTypeLabel($engagement['completion_day_type'] ?? null);
    }

    private function money(int $amount, mixed $currency = 'IDR'): string
    {
        $code = is_string($currency) && trim($currency) !== '' ? strtoupper($currency) : 'IDR';
        $prefix = $code === 'IDR' ? 'Rp' : $code.' ';

        return $prefix.($code === 'IDR' ? '' : '').number_format($amount, 0, ',', '.');
    }

    private function nullableMoney(mixed $amount): string
    {
        return is_int($amount) || (is_string($amount) && preg_match('/\A\d+\z/', $amount) === 1)
            ? $this->money((int) $amount)
            : '—';
    }

    private function percentage(int $basisPoints): string
    {
        $value = number_format($basisPoints / 100, 2, ',', '.');
        $value = rtrim(rtrim($value, '0'), ',');

        return $value.'%';
    }

    private function decimal(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
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
            static fn (string $value): bool => ! OfferDocumentContentGuard::containsProvisionalMarker($value),
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
        return OfferDocumentContentGuard::containsProvisionalMarker($value);
    }

    private function isEffectiveOn(?string $date, ?string $effectiveFrom, ?string $effectiveUntil = null): bool
    {
        if ($date === null) {
            return false;
        }

        return ($effectiveFrom === null || $effectiveFrom <= $date)
            && ($effectiveUntil === null || $effectiveUntil >= $date);
    }
}
