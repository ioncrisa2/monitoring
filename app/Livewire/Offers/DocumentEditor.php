<?php

namespace App\Livewire\Offers;

use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\Offer;
use App\Models\OfferTemplateVersion;
use App\Models\User;
use App\Services\Offers\OfferDocumentMasterIntegrityService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DocumentEditor extends Component
{
    private const MAX_SUBJECTS = 100;

    private const MAX_ASSETS_PER_SUBJECT = 10;

    private const MAX_DOCUMENTS_PER_ASSET = 10;

    private const MAX_FEE_ITEMS = 500;

    private const MAX_PAYMENT_TERMS = 20;

    private const MAX_REQUIREMENTS = 100;

    private const BOOTSTRAPPER = 'App\\Services\\Offers\\OfferDocumentBootstrapper';

    private const SNAPSHOT_BUILDER = 'App\\Services\\Offers\\OfferSnapshotBuilder';

    private const PREFLIGHT_VALIDATOR = 'App\\Services\\Offers\\OfferPreflightValidator';

    #[Locked]
    public int $offerId;

    /**
     * Mutable editor payload. Immutable snapshots are built by the domain
     * service only after this draft has been persisted.
     *
     * @var array<string, mixed>
     */
    public array $draft = [];

    /** @var array{errors: list<string>, warnings: list<string>} */
    public array $preflight = [
        'errors' => [],
        'warnings' => [],
    ];

    #[Locked]
    public bool $printReadyEligible = false;

    public function mount(Offer $offer): void
    {
        $this->authorize('viewDocument', $offer);
        $this->offerId = $offer->getKey();

        $offer->load(['branch', 'debtor', 'client', 'reportUser']);
        $this->draft = $this->loadDraft($offer);
    }

    public function updatedDraft(): void
    {
        $this->preflight = ['errors' => [], 'warnings' => []];
        $this->printReadyEligible = false;
    }

    public function saveDraft(): void
    {
        $offer = $this->findOffer();
        $this->authorize('manageDocument', $offer);

        if (! class_exists(self::BOOTSTRAPPER)) {
            $this->addError('integration', 'Fondasi dokumen penawaran belum tersedia.');

            return;
        }

        $validated = $this->validate()['draft'];
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);

        $bootstrapper = app(self::BOOTSTRAPPER);

        try {
            $bootstrapper->saveDraft($offer, $validated, $actor);
        } catch (DomainException $exception) {
            $this->addError('draft', $exception->getMessage());

            return;
        }

        $this->draft = $bootstrapper->loadForm($offer->fresh());
        $this->preflight = ['errors' => [], 'warnings' => []];
        $this->printReadyEligible = false;

        session()->flash('message', 'Draft dokumen penawaran berhasil disimpan.');
    }

    public function checkPreflight(): void
    {
        $offer = $this->findOffer();
        $this->authorize('generateDocumentDraft', $offer);

        if (! class_exists(self::SNAPSHOT_BUILDER) || ! class_exists(self::PREFLIGHT_VALIDATOR)) {
            $this->preflight = [
                'errors' => ['Pemeriksaan dokumen belum tersedia.'],
                'warnings' => [],
            ];

            return;
        }

        $snapshot = app(self::SNAPSHOT_BUILDER)->build($offer);
        $result = app(self::PREFLIGHT_VALIDATOR)->validate($snapshot);

        $this->preflight = [
            'errors' => array_values($result['errors'] ?? []),
            'warnings' => array_values($result['warnings'] ?? []),
        ];
        $this->printReadyEligible = false;
    }

    public function checkPrintReady(): void
    {
        $offer = $this->findOffer();
        $this->authorize('generateDocumentPrintReady', $offer);

        if (! class_exists(self::SNAPSHOT_BUILDER) || ! class_exists(self::PREFLIGHT_VALIDATOR)) {
            $this->preflight = [
                'errors' => ['Pemeriksaan PDF siap cetak belum tersedia.'],
                'warnings' => [],
            ];
            $this->printReadyEligible = false;

            return;
        }

        $snapshot = app(self::SNAPSHOT_BUILDER)->build($offer);
        $validator = app(self::PREFLIGHT_VALIDATOR);
        $result = $validator->validate($snapshot, $validator::MODE_PRINT_READY);

        $this->preflight = [
            'errors' => array_values($result['errors'] ?? []),
            'warnings' => array_values($result['warnings'] ?? []),
        ];
        $this->printReadyEligible = $this->preflight['errors'] === [];
    }

    public function addSubject(): void
    {
        $this->authorizeManage();

        if (count($this->draft['subjects'] ?? []) >= self::MAX_SUBJECTS) {
            $this->addError('draft.subjects', 'Maksimal '.self::MAX_SUBJECTS.' pihak dalam satu penawaran.');

            return;
        }

        $this->draft['subjects'][] = $this->emptySubject(count($this->draft['subjects'] ?? []));
    }

    public function removeSubject(int $subjectIndex): void
    {
        $this->authorizeManage();

        if (count($this->draft['subjects'] ?? []) <= 1) {
            $this->addError('draft.subjects', 'Dokumen harus memiliki sedikitnya satu pihak.');

            return;
        }

        unset($this->draft['subjects'][$subjectIndex]);
        $this->draft['subjects'] = array_values($this->draft['subjects']);
        $this->resequence($this->draft['subjects']);
    }

    public function setPrimarySubject(int $subjectIndex): void
    {
        $this->authorizeManage();

        foreach ($this->draft['subjects'] as $index => &$subject) {
            $subject['is_primary'] = $index === $subjectIndex;
        }
    }

    public function addAsset(int $subjectIndex): void
    {
        $this->authorizeManage();

        if (count($this->draft['subjects'][$subjectIndex]['assets'] ?? []) >= self::MAX_ASSETS_PER_SUBJECT) {
            $this->addError("draft.subjects.{$subjectIndex}.assets", 'Maksimal '.self::MAX_ASSETS_PER_SUBJECT.' objek per pihak.');

            return;
        }

        $this->draft['subjects'][$subjectIndex]['assets'][] = $this->emptyAsset(
            count($this->draft['subjects'][$subjectIndex]['assets'] ?? []),
        );
    }

    public function removeAsset(int $subjectIndex, int $assetIndex): void
    {
        $this->authorizeManage();
        unset($this->draft['subjects'][$subjectIndex]['assets'][$assetIndex]);
        $this->draft['subjects'][$subjectIndex]['assets'] = array_values(
            $this->draft['subjects'][$subjectIndex]['assets'] ?? [],
        );
        $this->resequence($this->draft['subjects'][$subjectIndex]['assets']);
    }

    public function addAssetDocument(int $subjectIndex, int $assetIndex): void
    {
        $this->authorizeManage();
        $documents = &$this->draft['subjects'][$subjectIndex]['assets'][$assetIndex]['documents'];

        if (count($documents ?? []) >= self::MAX_DOCUMENTS_PER_ASSET) {
            $this->addError(
                "draft.subjects.{$subjectIndex}.assets.{$assetIndex}.documents",
                'Maksimal '.self::MAX_DOCUMENTS_PER_ASSET.' dokumen kepemilikan per objek.',
            );

            return;
        }

        $documents[] = $this->emptyAssetDocument(count($documents ?? []));
    }

    public function removeAssetDocument(int $subjectIndex, int $assetIndex, int $documentIndex): void
    {
        $this->authorizeManage();
        $documents = &$this->draft['subjects'][$subjectIndex]['assets'][$assetIndex]['documents'];
        unset($documents[$documentIndex]);
        $documents = array_values($documents ?? []);
        $this->resequence($documents);
    }

    public function setPrimaryAssetDocument(int $subjectIndex, int $assetIndex, int $documentIndex): void
    {
        $this->authorizeManage();
        $documents = &$this->draft['subjects'][$subjectIndex]['assets'][$assetIndex]['documents'];

        foreach ($documents as $index => &$document) {
            $document['is_primary'] = $index === $documentIndex;
        }
    }

    public function addFeeItem(): void
    {
        $this->authorizeManage();

        if (count($this->draft['fee_items'] ?? []) >= self::MAX_FEE_ITEMS) {
            $this->addError('draft.fee_items', 'Maksimal '.self::MAX_FEE_ITEMS.' komponen biaya.');

            return;
        }

        $this->draft['fee_items'][] = $this->emptyFeeItem(count($this->draft['fee_items'] ?? []));
    }

    public function removeFeeItem(int $index): void
    {
        $this->authorizeManage();
        unset($this->draft['fee_items'][$index]);
        $this->draft['fee_items'] = array_values($this->draft['fee_items'] ?? []);
        $this->resequence($this->draft['fee_items']);
    }

    public function addPaymentTerm(): void
    {
        $this->authorizeManage();

        if (count($this->draft['payment_terms'] ?? []) >= self::MAX_PAYMENT_TERMS) {
            $this->addError('draft.payment_terms', 'Maksimal '.self::MAX_PAYMENT_TERMS.' termin pembayaran.');

            return;
        }

        $this->draft['payment_terms'][] = [
            'sequence' => count($this->draft['payment_terms'] ?? []) + 1,
            'percentage_bps' => 0,
            'trigger_text' => '',
            'due_days' => null,
        ];
    }

    public function removePaymentTerm(int $index): void
    {
        $this->authorizeManage();
        unset($this->draft['payment_terms'][$index]);
        $this->draft['payment_terms'] = array_values($this->draft['payment_terms'] ?? []);

        foreach ($this->draft['payment_terms'] as $termIndex => &$term) {
            $term['sequence'] = $termIndex + 1;
        }
    }

    public function addRequirement(): void
    {
        $this->authorizeManage();

        if (count($this->draft['requirements'] ?? []) >= self::MAX_REQUIREMENTS) {
            $this->addError('draft.requirements', 'Maksimal '.self::MAX_REQUIREMENTS.' persyaratan data.');

            return;
        }

        $this->draft['requirements'][] = [
            'requirement_code' => null,
            'description_snapshot' => '',
            'emphasis_style' => 'normal',
            'sort_order' => count($this->draft['requirements'] ?? []),
        ];
    }

    public function removeRequirement(int $index): void
    {
        $this->authorizeManage();
        unset($this->draft['requirements'][$index]);
        $this->draft['requirements'] = array_values($this->draft['requirements'] ?? []);
        $this->resequence($this->draft['requirements']);
    }

    public function render()
    {
        $offer = $this->findOffer()->load(['branch', 'debtor', 'client', 'reportUser']);
        $this->authorize('viewDocument', $offer);

        $masterOptions = $this->approvedMasterOptions($offer);

        return view('livewire.offers.document-editor', [
            'offer' => $offer,
            'canManage' => Auth::user()?->can('manageDocument', $offer) === true,
            'canGenerateDraft' => Auth::user()?->can('generateDocumentDraft', $offer) === true,
            'canGeneratePrintReady' => Auth::user()?->can('generateDocumentPrintReady', $offer) === true,
            'domainReady' => class_exists(self::BOOTSTRAPPER),
            'rendererReady' => class_exists('App\\Services\\Offers\\OfferDocumentRenderer'),
            'printReadyRouteReady' => Route::has('offers.documents.print-ready'),
            ...$masterOptions,
        ])->layout('layouts.app');
    }

    /** @return array<string, array<int, string>|string> */
    protected function rules(): array
    {
        return [
            'draft.engagement.issue_city' => ['required', 'string', 'max:100'],
            'draft.engagement.lock_version' => ['required', 'integer', 'min:0'],
            'draft.engagement.recipient_attention' => ['nullable', 'string', 'max:150'],
            'draft.engagement.recipient_organization' => ['required', 'string', 'max:255'],
            'draft.engagement.recipient_address' => ['required', 'string', 'max:2000'],
            'draft.engagement.recipient_city' => ['nullable', 'string', 'max:150'],
            'draft.engagement.subject' => ['required', 'string', 'max:255'],
            'draft.engagement.request_reference_type' => ['required', 'in:letter,email,verbal,other,none'],
            'draft.engagement.request_reference_no' => ['nullable', 'string', 'max:150'],
            'draft.engagement.request_reference_date' => ['nullable', 'date'],
            'draft.engagement.opening_context' => ['nullable', 'string', 'max:2000'],
            'draft.engagement.ownership_form' => ['required', 'string', 'max:255'],
            'draft.engagement.currency' => ['required', 'string', 'size:3'],
            'draft.engagement.purpose' => ['required', 'string', 'max:255'],
            'draft.engagement.valuation_basis' => ['required', 'string', 'max:255'],
            'draft.engagement.valuation_date' => ['nullable', 'date'],
            'draft.engagement.valuation_date_rule' => ['nullable', 'string', 'max:255'],
            'draft.engagement.investigation_level' => ['required', 'string', 'max:100'],
            'draft.engagement.report_format' => ['required', 'string', 'max:100'],
            'draft.engagement.report_language' => ['required', 'string', 'max:20'],
            'draft.engagement.report_copies' => ['required', 'integer', 'min:1', 'max:100'],
            'draft.engagement.completion_days' => ['required', 'integer', 'min:1', 'max:365'],
            'draft.engagement.completion_day_type' => ['required', 'in:business,calendar'],
            'draft.engagement.tax_inclusion' => ['required', 'in:included,excluded,non_taxable'],
            'draft.engagement.ppn_rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'draft.engagement.pph_rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'draft.engagement.cost_inclusions' => ['array'],
            'draft.engagement.cost_inclusions.*' => ['string', 'max:100'],
            'draft.engagement.special_assumptions' => ['nullable', 'string', 'max:5000'],
            'draft.engagement.internal_note' => ['nullable', 'string', 'max:5000'],
            'draft.engagement.template_version_id' => ['nullable', 'integer'],
            'draft.engagement.issuer_profile_version_id' => ['nullable', 'integer'],
            'draft.engagement.signer_version_id' => ['nullable', 'integer'],
            'draft.subjects' => ['required', 'array', 'min:1', 'max:'.self::MAX_SUBJECTS],
            'draft.subjects.*.id' => ['nullable', 'integer'],
            'draft.subjects.*.debtor_id' => ['nullable', 'integer', 'exists:debtors,id'],
            'draft.subjects.*.name_snapshot' => ['required', 'string', 'max:255'],
            'draft.subjects.*.identifier_snapshot' => ['nullable', 'string', 'max:255'],
            'draft.subjects.*.address_snapshot' => ['nullable', 'string', 'max:2000'],
            'draft.subjects.*.is_primary' => ['boolean'],
            'draft.subjects.*.sort_order' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_SUBJECTS - 1)],
            'draft.subjects.*.assets' => ['required', 'array', 'min:1', 'max:'.self::MAX_ASSETS_PER_SUBJECT],
            'draft.subjects.*.assets.*.id' => ['nullable', 'integer'],
            'draft.subjects.*.assets.*.asset_type' => ['required', 'in:tanah,bangunan,mesin,kendaraan,inventaris,lainnya'],
            'draft.subjects.*.assets.*.description' => ['nullable', 'string', 'max:5000'],
            'draft.subjects.*.assets.*.address' => ['nullable', 'string', 'max:2000'],
            'draft.subjects.*.assets.*.city' => ['nullable', 'string', 'max:150'],
            'draft.subjects.*.assets.*.province' => ['nullable', 'string', 'max:150'],
            'draft.subjects.*.assets.*.land_area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'draft.subjects.*.assets.*.building_area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'draft.subjects.*.assets.*.inspection_note' => ['nullable', 'string', 'max:3000'],
            'draft.subjects.*.assets.*.sort_order' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_ASSETS_PER_SUBJECT - 1)],
            'draft.subjects.*.assets.*.documents' => ['array', 'max:'.self::MAX_DOCUMENTS_PER_ASSET],
            'draft.subjects.*.assets.*.documents.*.id' => ['nullable', 'integer'],
            'draft.subjects.*.assets.*.documents.*.document_type' => ['required', 'string', 'max:100'],
            'draft.subjects.*.assets.*.documents.*.document_no' => ['required', 'string', 'max:255'],
            'draft.subjects.*.assets.*.documents.*.issued_at' => ['nullable', 'date'],
            'draft.subjects.*.assets.*.documents.*.issuer' => ['nullable', 'string', 'max:255'],
            'draft.subjects.*.assets.*.documents.*.is_primary' => ['boolean'],
            'draft.subjects.*.assets.*.documents.*.sort_order' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_DOCUMENTS_PER_ASSET - 1)],
            'draft.subjects.*.assets.*.documents.*.note' => ['nullable', 'string', 'max:2000'],
            'draft.fee_items' => ['required', 'array', 'min:1', 'max:'.self::MAX_FEE_ITEMS],
            'draft.fee_items.*.id' => ['nullable', 'integer'],
            'draft.fee_items.*.offer_subject_id' => ['nullable', 'integer'],
            'draft.fee_items.*.offer_asset_id' => ['nullable', 'integer'],
            'draft.fee_items.*.label' => ['required', 'string', 'max:255'],
            'draft.fee_items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'draft.fee_items.*.unit_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'draft.fee_items.*.sort_order' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_FEE_ITEMS - 1)],
            'draft.payment_terms' => ['array', 'max:'.self::MAX_PAYMENT_TERMS],
            'draft.payment_terms.*.id' => ['nullable', 'integer'],
            'draft.payment_terms.*.sequence' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PAYMENT_TERMS],
            'draft.payment_terms.*.percentage_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'draft.payment_terms.*.trigger_text' => ['required', 'string', 'max:1000'],
            'draft.payment_terms.*.due_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'draft.requirements' => ['array', 'max:'.self::MAX_REQUIREMENTS],
            'draft.requirements.*.id' => ['nullable', 'integer'],
            'draft.requirements.*.requirement_code' => ['nullable', 'string', 'max:100'],
            'draft.requirements.*.description_snapshot' => ['required', 'string', 'max:2000'],
            'draft.requirements.*.emphasis_style' => ['required', 'in:normal,bold,italic,underline'],
            'draft.requirements.*.sort_order' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_REQUIREMENTS - 1)],
        ];
    }

    private function findOffer(): Offer
    {
        return Offer::query()->findOrFail($this->offerId);
    }

    /** @return array<string, mixed> */
    private function loadDraft(Offer $offer): array
    {
        if (class_exists(self::BOOTSTRAPPER)) {
            return app(self::BOOTSTRAPPER)->loadForm($offer);
        }

        return $this->defaultDraft($offer);
    }

    /** @return array<string, mixed> */
    private function defaultDraft(Offer $offer): array
    {
        return [
            'engagement' => [
                'lock_version' => 0,
                'issue_city' => '',
                'recipient_attention' => '',
                'recipient_organization' => $offer->client?->name ?? '',
                'recipient_address' => $offer->client?->address ?? '',
                'recipient_city' => '',
                'subject' => 'Penawaran Jasa Penilaian',
                'request_reference_type' => 'none',
                'request_reference_no' => '',
                'request_reference_date' => null,
                'opening_context' => '',
                'ownership_form' => 'Hak milik',
                'currency' => 'IDR',
                'purpose' => 'Penjaminan utang',
                'valuation_basis' => 'Nilai Pasar',
                'valuation_date' => $offer->offer_date?->format('Y-m-d'),
                'valuation_date_rule' => 'Tanggal inspeksi lapangan',
                'investigation_level' => 'full',
                'report_format' => 'complete',
                'report_language' => 'id',
                'report_copies' => 2,
                'completion_days' => 14,
                'completion_day_type' => 'business',
                'tax_inclusion' => 'excluded',
                'ppn_rate_bps' => 1100,
                'pph_rate_bps' => 200,
                'cost_inclusions' => [],
                'special_assumptions' => '',
                'internal_note' => $offer->note ?? '',
                'template_version_id' => null,
                'issuer_profile_version_id' => null,
                'signer_version_id' => null,
            ],
            'subjects' => [[
                'debtor_id' => $offer->debtor_id,
                'name_snapshot' => $offer->debtor?->name ?? '',
                'identifier_snapshot' => $offer->debtor?->identifier,
                'address_snapshot' => $offer->debtor?->address,
                'is_primary' => true,
                'sort_order' => 0,
                'assets' => [$this->emptyAsset(0)],
            ]],
            'fee_items' => [[
                'offer_subject_id' => null,
                'offer_asset_id' => null,
                'label' => 'Jasa penilaian',
                'quantity' => '1',
                'unit_amount' => (int) round((float) $offer->fee),
                'sort_order' => 0,
            ]],
            'payment_terms' => [],
            'requirements' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function emptySubject(int $sortOrder): array
    {
        return [
            'debtor_id' => null,
            'name_snapshot' => '',
            'identifier_snapshot' => '',
            'address_snapshot' => '',
            'is_primary' => false,
            'sort_order' => $sortOrder,
            'assets' => [$this->emptyAsset(0)],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyAsset(int $sortOrder): array
    {
        return [
            'asset_type' => 'tanah',
            'description' => '',
            'address' => '',
            'city' => '',
            'province' => '',
            'land_area_m2' => null,
            'building_area_m2' => null,
            'inspection_note' => '',
            'sort_order' => $sortOrder,
            'documents' => [$this->emptyAssetDocument(0)],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyAssetDocument(int $sortOrder): array
    {
        return [
            'document_type' => 'certificate',
            'document_no' => '',
            'issued_at' => null,
            'issuer' => '',
            'is_primary' => $sortOrder === 0,
            'sort_order' => $sortOrder,
            'note' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function emptyFeeItem(int $sortOrder): array
    {
        return [
            'offer_subject_id' => null,
            'offer_asset_id' => null,
            'label' => '',
            'quantity' => '1',
            'unit_amount' => 0,
            'sort_order' => $sortOrder,
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function resequence(array &$items): void
    {
        foreach ($items as $index => &$item) {
            $item['sort_order'] = $index;
        }
    }

    private function authorizeManage(): void
    {
        $this->authorize('manageDocument', $this->findOffer());
    }

    /** @return array<string, mixed> */
    private function approvedMasterOptions(Offer $offer): array
    {
        $effectiveOn = $offer->offer_date?->format('Y-m-d') ?? now()->toDateString();
        $integrity = app(OfferDocumentMasterIntegrityService::class);

        $templateVersions = OfferTemplateVersion::query()
            ->with('template')
            ->where('status', 'approved')
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            ->where('checksum', '<>', '')
            ->whereHas('template', fn ($query) => $query->where('active', true))
            ->where(fn ($query) => $query
                ->whereNull('effective_from')
                ->orWhereDate('effective_from', '<=', $effectiveOn))
            ->orderByDesc('effective_from')
            ->orderByDesc('version_no')
            ->get()
            ->filter(fn (OfferTemplateVersion $version): bool => $integrity->verify($version)
                && $integrity->templateSchemaErrors(
                    (array) $version->clause_schema,
                    is_array($version->condition_schema) ? $version->condition_schema : null,
                ) === [])
            ->values();

        $issuerProfiles = IssuerProfileVersion::query()
            ->where('branch_id', $offer->branch_id)
            ->where('status', 'approved')
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            ->where('checksum', '<>', '')
            ->where(fn ($query) => $query
                ->whereNull('effective_from')
                ->orWhereDate('effective_from', '<=', $effectiveOn))
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $effectiveOn))
            ->orderByDesc('effective_from')
            ->orderByDesc('version_no')
            ->get()
            ->filter(fn (IssuerProfileVersion $profile): bool => $integrity->verify($profile))
            ->values();

        $signerVersions = DocumentSignerVersion::query()
            ->where('branch_id', $offer->branch_id)
            ->where('status', 'approved')
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            ->where('checksum', '<>', '')
            ->where(fn ($query) => $query
                ->whereNull('effective_from')
                ->orWhereDate('effective_from', '<=', $effectiveOn))
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $effectiveOn))
            ->orderByDesc('effective_from')
            ->orderByDesc('version_no')
            ->get()
            ->filter(fn (DocumentSignerVersion $signer): bool => $integrity->verify($signer))
            ->values();

        return compact('templateVersions', 'issuerProfiles', 'signerVersions');
    }
}
