<?php

namespace App\Services\Offers;

use App\Enums\OfferDocumentVersionState;
use App\Enums\OfferFeePresentation;
use App\Enums\OfferTaxInclusion;
use App\Enums\OfferWorkflowState;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\Offer;
use App\Models\OfferAsset;
use App\Models\OfferAssetDocument;
use App\Models\OfferDocumentVersion;
use App\Models\OfferEngagement;
use App\Models\OfferFeeItem;
use App\Models\OfferPaymentTerm;
use App\Models\OfferRequirement;
use App\Models\OfferSubject;
use App\Models\OfferTemplateVersion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class OfferDocumentBootstrapper
{
    private const MAX_FEE_QUANTITY = 10_000;

    private const MAX_FEE_UNIT_AMOUNT = 999_999_999_999;

    private const MAX_AREA_M2 = 9_999_999_999_999.99;

    public const REQUEST_REFERENCE_TYPES = ['none', 'letter', 'email', 'verbal', 'other'];

    public const INVESTIGATION_LEVELS = ['desktop', 'limited', 'full'];

    public const REPORT_FORMATS = ['summary', 'complete'];

    public const REPORT_LANGUAGES = ['id', 'en'];

    public const COMPLETION_DAY_TYPES = ['business', 'calendar'];

    public const ASSET_TYPES = ['tanah', 'bangunan', 'mesin', 'kendaraan', 'inventaris', 'lainnya'];

    public const EMPHASIS_STYLES = ['normal', 'bold', 'italic', 'underline'];

    public function __construct(
        private readonly OfferNumberAllocator $numberAllocator,
        private readonly OfferDocumentMasterIntegrityService $masterIntegrity,
    ) {}

    /**
     * Load persisted draft data or non-persisted compatibility defaults for a legacy Offer.
     * Authorization must be enforced by the caller.
     *
     * @return array<string, mixed>
     */
    public function loadForm(Offer $offer): array
    {
        $offer->loadMissing([
            'branch',
            'debtor',
            'client',
            'reportUser',
            'engagement',
            'subjects.assets.documents',
            'feeItems',
            'paymentTerms',
            'requirements',
        ]);

        return [
            'engagement' => $offer->engagement
                ? $this->engagementToArray($offer->engagement)
                : $this->defaultEngagement($offer),
            'subjects' => $offer->subjects->isEmpty()
                ? $this->legacySubjects($offer)
                : $offer->subjects->map(fn (OfferSubject $subject): array => $this->subjectToArray($subject))->values()->all(),
            'fee_items' => $offer->feeItems->isEmpty()
                ? $this->legacyFeeItems($offer)
                : $offer->feeItems->map(fn (OfferFeeItem $item): array => $this->feeItemToArray($item))->values()->all(),
            'payment_terms' => $offer->paymentTerms
                ->map(fn (OfferPaymentTerm $term): array => $this->paymentTermToArray($term))
                ->values()
                ->all(),
            'requirements' => $offer->requirements
                ->map(fn (OfferRequirement $requirement): array => $this->requirementToArray($requirement))
                ->values()
                ->all(),
        ];
    }

    /**
     * Persist an incomplete-safe data draft. Completeness is enforced by preflight, not here.
     * Every nested identifier is resolved inside the selected Offer/branch scope.
     * Authorization must be enforced by the caller.
     *
     * @param  array<string, mixed>  $validated
     */
    public function saveDraft(Offer $offer, array $validated, User $actor): OfferEngagement
    {
        return DB::transaction(function () use ($offer, $validated, $actor) {
            $initialOffer = Offer::query()->lockForUpdate()->findOrFail($offer->getKey());

            if ($initialOffer->current_number_allocation_id === null && $initialOffer->sequence_no !== null) {
                $this->numberAllocator->adoptExisting($initialOffer, $actor);
            }

            $lockedOffer = Offer::query()
                ->with(['branch', 'debtor', 'client', 'reportUser'])
                ->lockForUpdate()
                ->findOrFail($offer->getKey());
            $existingEngagement = OfferEngagement::query()
                ->where('offer_id', $lockedOffer->getKey())
                ->lockForUpdate()
                ->first();

            $engagementPayload = $validated['engagement'] ?? [];

            if (! is_array($engagementPayload)) {
                throw new DomainException('Data engagement harus berupa objek.');
            }

            $expectedLockVersion = $this->nonNegativeInteger(
                $engagementPayload['lock_version'] ?? 0,
                'Versi kunci engagement',
            );

            if ($expectedLockVersion !== ($existingEngagement?->lock_version ?? 0)) {
                throw new DomainException('Draft telah berubah di sesi lain. Muat ulang sebelum menyimpan.');
            }

            if ($existingEngagement?->current_review_version_id !== null) {
                $reviewVersion = OfferDocumentVersion::query()
                    ->whereKey($existingEngagement->current_review_version_id)
                    ->where('offer_id', $lockedOffer->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($reviewVersion instanceof OfferDocumentVersion
                    && in_array($reviewVersion->version_state, [
                        OfferDocumentVersionState::InReview,
                        OfferDocumentVersionState::Approved,
                    ], true)) {
                    DB::table('offer_document_versions')
                        ->where('id', $reviewVersion->getKey())
                        ->update([
                            'version_state' => OfferDocumentVersionState::Superseded->value,
                            'lock_version' => DB::raw('lock_version + 1'),
                            'updated_at' => now(),
                        ]);
                }
            }

            $engagementData = $this->normalizeEngagement(
                $lockedOffer,
                $existingEngagement,
                $engagementPayload,
            );
            $engagementData['workflow_state'] = OfferWorkflowState::DataDraft;
            $engagementData['current_review_version_id'] = null;
            $engagementData['state_changed_by'] = $actor->getKey();
            $engagementData['state_changed_at'] = now();
            $engagementData['lock_version'] = $expectedLockVersion + 1;

            if ($existingEngagement === null) {
                $engagement = OfferEngagement::query()->create([
                    'offer_id' => $lockedOffer->getKey(),
                    ...$engagementData,
                ]);
            } else {
                $updated = OfferEngagement::query()
                    ->whereKey($existingEngagement->getKey())
                    ->where('lock_version', $expectedLockVersion)
                    ->update($engagementData);

                if ($updated !== 1) {
                    throw new DomainException('Draft telah berubah di sesi lain. Muat ulang sebelum menyimpan.');
                }

                $engagement = OfferEngagement::query()->findOrFail($existingEngagement->getKey());
            }

            if (array_key_exists('subjects', $validated)) {
                $this->syncSubjects($lockedOffer, $this->list($validated['subjects'], 'subjects'));
            }

            if (array_key_exists('fee_items', $validated)) {
                $this->syncFeeItems($lockedOffer, $this->list($validated['fee_items'], 'fee_items'));
            }

            if (array_key_exists('payment_terms', $validated)) {
                $this->syncPaymentTerms($lockedOffer, $this->list($validated['payment_terms'], 'payment_terms'));
            }

            if (array_key_exists('requirements', $validated)) {
                $this->syncRequirements($lockedOffer, $this->list($validated['requirements'], 'requirements'));
            }

            return $engagement->refresh();
        }, 5);
    }

    /** @return array<string, mixed> */
    private function normalizeEngagement(
        Offer $offer,
        ?OfferEngagement $existing,
        array $payload,
    ): array {
        $source = array_replace(
            $this->defaultEngagement($offer),
            $existing ? $this->engagementToArray($existing) : [],
            $payload,
        );

        $templateVersionId = $this->nullableId($source['template_version_id'] ?? null, 'Versi template');
        $issuerProfileVersionId = $this->nullableId(
            $source['issuer_profile_version_id'] ?? null,
            'Versi profil penerbit',
        );
        $signerVersionId = $this->nullableId($source['signer_version_id'] ?? null, 'Versi penandatangan');

        if ($templateVersionId !== null) {
            $templateVersion = OfferTemplateVersion::query()
                ->with('template')
                ->whereKey($templateVersionId)
                ->first();

            if (! $templateVersion instanceof OfferTemplateVersion) {
                throw new DomainException('Versi template tidak ditemukan.');
            }

            $this->assertSelectableMaster($templateVersion, $offer);

            if ($templateVersion->template?->active !== true) {
                throw new DomainException('Template penawaran tidak aktif.');
            }
        }

        if ($issuerProfileVersionId !== null) {
            $issuer = IssuerProfileVersion::query()
                ->whereKey($issuerProfileVersionId)
                ->where('branch_id', $offer->branch_id)
                ->first();

            if (! $issuer instanceof IssuerProfileVersion) {
                throw new DomainException('Profil penerbit tidak berada dalam scope cabang penawaran.');
            }

            $this->assertSelectableMaster($issuer, $offer);
        }

        if ($signerVersionId !== null) {
            $signer = DocumentSignerVersion::query()
                ->whereKey($signerVersionId)
                ->where('branch_id', $offer->branch_id)
                ->first();

            if (! $signer instanceof DocumentSignerVersion) {
                throw new DomainException('Penandatangan tidak berada dalam scope cabang penawaran.');
            }

            $this->assertSelectableMaster($signer, $offer);
        }

        return [
            'template_version_id' => $templateVersionId,
            'issuer_profile_version_id' => $issuerProfileVersionId,
            'signer_version_id' => $signerVersionId,
            'issue_city' => $this->nullableString($source['issue_city'] ?? null),
            'recipient_attention' => $this->nullableString($source['recipient_attention'] ?? null),
            'recipient_organization' => $this->nullableString($source['recipient_organization'] ?? null),
            'recipient_address' => $this->nullableString($source['recipient_address'] ?? null),
            'recipient_city' => $this->nullableString($source['recipient_city'] ?? null),
            'subject' => $this->nullableString($source['subject'] ?? null),
            'request_reference_type' => $this->oneOf(
                $source['request_reference_type'] ?? 'none',
                self::REQUEST_REFERENCE_TYPES,
                'Jenis referensi permintaan',
            ),
            'request_reference_no' => $this->nullableString($source['request_reference_no'] ?? null),
            'request_reference_date' => $this->nullableDate($source['request_reference_date'] ?? null),
            'opening_context' => $this->nullableString($source['opening_context'] ?? null),
            'ownership_form' => $this->nullableString($source['ownership_form'] ?? null),
            'currency' => $this->currency($source['currency'] ?? 'IDR'),
            'purpose' => $this->nullableString($source['purpose'] ?? null),
            'valuation_basis' => $this->nullableString($source['valuation_basis'] ?? null),
            'valuation_date' => $this->nullableDate($source['valuation_date'] ?? null),
            'valuation_date_rule' => $this->nullableString($source['valuation_date_rule'] ?? null),
            'investigation_level' => $this->nullableOneOf(
                $source['investigation_level'] ?? null,
                self::INVESTIGATION_LEVELS,
                'Tingkat investigasi',
            ),
            'report_format' => $this->nullableOneOf(
                $source['report_format'] ?? null,
                self::REPORT_FORMATS,
                'Format laporan',
            ),
            'report_language' => $this->oneOf(
                $source['report_language'] ?? 'id',
                self::REPORT_LANGUAGES,
                'Bahasa laporan',
            ),
            'report_copies' => $this->nullableBoundedPositiveInteger(
                $source['report_copies'] ?? null,
                100,
                'Jumlah salinan',
            ),
            'completion_days' => $this->nullableBoundedPositiveInteger(
                $source['completion_days'] ?? null,
                365,
                'Durasi penyelesaian',
            ),
            'completion_day_type' => $this->nullableOneOf(
                $source['completion_day_type'] ?? null,
                self::COMPLETION_DAY_TYPES,
                'Jenis hari penyelesaian',
            ),
            'tax_inclusion' => $this->nullableOneOf(
                $source['tax_inclusion'] ?? null,
                array_column(OfferTaxInclusion::cases(), 'value'),
                'Mode pajak',
            ),
            'fee_presentation' => $this->oneOf(
                $source['fee_presentation'] ?? OfferFeePresentation::LumpSum->value,
                array_column(OfferFeePresentation::cases(), 'value'),
                'Penyajian fee',
            ),
            'ppn_rate_bps' => $this->nullableRate($source['ppn_rate_bps'] ?? null, 'Tarif PPN'),
            'pph_rate_bps' => $this->nullableRate($source['pph_rate_bps'] ?? null, 'Tarif PPh'),
            'cost_inclusions' => $this->stringList($source['cost_inclusions'] ?? []),
            'special_assumptions' => $this->nullableString($source['special_assumptions'] ?? null),
            'internal_note' => $this->nullableString($source['internal_note'] ?? null),
        ];
    }

    /** @param list<mixed> $payload */
    private function syncSubjects(Offer $offer, array $payload): void
    {
        $this->assertMaxCount($payload, 100, 'Subject');
        $offer->subjects()->update([
            'primary_slot' => null,
            'sort_order' => DB::raw('sort_order + 1000000'),
        ]);
        $existing = $offer->subjects()->with('assets.documents')->lockForUpdate()->get()->keyBy('id');

        $kept = [];
        $sortOrders = [];
        $primaryCount = 0;

        foreach (array_values($payload) as $index => $row) {
            $row = $this->row($row, "subjects.{$index}");
            $id = $this->nullableId($row['id'] ?? null, 'ID subject');
            $subject = $id === null ? new OfferSubject : $existing->get($id);

            if (! $subject instanceof OfferSubject) {
                throw new DomainException('Subject tidak berada dalam scope penawaran ini.');
            }

            $sortOrder = $this->nonNegativeInteger($row['sort_order'] ?? $index, 'Urutan subject');
            $this->assertUnique($sortOrders, $sortOrder, 'Urutan subject');
            $isPrimary = $this->boolean($row['is_primary'] ?? false, 'Status subject utama');
            $primaryCount += $isPrimary ? 1 : 0;

            if ($primaryCount > 1) {
                throw new DomainException('Hanya satu subject yang boleh menjadi subject utama.');
            }

            $debtorId = $this->nullableId($row['debtor_id'] ?? null, 'Debitur subject');

            if ($debtorId !== null && ! DB::table('debtors')->where('id', $debtorId)->exists()) {
                throw new DomainException('Debitur subject tidak ditemukan.');
            }

            if ($isPrimary && $debtorId !== null && $debtorId !== $offer->debtor_id) {
                throw new DomainException('Debitur subject utama harus sama dengan debitur pada penawaran legacy.');
            }

            $subject->fill([
                'offer_id' => $offer->getKey(),
                'debtor_id' => $debtorId,
                'name_snapshot' => $this->requiredString($row['name_snapshot'] ?? null, 'Nama subject'),
                'identifier_snapshot' => $this->nullableString($row['identifier_snapshot'] ?? null),
                'address_snapshot' => $this->nullableString($row['address_snapshot'] ?? null),
                'primary_slot' => $isPrimary ? 1 : null,
                'sort_order' => $sortOrder,
            ])->save();
            $kept[] = $subject->getKey();

            if (array_key_exists('assets', $row)) {
                $this->syncAssets($subject, $this->list($row['assets'], "subjects.{$index}.assets"));
            }
        }

        $this->deleteMissing($offer->subjects(), $kept);
    }

    /** @param list<mixed> $payload */
    private function syncAssets(OfferSubject $subject, array $payload): void
    {
        $this->assertMaxCount($payload, 10, 'Aset per subject');
        $subject->assets()->update(['sort_order' => DB::raw('sort_order + 1000000')]);
        $existing = $subject->assets()->with('documents')->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $sortOrders = [];

        foreach (array_values($payload) as $index => $row) {
            $row = $this->row($row, "assets.{$index}");
            $id = $this->nullableId($row['id'] ?? null, 'ID aset');
            $asset = $id === null ? new OfferAsset : $existing->get($id);

            if (! $asset instanceof OfferAsset) {
                throw new DomainException('Aset tidak berada dalam scope subject ini.');
            }

            $sortOrder = $this->nonNegativeInteger($row['sort_order'] ?? $index, 'Urutan aset');
            $this->assertUnique($sortOrders, $sortOrder, 'Urutan aset');

            $asset->fill([
                'offer_subject_id' => $subject->getKey(),
                'asset_type' => $this->oneOf($row['asset_type'] ?? null, self::ASSET_TYPES, 'Jenis aset'),
                'description' => $this->nullableString($row['description'] ?? null),
                'address' => $this->nullableString($row['address'] ?? null),
                'city' => $this->nullableString($row['city'] ?? null),
                'province' => $this->nullableString($row['province'] ?? null),
                'land_area_m2' => $this->nullableDecimal(
                    $row['land_area_m2'] ?? null,
                    'Luas tanah',
                    self::MAX_AREA_M2,
                ),
                'building_area_m2' => $this->nullableDecimal(
                    $row['building_area_m2'] ?? null,
                    'Luas bangunan',
                    self::MAX_AREA_M2,
                ),
                'inspection_note' => $this->nullableString($row['inspection_note'] ?? null),
                'exposure_amount' => $this->nullableBoundedNonNegativeInteger(
                    $row['exposure_amount'] ?? null,
                    999_999_999_999_999,
                    'Exposure aset',
                ),
                'reference_market_value' => $this->nullableBoundedNonNegativeInteger(
                    $row['reference_market_value'] ?? null,
                    999_999_999_999_999,
                    'Referensi Nilai Pasar aset',
                ),
                'reference_liquidation_value' => $this->nullableBoundedNonNegativeInteger(
                    $row['reference_liquidation_value'] ?? null,
                    999_999_999_999_999,
                    'Referensi Nilai Likuidasi aset',
                ),
                'liquidation_discount_bps' => $this->nullableRate(
                    $row['liquidation_discount_bps'] ?? null,
                    'Diskon likuidasi aset',
                ),
                'sort_order' => $sortOrder,
            ])->save();
            $kept[] = $asset->getKey();

            if (array_key_exists('documents', $row)) {
                $this->syncAssetDocuments(
                    $asset,
                    $this->list($row['documents'], "assets.{$index}.documents"),
                );
            }
        }

        $this->deleteMissing($subject->assets(), $kept);
    }

    /** @param list<mixed> $payload */
    private function syncAssetDocuments(OfferAsset $asset, array $payload): void
    {
        $this->assertMaxCount($payload, 10, 'Dokumen per aset');
        $asset->documents()->update([
            'primary_slot' => null,
            'sort_order' => DB::raw('sort_order + 1000000'),
        ]);
        $existing = $asset->documents()->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $sortOrders = [];
        $documentKeys = [];
        $primaryCount = 0;

        foreach (array_values($payload) as $index => $row) {
            $row = $this->row($row, "documents.{$index}");
            $id = $this->nullableId($row['id'] ?? null, 'ID dokumen aset');
            $document = $id === null ? new OfferAssetDocument : $existing->get($id);

            if (! $document instanceof OfferAssetDocument) {
                throw new DomainException('Dokumen aset tidak berada dalam scope aset ini.');
            }

            $sortOrder = $this->nonNegativeInteger($row['sort_order'] ?? $index, 'Urutan dokumen aset');
            $this->assertUnique($sortOrders, $sortOrder, 'Urutan dokumen aset');
            $isPrimary = $this->boolean($row['is_primary'] ?? false, 'Status dokumen utama');
            $primaryCount += $isPrimary ? 1 : 0;

            if ($primaryCount > 1) {
                throw new DomainException('Hanya satu dokumen utama yang diperbolehkan per aset.');
            }

            $type = $this->requiredString($row['document_type'] ?? null, 'Jenis dokumen aset');
            $number = $this->requiredString($row['document_no'] ?? null, 'Nomor dokumen aset');
            $documentKey = mb_strtolower($type.'|'.$number);
            $this->assertUnique($documentKeys, $documentKey, 'Jenis dan nomor dokumen aset');

            $document->fill([
                'offer_asset_id' => $asset->getKey(),
                'document_type' => $type,
                'document_no' => $number,
                'issued_at' => $this->nullableDate($row['issued_at'] ?? null),
                'issuer' => $this->nullableString($row['issuer'] ?? null),
                'primary_slot' => $isPrimary ? 1 : null,
                'sort_order' => $sortOrder,
                'note' => $this->nullableString($row['note'] ?? null),
            ])->save();
            $kept[] = $document->getKey();
        }

        $this->deleteMissing($asset->documents(), $kept);
    }

    /** @param list<mixed> $payload */
    private function syncFeeItems(Offer $offer, array $payload): void
    {
        $this->assertMaxCount($payload, 500, 'Item fee');
        $offer->feeItems()->update(['sort_order' => DB::raw('sort_order + 1000000')]);
        $existing = $offer->feeItems()->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $sortOrders = [];

        foreach (array_values($payload) as $index => $row) {
            $row = $this->row($row, "fee_items.{$index}");
            $id = $this->nullableId($row['id'] ?? null, 'ID item fee');
            $item = $id === null ? new OfferFeeItem : $existing->get($id);

            if (! $item instanceof OfferFeeItem) {
                throw new DomainException('Item fee tidak berada dalam scope penawaran ini.');
            }

            $sortOrder = $this->nonNegativeInteger($row['sort_order'] ?? $index, 'Urutan item fee');
            $this->assertUnique($sortOrders, $sortOrder, 'Urutan item fee');
            $subjectId = $this->nullableId($row['offer_subject_id'] ?? null, 'Subject item fee');
            $assetId = $this->nullableId($row['offer_asset_id'] ?? null, 'Aset item fee');
            $asset = null;

            if ($subjectId !== null && ! $offer->subjects()->whereKey($subjectId)->exists()) {
                throw new DomainException('Subject item fee tidak berada dalam scope penawaran ini.');
            }

            if ($assetId !== null) {
                $asset = OfferAsset::query()
                    ->whereKey($assetId)
                    ->whereHas('subject', fn ($query) => $query->where('offer_id', $offer->getKey()))
                    ->first();

                if ($asset === null) {
                    throw new DomainException('Aset item fee tidak berada dalam scope penawaran ini.');
                }

                if ($subjectId !== null && $asset->offer_subject_id !== $subjectId) {
                    throw new DomainException('Subject dan aset item fee tidak saling terkait.');
                }

                $subjectId ??= $asset->offer_subject_id;
            }

            $item->fill([
                'offer_id' => $offer->getKey(),
                'offer_subject_id' => $subjectId,
                'offer_asset_id' => $assetId,
                'label' => $this->requiredString($row['label'] ?? null, 'Label item fee'),
                'quantity' => $this->boundedPositiveInteger(
                    $row['quantity'] ?? 1,
                    self::MAX_FEE_QUANTITY,
                    'Kuantitas item fee',
                ),
                'unit_amount' => $this->boundedNonNegativeInteger(
                    $row['unit_amount'] ?? null,
                    self::MAX_FEE_UNIT_AMOUNT,
                    'Nilai item fee',
                ),
                'sort_order' => $sortOrder,
            ])->save();
            $kept[] = $item->getKey();
        }

        $this->deleteMissing($offer->feeItems(), $kept);
    }

    /** @param list<mixed> $payload */
    private function syncPaymentTerms(Offer $offer, array $payload): void
    {
        $this->assertMaxCount($payload, 20, 'Termin pembayaran');
        $offer->paymentTerms()->update(['sequence' => DB::raw('sequence + 10000')]);
        $existing = $offer->paymentTerms()->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $sequences = [];

        foreach (array_values($payload) as $index => $row) {
            $row = $this->row($row, "payment_terms.{$index}");
            $id = $this->nullableId($row['id'] ?? null, 'ID termin');
            $term = $id === null ? new OfferPaymentTerm : $existing->get($id);

            if (! $term instanceof OfferPaymentTerm) {
                throw new DomainException('Termin tidak berada dalam scope penawaran ini.');
            }

            $sequence = $this->positiveInteger($row['sequence'] ?? $index + 1, 'Urutan termin');
            $this->assertUnique($sequences, $sequence, 'Urutan termin');

            $term->fill([
                'offer_id' => $offer->getKey(),
                'sequence' => $sequence,
                'percentage_bps' => $this->rate($row['percentage_bps'] ?? null, 'Persentase termin'),
                'trigger_text' => $this->requiredString($row['trigger_text'] ?? null, 'Pemicu termin'),
                'due_days' => $this->nullableBoundedNonNegativeInteger(
                    $row['due_days'] ?? null,
                    3650,
                    'Jatuh tempo termin',
                ),
            ])->save();
            $kept[] = $term->getKey();
        }

        $this->deleteMissing($offer->paymentTerms(), $kept);
    }

    /** @param list<mixed> $payload */
    private function syncRequirements(Offer $offer, array $payload): void
    {
        $this->assertMaxCount($payload, 100, 'Requirement');
        $offer->requirements()->update(['sort_order' => DB::raw('sort_order + 1000000')]);
        $existing = $offer->requirements()->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $sortOrders = [];

        foreach (array_values($payload) as $index => $row) {
            $row = $this->row($row, "requirements.{$index}");
            $id = $this->nullableId($row['id'] ?? null, 'ID requirement');
            $requirement = $id === null ? new OfferRequirement : $existing->get($id);

            if (! $requirement instanceof OfferRequirement) {
                throw new DomainException('Requirement tidak berada dalam scope penawaran ini.');
            }

            $sortOrder = $this->nonNegativeInteger($row['sort_order'] ?? $index, 'Urutan requirement');
            $this->assertUnique($sortOrders, $sortOrder, 'Urutan requirement');

            $requirement->fill([
                'offer_id' => $offer->getKey(),
                'requirement_code' => $this->nullableString($row['requirement_code'] ?? null),
                'description_snapshot' => $this->requiredString(
                    $row['description_snapshot'] ?? null,
                    'Deskripsi requirement',
                ),
                'emphasis_style' => $this->oneOf(
                    $row['emphasis_style'] ?? 'normal',
                    self::EMPHASIS_STYLES,
                    'Gaya requirement',
                ),
                'sort_order' => $sortOrder,
            ])->save();
            $kept[] = $requirement->getKey();
        }

        $this->deleteMissing($offer->requirements(), $kept);
    }

    /** @return array<string, mixed> */
    private function defaultEngagement(Offer $offer): array
    {
        return [
            'workflow_state' => OfferWorkflowState::DataDraft->value,
            'lock_version' => 0,
            'template_version_id' => null,
            'issuer_profile_version_id' => null,
            'signer_version_id' => null,
            'issue_city' => $offer->branch?->name,
            'recipient_attention' => null,
            'recipient_organization' => $offer->client?->name,
            'recipient_address' => $offer->client?->address,
            'recipient_city' => null,
            'subject' => 'Penawaran Jasa Penilaian',
            'request_reference_type' => 'none',
            'request_reference_no' => null,
            'request_reference_date' => null,
            'opening_context' => null,
            'ownership_form' => null,
            'currency' => 'IDR',
            'purpose' => null,
            'valuation_basis' => null,
            'valuation_date' => null,
            'valuation_date_rule' => null,
            'investigation_level' => null,
            'report_format' => null,
            'report_language' => 'id',
            'report_copies' => null,
            'completion_days' => null,
            'completion_day_type' => null,
            'tax_inclusion' => null,
            'fee_presentation' => OfferFeePresentation::LumpSum->value,
            'ppn_rate_bps' => 1100,
            'pph_rate_bps' => 200,
            'cost_inclusions' => [],
            'special_assumptions' => null,
            'internal_note' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function engagementToArray(OfferEngagement $engagement): array
    {
        return [
            'workflow_state' => $engagement->workflow_state->value,
            'lock_version' => $engagement->lock_version,
            'template_version_id' => $engagement->template_version_id,
            'issuer_profile_version_id' => $engagement->issuer_profile_version_id,
            'signer_version_id' => $engagement->signer_version_id,
            'issue_city' => $engagement->issue_city,
            'recipient_attention' => $engagement->recipient_attention,
            'recipient_organization' => $engagement->recipient_organization,
            'recipient_address' => $engagement->recipient_address,
            'recipient_city' => $engagement->recipient_city,
            'subject' => $engagement->subject,
            'request_reference_type' => $engagement->request_reference_type,
            'request_reference_no' => $engagement->request_reference_no,
            'request_reference_date' => $engagement->request_reference_date?->format('Y-m-d'),
            'opening_context' => $engagement->opening_context,
            'ownership_form' => $engagement->ownership_form,
            'currency' => $engagement->currency,
            'purpose' => $engagement->purpose,
            'valuation_basis' => $engagement->valuation_basis,
            'valuation_date' => $engagement->valuation_date?->format('Y-m-d'),
            'valuation_date_rule' => $engagement->valuation_date_rule,
            'investigation_level' => $engagement->investigation_level,
            'report_format' => $engagement->report_format,
            'report_language' => $engagement->report_language,
            'report_copies' => $engagement->report_copies,
            'completion_days' => $engagement->completion_days,
            'completion_day_type' => $engagement->completion_day_type,
            'tax_inclusion' => $engagement->tax_inclusion?->value,
            'fee_presentation' => $engagement->fee_presentation?->value ?? OfferFeePresentation::LumpSum->value,
            'ppn_rate_bps' => $engagement->ppn_rate_bps,
            'pph_rate_bps' => $engagement->pph_rate_bps,
            'cost_inclusions' => $engagement->cost_inclusions ?? [],
            'special_assumptions' => $engagement->special_assumptions,
            'internal_note' => $engagement->internal_note,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function legacySubjects(Offer $offer): array
    {
        if ($offer->debtor === null) {
            return [];
        }

        return [[
            'id' => null,
            'debtor_id' => $offer->debtor_id,
            'name_snapshot' => $offer->debtor->name,
            'identifier_snapshot' => $offer->debtor->identifier,
            'address_snapshot' => $offer->debtor->address,
            'is_primary' => true,
            'sort_order' => 0,
            'assets' => [],
        ]];
    }

    /** @return list<array<string, mixed>> */
    private function legacyFeeItems(Offer $offer): array
    {
        return [[
            'id' => null,
            'offer_subject_id' => null,
            'offer_asset_id' => null,
            'label' => 'Jasa Penilaian',
            'quantity' => 1,
            'unit_amount' => max(0, (int) round((float) $offer->fee, 0, PHP_ROUND_HALF_UP)),
            'sort_order' => 0,
        ]];
    }

    /** @return array<string, mixed> */
    private function subjectToArray(OfferSubject $subject): array
    {
        return [
            'id' => $subject->getKey(),
            'debtor_id' => $subject->debtor_id,
            'name_snapshot' => $subject->name_snapshot,
            'identifier_snapshot' => $subject->identifier_snapshot,
            'address_snapshot' => $subject->address_snapshot,
            'is_primary' => $subject->isPrimary(),
            'sort_order' => $subject->sort_order,
            'assets' => $subject->assets
                ->map(fn (OfferAsset $asset): array => $this->assetToArray($asset))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function assetToArray(OfferAsset $asset): array
    {
        return [
            'id' => $asset->getKey(),
            'asset_type' => $asset->asset_type,
            'description' => $asset->description,
            'address' => $asset->address,
            'city' => $asset->city,
            'province' => $asset->province,
            'land_area_m2' => $asset->land_area_m2,
            'building_area_m2' => $asset->building_area_m2,
            'inspection_note' => $asset->inspection_note,
            'exposure_amount' => $asset->exposure_amount,
            'reference_market_value' => $asset->reference_market_value,
            'reference_liquidation_value' => $asset->reference_liquidation_value,
            'liquidation_discount_bps' => $asset->liquidation_discount_bps,
            'sort_order' => $asset->sort_order,
            'documents' => $asset->documents
                ->map(fn (OfferAssetDocument $document): array => [
                    'id' => $document->getKey(),
                    'document_type' => $document->document_type,
                    'document_no' => $document->document_no,
                    'issued_at' => $document->issued_at?->format('Y-m-d'),
                    'issuer' => $document->issuer,
                    'is_primary' => $document->isPrimary(),
                    'sort_order' => $document->sort_order,
                    'note' => $document->note,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function feeItemToArray(OfferFeeItem $item): array
    {
        return [
            'id' => $item->getKey(),
            'offer_subject_id' => $item->offer_subject_id,
            'offer_asset_id' => $item->offer_asset_id,
            'label' => $item->label,
            'quantity' => $item->quantity,
            'unit_amount' => $item->unit_amount,
            'sort_order' => $item->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    private function paymentTermToArray(OfferPaymentTerm $term): array
    {
        return [
            'id' => $term->getKey(),
            'sequence' => $term->sequence,
            'percentage_bps' => $term->percentage_bps,
            'trigger_text' => $term->trigger_text,
            'due_days' => $term->due_days,
        ];
    }

    /** @return array<string, mixed> */
    private function requirementToArray(OfferRequirement $requirement): array
    {
        return [
            'id' => $requirement->getKey(),
            'requirement_code' => $requirement->requirement_code,
            'description_snapshot' => $requirement->description_snapshot,
            'emphasis_style' => $requirement->emphasis_style,
            'sort_order' => $requirement->sort_order,
        ];
    }

    /** @return list<mixed> */
    private function list(mixed $value, string $path): array
    {
        if (! is_array($value)) {
            throw new DomainException("{$path} harus berupa daftar.");
        }

        return array_values($value);
    }

    /** @return array<string, mixed> */
    private function row(mixed $value, string $path): array
    {
        if (! is_array($value)) {
            throw new DomainException("{$path} harus berupa objek.");
        }

        return $value;
    }

    private function deleteMissing($relation, array $kept): void
    {
        $query = $relation->getQuery();

        if ($kept !== []) {
            $query->whereNotIn($query->getModel()->getQualifiedKeyName(), $kept);
        }

        $query->delete();
    }

    private function assertUnique(array &$seen, int|string $value, string $label): void
    {
        if (in_array($value, $seen, true)) {
            throw new DomainException("{$label} tidak boleh duplikat.");
        }

        $seen[] = $value;
    }

    private function assertMaxCount(array $items, int $maximum, string $label): void
    {
        if (count($items) > $maximum) {
            throw new DomainException("{$label} melebihi batas {$maximum} item per penawaran.");
        }
    }

    private function oneOf(mixed $value, array $allowed, string $label): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new DomainException("{$label} tidak valid.");
        }

        return $value;
    }

    private function nullableOneOf(mixed $value, array $allowed, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->oneOf($value, $allowed, $label);
    }

    private function requiredString(mixed $value, string $label): string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            throw new DomainException("{$label} wajib diisi.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new DomainException('Nilai teks tidak valid.');
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function currency(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[A-Z]{3}$/', strtoupper($value))) {
            throw new DomainException('Kode mata uang tidak valid.');
        }

        return strtoupper($value);
    }

    private function boolean(mixed $value, string $label): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (bool) $value;
        }

        throw new DomainException("{$label} harus berupa boolean.");
    }

    private function nullableId(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->positiveInteger($value, $label);
    }

    private function positiveInteger(mixed $value, string $label): int
    {
        $integer = $this->nonNegativeInteger($value, $label);

        if ($integer < 1) {
            throw new DomainException("{$label} harus minimal 1.");
        }

        return $integer;
    }

    private function nullableBoundedPositiveInteger(mixed $value, int $maximum, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->boundedPositiveInteger($value, $maximum, $label);
    }

    private function boundedPositiveInteger(mixed $value, int $maximum, string $label): int
    {
        $integer = $this->positiveInteger($value, $label);

        if ($integer > $maximum) {
            throw new DomainException("{$label} tidak boleh melebihi {$maximum}.");
        }

        return $integer;
    }

    private function nullableBoundedNonNegativeInteger(mixed $value, int $maximum, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->boundedNonNegativeInteger($value, $maximum, $label);
    }

    private function boundedNonNegativeInteger(mixed $value, int $maximum, string $label): int
    {
        $integer = $this->nonNegativeInteger($value, $label);

        if ($integer > $maximum) {
            throw new DomainException("{$label} tidak boleh melebihi {$maximum}.");
        }

        return $integer;
    }

    private function nonNegativeInteger(mixed $value, string $label): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX],
            ]);

            if ($filtered === false) {
                throw new DomainException("{$label} berada di luar rentang integer yang didukung.");
            }

            $integer = $filtered;
        } else {
            throw new DomainException("{$label} harus berupa bilangan bulat.");
        }

        if ($integer < 0) {
            throw new DomainException("{$label} tidak boleh negatif.");
        }

        return $integer;
    }

    private function nullableRate(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->rate($value, $label);
    }

    private function rate(mixed $value, string $label): int
    {
        $rate = $this->nonNegativeInteger($value, $label);

        if ($rate > 10_000) {
            throw new DomainException("{$label} tidak boleh melebihi 10000 basis point.");
        }

        return $rate;
    }

    private function nullableDecimal(mixed $value, string $label, float $maximum): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new DomainException("{$label} harus berupa angka non-negatif.");
        }

        if ((float) $value > $maximum) {
            throw new DomainException("{$label} melebihi batas yang didukung.");
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)
            || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new DomainException('Tanggal harus menggunakan format YYYY-MM-DD yang valid.');
        }

        return $value;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value)) {
            throw new DomainException('Daftar komponen biaya harus berupa array.');
        }

        $items = [];

        foreach ($value as $item) {
            $item = $this->requiredString($item, 'Komponen biaya');

            if (! in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function assertSelectableMaster(
        OfferTemplateVersion|IssuerProfileVersion|DocumentSignerVersion $master,
        Offer $offer,
    ): void {
        $this->masterIntegrity->assertApprovedIntegrity($master);
        $effectiveOn = $offer->offer_date ?? now();

        if ($master->effective_from === null || $master->effective_from->gt($effectiveOn)) {
            throw new DomainException('Master dokumen belum berlaku pada tanggal penawaran.');
        }

        if ($master->effective_until !== null && $master->effective_until->lt($effectiveOn)) {
            throw new DomainException('Master dokumen sudah tidak berlaku pada tanggal penawaran.');
        }
    }
}
