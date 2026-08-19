<?php

namespace App\Livewire\Master;

use App\Enums\OfferDocumentMasterReviewStatus;
use App\Enums\OfferFeePresentation;
use App\Enums\OfferTaxInclusion;
use App\Enums\OfferTemplateBlockType;
use App\Enums\OfferTemplateCategory;
use App\Enums\OfferTemplateCondition;
use App\Enums\OfferTemplateDynamicSource;
use App\Models\Branch;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\OfferTemplate;
use App\Models\OfferTemplateVersion;
use App\Services\Offers\OfferDocumentMasterApprovalService;
use App\Services\Offers\OfferTemplateSchemaV2;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class OfferDocumentMasters extends Component
{
    use WithFileUploads;

    public string $activeTab = 'templates';

    public bool $showTemplateEditor = false;

    public ?int $templateId = null;

    public ?int $templateVersionId = null;

    public string $templateCode = '';

    public string $templateName = '';

    public string $templatePurpose = '';

    public string $templateCategory = OfferTemplateCategory::PropertyCollateral->value;

    public string $templateEffectiveFrom = '';

    public string $templateEffectiveUntil = '';

    public string $templateOpening = '';

    public string $templateClosing = '';

    public array $templateDefaults = [];

    public array $templateClauses = [];

    public array $templateConstraints = [];

    public string $templateCostInclusions = '';

    public bool $showIssuerEditor = false;

    public ?int $issuerVersionId = null;

    public ?int $issuerBranchId = null;

    public string $issuerLegalName = '';

    public string $issuerPermitNo = '';

    public string $issuerOfficeLabel = '';

    public string $issuerAddress = '';

    public string $issuerCity = '';

    public string $issuerPhone = '';

    public string $issuerEmail = '';

    public string $issuerEffectiveFrom = '';

    public string $issuerEffectiveUntil = '';

    public $letterheadUpload = null;

    public ?string $issuerExistingLetterhead = null;

    public bool $showSignerEditor = false;

    public ?int $signerVersionId = null;

    public ?int $signerBranchId = null;

    public string $signerKey = '';

    public string $signerFullName = '';

    public string $signerTitleSuffix = '';

    public string $signerPosition = '';

    public string $signerPermitNo = '';

    public string $signerRegistrationNo = '';

    public string $signerPhone = '';

    public string $signerEmail = '';

    public string $signerEffectiveFrom = '';

    public string $signerEffectiveUntil = '';

    public bool $showReviewDialog = false;

    public string $reviewAction = '';

    public string $reviewMasterType = '';

    public ?int $reviewMasterId = null;

    public string $reviewNote = '';

    public bool $showPreview = false;

    public string $previewTitle = '';

    public string $previewOpening = '';

    public string $previewClosing = '';

    public array $previewClauses = [];

    public function mount(): void
    {
        $this->authorize('offers.document-masters.view');
    }

    public function setTab(string $tab): void
    {
        $this->authorize('offers.document-masters.view');

        if (! in_array($tab, ['templates', 'issuers', 'signers'], true)) {
            abort(404);
        }

        $this->activeTab = $tab;
        $this->closeEditors();
        $this->resetErrorBag();
    }

    public function createTemplate(): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->resetTemplateEditor();
        $this->loadTemplateSchema($this->defaultTemplateSchema($this->templateCategory));
        $this->templateEffectiveFrom = now()->toDateString();
        $this->showTemplateEditor = true;
    }

    public function editTemplate(int $versionId): void
    {
        $this->authorize('offers.document-masters.manage');
        $version = OfferTemplateVersion::query()->with('template')->findOrFail($versionId);
        $this->assertDraft($version);

        if ($version->schema_version !== OfferTemplateSchemaV2::SCHEMA_VERSION) {
            $this->addError('workflow', 'Template legacy hanya dapat dibaca. Buat template schema v2 baru untuk perubahan.');

            return;
        }

        $this->resetTemplateEditor();
        $this->templateId = $version->offer_template_id;
        $this->templateVersionId = $version->id;
        $this->templateCode = $version->template->code;
        $this->templateName = $version->template->name;
        $this->templatePurpose = (string) $version->template->purpose;
        $category = $version->template->category;
        $this->templateCategory = $category instanceof OfferTemplateCategory ? $category->value : (string) $category;
        $this->templateEffectiveFrom = $version->effective_from?->toDateString() ?? '';
        $this->templateEffectiveUntil = $version->effective_until?->toDateString() ?? '';
        $this->loadTemplateSchema((array) $version->clause_schema);
        $this->showTemplateEditor = true;
    }

    public function copyTemplate(int $versionId): void
    {
        $this->authorize('offers.document-masters.manage');
        $source = OfferTemplateVersion::query()->with('template')->findOrFail($versionId);

        if ($source->schema_version !== OfferTemplateSchemaV2::SCHEMA_VERSION) {
            $this->addError('workflow', 'Versi legacy tidak dapat disalin sebagai versi baru. Gunakan template schema v2.');

            return;
        }

        $version = DB::transaction(function () use ($source): OfferTemplateVersion {
            OfferTemplate::query()->lockForUpdate()->findOrFail($source->offer_template_id);
            $nextVersion = (int) OfferTemplateVersion::query()
                ->where('offer_template_id', $source->offer_template_id)
                ->max('version_no') + 1;

            return OfferTemplateVersion::create([
                'offer_template_id' => $source->offer_template_id,
                'version_no' => $nextVersion,
                'schema_version' => OfferTemplateSchemaV2::SCHEMA_VERSION,
                'clause_schema' => $source->clause_schema,
                'condition_schema' => null,
                'layout_version' => OfferTemplateSchemaV2::LAYOUT_VERSION,
                'header_mode' => OfferTemplateSchemaV2::HEADER_MODE,
                'status' => OfferDocumentMasterReviewStatus::Draft->value,
                'effective_from' => $source->effective_from?->toDateString(),
                'effective_until' => $source->effective_until?->toDateString(),
                'created_by' => auth()->id(),
            ]);
        });

        session()->flash('message', "Template {$source->template->name} versi {$version->version_no} berhasil dibuat sebagai draft.");
        $this->editTemplate($version->id);
    }

    public function updatedTemplateCategory(string $category): void
    {
        if ($this->templateId !== null || ! in_array($category, array_column(OfferTemplateCategory::cases(), 'value'), true)) {
            return;
        }

        $schema = $this->defaultTemplateSchema($category);
        $this->templatePurpose = $this->categoryPurpose($category);
        $this->loadTemplateSchema($schema);
    }

    public function addTemplateBlock(string $clauseKey): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->assertClauseKey($clauseKey);
        $this->templateClauses[$clauseKey]['blocks'][] = $this->emptyEditorBlock();
    }

    public function removeTemplateBlock(string $clauseKey, int $index): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->assertClauseKey($clauseKey);

        if (count($this->templateClauses[$clauseKey]['blocks'] ?? []) <= 1) {
            $this->addError("templateClauses.{$clauseKey}", 'Setiap klausul wajib memiliki sedikitnya satu blok.');

            return;
        }

        unset($this->templateClauses[$clauseKey]['blocks'][$index]);
        $this->templateClauses[$clauseKey]['blocks'] = array_values($this->templateClauses[$clauseKey]['blocks']);
    }

    public function addPaymentTerm(): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->templateDefaults['payment_terms'][] = [
            'percentage_bps' => 0,
            'trigger_text' => '',
            'due_days' => null,
        ];
    }

    public function removePaymentTerm(int $index): void
    {
        $this->authorize('offers.document-masters.manage');
        unset($this->templateDefaults['payment_terms'][$index]);
        $this->templateDefaults['payment_terms'] = array_values($this->templateDefaults['payment_terms']);
    }

    public function addRequirement(): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->templateDefaults['requirements'][] = [
            'requirement_code' => null,
            'description' => '',
            'emphasis_style' => 'normal',
        ];
    }

    public function removeRequirement(int $index): void
    {
        $this->authorize('offers.document-masters.manage');
        unset($this->templateDefaults['requirements'][$index]);
        $this->templateDefaults['requirements'] = array_values($this->templateDefaults['requirements']);
    }

    public function saveTemplate(): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->resetErrorBag();

        $validated = $this->validate([
            'templateCode' => [
                'required', 'string', 'max:64', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('offer_templates', 'code')->ignore($this->templateId),
            ],
            'templateName' => ['required', 'string', 'max:255'],
            'templatePurpose' => ['required', 'string', 'max:1000'],
            'templateCategory' => ['required', Rule::enum(OfferTemplateCategory::class)],
            'templateEffectiveFrom' => ['required', 'date'],
            'templateEffectiveUntil' => ['nullable', 'date', 'after_or_equal:templateEffectiveFrom'],
            'templateOpening' => ['required', 'string', 'max:5000'],
            'templateClosing' => ['required', 'string', 'max:5000'],
        ], [
            'templateCode.regex' => 'Kode template hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
        ]);

        $schema = $this->templateSchemaFromEditor();
        $this->assertSafeTemplateSchema($schema);

        DB::transaction(function () use ($validated, $schema): void {
            if ($this->templateVersionId !== null) {
                $version = OfferTemplateVersion::query()->lockForUpdate()->findOrFail($this->templateVersionId);
                $this->assertDraft($version);
                $version->update([
                    'clause_schema' => $schema,
                    'condition_schema' => null,
                    'effective_from' => $validated['templateEffectiveFrom'],
                    'effective_until' => $validated['templateEffectiveUntil'] ?: null,
                ]);

                return;
            }

            $template = OfferTemplate::create([
                'code' => $validated['templateCode'],
                'name' => $validated['templateName'],
                'purpose' => $validated['templatePurpose'],
                'category' => $validated['templateCategory'],
                'active' => true,
                'is_default' => false,
            ]);

            OfferTemplateVersion::create([
                'offer_template_id' => $template->id,
                'version_no' => 1,
                'schema_version' => OfferTemplateSchemaV2::SCHEMA_VERSION,
                'clause_schema' => $schema,
                'condition_schema' => null,
                'layout_version' => OfferTemplateSchemaV2::LAYOUT_VERSION,
                'header_mode' => OfferTemplateSchemaV2::HEADER_MODE,
                'status' => OfferDocumentMasterReviewStatus::Draft->value,
                'effective_from' => $validated['templateEffectiveFrom'],
                'effective_until' => $validated['templateEffectiveUntil'] ?: null,
                'created_by' => auth()->id(),
            ]);
        });

        $this->showTemplateEditor = false;
        session()->flash('message', 'Draft template berhasil disimpan. Ajukan review setelah seluruh redaksi diperiksa.');
    }

    public function previewTemplate(int $versionId): void
    {
        $this->authorize('offers.document-masters.view');
        $version = OfferTemplateVersion::query()->with('template')->findOrFail($versionId);
        $schema = (array) $version->clause_schema;
        $this->previewTitle = "Fixture anonim — {$version->template->name} v{$version->version_no}";
        $this->previewOpening = $this->replaceFixtureTokens((string) data_get($schema, 'document.opening', ''));
        $this->previewClosing = $this->replaceFixtureTokens((string) data_get($schema, 'document.closing', ''));
        $this->previewClauses = [];

        foreach ((array) config('offer-documents.clause_titles', []) as $key => $title) {
            $blocks = [];
            $rawBlocks = data_get($schema, "clauses.{$key}.blocks");

            if (! is_array($rawBlocks) && $version->schema_version === 1) {
                $legacyClause = (array) data_get($schema, "clauses.{$key}", []);
                $rawBlocks = [];

                foreach ((array) ($legacyClause['paragraphs'] ?? []) as $paragraph) {
                    $rawBlocks[] = ['type' => OfferTemplateBlockType::Text->value, 'text' => $paragraph];
                }

                if ((array) ($legacyClause['items'] ?? []) !== []) {
                    $rawBlocks[] = ['type' => OfferTemplateBlockType::Bullets->value, 'items' => $legacyClause['items']];
                }
            }

            foreach ((array) $rawBlocks as $block) {
                if (is_array($block)) {
                    $blocks[] = $this->fixtureBlock($block);
                }
            }

            $this->previewClauses[] = ['title' => $title, 'blocks' => $blocks];
        }

        $this->showPreview = true;
    }

    public function createIssuer(): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->resetIssuerEditor();
        $this->issuerBranchId = auth()->user()->branch_id;
        $this->issuerEffectiveFrom = now()->toDateString();
        $this->showIssuerEditor = true;
    }

    public function editIssuer(int $versionId): void
    {
        $this->authorize('offers.document-masters.manage');
        $version = $this->issuerQuery()->findOrFail($versionId);
        $this->assertDraft($version);
        $this->resetIssuerEditor();
        $this->issuerVersionId = $version->id;
        $this->issuerBranchId = $version->branch_id;
        $this->issuerLegalName = $version->legal_name;
        $this->issuerPermitNo = (string) $version->permit_no;
        $this->issuerOfficeLabel = (string) $version->office_label;
        $this->issuerAddress = $version->address;
        $this->issuerCity = $version->city;
        $this->issuerPhone = (string) $version->phone;
        $this->issuerEmail = (string) $version->email;
        $this->issuerEffectiveFrom = $version->effective_from?->toDateString() ?? '';
        $this->issuerEffectiveUntil = $version->effective_until?->toDateString() ?? '';
        $this->issuerExistingLetterhead = $version->letterhead_path;
        $this->showIssuerEditor = true;
    }

    public function copyIssuer(int $versionId): void
    {
        $this->authorize('offers.document-masters.manage');
        $source = $this->issuerQuery()->findOrFail($versionId);
        $copy = DB::transaction(function () use ($source): IssuerProfileVersion {
            Branch::query()->lockForUpdate()->findOrFail($source->branch_id);
            $nextVersion = (int) IssuerProfileVersion::query()
                ->where('branch_id', $source->branch_id)
                ->max('version_no') + 1;

            return IssuerProfileVersion::create([
                ...$source->only([
                    'branch_id', 'legal_name', 'permit_no', 'office_label', 'address', 'city', 'phone', 'email',
                    'letterhead_path', 'letterhead_sha256', 'letterhead_mime', 'letterhead_width_px',
                    'letterhead_height_px', 'letterhead_size_bytes', 'effective_from', 'effective_until',
                ]),
                'version_no' => $nextVersion,
                'status' => OfferDocumentMasterReviewStatus::Draft->value,
                'created_by' => auth()->id(),
            ]);
        });

        session()->flash('message', "Profil penerbit versi {$copy->version_no} berhasil dibuat sebagai draft.");
        $this->editIssuer($copy->id);
    }

    public function saveIssuer(): void
    {
        $this->authorize('offers.document-masters.manage');
        $validated = $this->validate([
            'issuerBranchId' => ['required', 'integer', 'exists:branches,id'],
            'issuerLegalName' => ['required', 'string', 'max:255'],
            'issuerPermitNo' => ['nullable', 'string', 'max:255'],
            'issuerOfficeLabel' => ['nullable', 'string', 'max:255'],
            'issuerAddress' => ['required', 'string', 'max:5000'],
            'issuerCity' => ['required', 'string', 'max:255'],
            'issuerPhone' => ['nullable', 'string', 'max:100'],
            'issuerEmail' => ['nullable', 'email', 'max:255'],
            'issuerEffectiveFrom' => ['required', 'date'],
            'issuerEffectiveUntil' => ['nullable', 'date', 'after_or_equal:issuerEffectiveFrom'],
            'letterheadUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:10240', 'dimensions:min_width=300,min_height=50,max_width=10000,max_height=5000'],
        ]);
        $this->assertBranchAccess((int) $validated['issuerBranchId']);

        $letterhead = $this->letterheadUpload === null ? [] : $this->storeLetterhead();

        DB::transaction(function () use ($validated, $letterhead): void {
            if ($this->issuerVersionId !== null) {
                $version = $this->issuerQuery()->lockForUpdate()->findOrFail($this->issuerVersionId);
                $this->assertDraft($version);
                $validated['issuerBranchId'] = $version->branch_id;
            } else {
                Branch::query()->lockForUpdate()->findOrFail($validated['issuerBranchId']);
                $versionNo = (int) IssuerProfileVersion::query()
                    ->where('branch_id', $validated['issuerBranchId'])
                    ->max('version_no') + 1;
                $version = new IssuerProfileVersion([
                    'branch_id' => $validated['issuerBranchId'],
                    'version_no' => $versionNo,
                    'status' => OfferDocumentMasterReviewStatus::Draft->value,
                    'created_by' => auth()->id(),
                ]);
            }

            $version->fill([
                'branch_id' => $validated['issuerBranchId'],
                'legal_name' => $validated['issuerLegalName'],
                'permit_no' => $validated['issuerPermitNo'] ?: null,
                'office_label' => $validated['issuerOfficeLabel'] ?: null,
                'address' => $validated['issuerAddress'],
                'city' => $validated['issuerCity'],
                'phone' => $validated['issuerPhone'] ?: null,
                'email' => $validated['issuerEmail'] ?: null,
                'effective_from' => $validated['issuerEffectiveFrom'],
                'effective_until' => $validated['issuerEffectiveUntil'] ?: null,
                ...$letterhead,
            ])->save();
        });

        $this->showIssuerEditor = false;
        $this->letterheadUpload = null;
        session()->flash('message', 'Draft profil penerbit berhasil disimpan.');
    }

    public function createSigner(): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->resetSignerEditor();
        $this->signerBranchId = auth()->user()->branch_id;
        $this->signerEffectiveFrom = now()->toDateString();
        $this->showSignerEditor = true;
    }

    public function editSigner(int $versionId): void
    {
        $this->authorize('offers.document-masters.manage');
        $version = $this->signerQuery()->findOrFail($versionId);
        $this->assertDraft($version);
        $this->resetSignerEditor();
        $this->signerVersionId = $version->id;
        $this->signerBranchId = $version->branch_id;
        $this->signerKey = $version->signer_key;
        $this->signerFullName = $version->full_name;
        $this->signerTitleSuffix = (string) $version->title_suffix;
        $this->signerPosition = $version->position;
        $this->signerPermitNo = (string) $version->permit_no;
        $this->signerRegistrationNo = (string) $version->registration_no;
        $this->signerPhone = (string) $version->phone;
        $this->signerEmail = (string) $version->email;
        $this->signerEffectiveFrom = $version->effective_from?->toDateString() ?? '';
        $this->signerEffectiveUntil = $version->effective_until?->toDateString() ?? '';
        $this->showSignerEditor = true;
    }

    public function copySigner(int $versionId): void
    {
        $this->authorize('offers.document-masters.manage');
        $source = $this->signerQuery()->findOrFail($versionId);
        $copy = DB::transaction(function () use ($source): DocumentSignerVersion {
            Branch::query()->lockForUpdate()->findOrFail($source->branch_id);
            $nextVersion = (int) DocumentSignerVersion::query()
                ->where('branch_id', $source->branch_id)
                ->where('signer_key', $source->signer_key)
                ->max('version_no') + 1;

            return DocumentSignerVersion::create([
                ...$source->only([
                    'branch_id', 'signer_key', 'full_name', 'title_suffix', 'position', 'permit_no',
                    'registration_no', 'phone', 'email', 'effective_from', 'effective_until',
                ]),
                'version_no' => $nextVersion,
                'status' => OfferDocumentMasterReviewStatus::Draft->value,
                'created_by' => auth()->id(),
            ]);
        });

        session()->flash('message', "Penandatangan {$copy->full_name} versi {$copy->version_no} berhasil dibuat sebagai draft.");
        $this->editSigner($copy->id);
    }

    public function saveSigner(): void
    {
        $this->authorize('offers.document-masters.manage');
        $validated = $this->validate([
            'signerBranchId' => ['required', 'integer', 'exists:branches,id'],
            'signerKey' => ['required', 'string', 'max:64', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'signerFullName' => ['required', 'string', 'max:255'],
            'signerTitleSuffix' => ['nullable', 'string', 'max:100'],
            'signerPosition' => ['required', 'string', 'max:255'],
            'signerPermitNo' => ['nullable', 'string', 'max:255'],
            'signerRegistrationNo' => ['nullable', 'string', 'max:255'],
            'signerPhone' => ['nullable', 'string', 'max:100'],
            'signerEmail' => ['nullable', 'email', 'max:255'],
            'signerEffectiveFrom' => ['required', 'date'],
            'signerEffectiveUntil' => ['nullable', 'date', 'after_or_equal:signerEffectiveFrom'],
        ], [
            'signerKey.regex' => 'Kode penandatangan hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
        ]);
        $this->assertBranchAccess((int) $validated['signerBranchId']);

        DB::transaction(function () use ($validated): void {
            if ($this->signerVersionId !== null) {
                $version = $this->signerQuery()->lockForUpdate()->findOrFail($this->signerVersionId);
                $this->assertDraft($version);
                $validated['signerBranchId'] = $version->branch_id;
                $validated['signerKey'] = $version->signer_key;
            } else {
                Branch::query()->lockForUpdate()->findOrFail($validated['signerBranchId']);
                $versionNo = (int) DocumentSignerVersion::query()
                    ->where('branch_id', $validated['signerBranchId'])
                    ->where('signer_key', $validated['signerKey'])
                    ->max('version_no') + 1;
                $version = new DocumentSignerVersion([
                    'version_no' => $versionNo,
                    'status' => OfferDocumentMasterReviewStatus::Draft->value,
                    'created_by' => auth()->id(),
                ]);
            }

            $version->fill([
                'branch_id' => $validated['signerBranchId'],
                'signer_key' => $validated['signerKey'],
                'full_name' => $validated['signerFullName'],
                'title_suffix' => $validated['signerTitleSuffix'] ?: null,
                'position' => $validated['signerPosition'],
                'permit_no' => $validated['signerPermitNo'] ?: null,
                'registration_no' => $validated['signerRegistrationNo'] ?: null,
                'phone' => $validated['signerPhone'] ?: null,
                'email' => $validated['signerEmail'] ?: null,
                'effective_from' => $validated['signerEffectiveFrom'],
                'effective_until' => $validated['signerEffectiveUntil'] ?: null,
            ])->save();
        });

        $this->showSignerEditor = false;
        session()->flash('message', 'Draft penandatangan berhasil disimpan tanpa gambar tanda tangan atau stempel.');
    }

    public function submitMaster(string $type, int $id, OfferDocumentMasterApprovalService $approval): void
    {
        $this->authorize('offers.document-masters.manage');
        $this->runWorkflowAction(
            fn () => $approval->submit($this->resolveMaster($type, $id), auth()->user()),
            'Master berhasil diajukan. Konten sekarang terkunci sampai ditinjau Supervisor.',
        );
    }

    public function approveMaster(string $type, int $id, OfferDocumentMasterApprovalService $approval): void
    {
        $this->authorize('offers.document-masters.approve');
        $this->runWorkflowAction(
            fn () => $approval->approve($this->resolveMaster($type, $id), auth()->user()),
            'Master berhasil disetujui dan tersedia sesuai masa berlakunya.',
        );
    }

    public function openReviewDialog(string $action, string $type, int $id): void
    {
        $this->authorize('offers.document-masters.approve');

        if (! in_array($action, ['reject', 'retire'], true)) {
            abort(404);
        }

        $this->resolveMaster($type, $id);
        $this->resetErrorBag('reviewNote');
        $this->reviewAction = $action;
        $this->reviewMasterType = $type;
        $this->reviewMasterId = $id;
        $this->reviewNote = '';
        $this->showReviewDialog = true;
    }

    public function confirmReviewAction(OfferDocumentMasterApprovalService $approval): void
    {
        $this->authorize('offers.document-masters.approve');
        $this->validate(['reviewNote' => ['required', 'string', 'max:1000']]);
        $master = $this->resolveMaster($this->reviewMasterType, (int) $this->reviewMasterId);

        $message = $this->reviewAction === 'reject'
            ? 'Master ditolak. Buat versi baru untuk menindaklanjuti catatan reviewer.'
            : 'Master berhasil di-retire dan tidak lagi tersedia untuk dokumen baru.';

        $succeeded = $this->runWorkflowAction(function () use ($approval, $master): void {
            if ($this->reviewAction === 'reject') {
                $approval->reject($master, auth()->user(), $this->reviewNote);

                return;
            }

            $approval->retire($master, auth()->user(), $this->reviewNote);
        }, $message);

        if ($succeeded) {
            $this->showReviewDialog = false;
        }
    }

    public function render()
    {
        $this->authorize('offers.document-masters.view');

        return view('livewire.master.offer-document-masters', [
            'templates' => OfferTemplateVersion::query()
                ->with(['template', 'creator', 'submitter', 'reviewer'])
                ->orderByDesc('id')
                ->get(),
            'issuers' => $this->issuerQuery()
                ->with(['branch', 'creator', 'submitter', 'reviewer'])
                ->orderByDesc('id')
                ->get(),
            'signers' => $this->signerQuery()
                ->with(['branch', 'creator', 'submitter', 'reviewer'])
                ->orderByDesc('id')
                ->get(),
            'branches' => $this->branchQuery()->where('active', true)->orderBy('name')->get(),
            'categoryOptions' => OfferTemplateCategory::cases(),
            'blockTypeOptions' => OfferTemplateBlockType::cases(),
            'conditionOptions' => OfferTemplateCondition::cases(),
            'dynamicSourceOptions' => OfferTemplateDynamicSource::cases(),
        ])->layout('layouts.app');
    }

    private function templateSchemaFromEditor(): array
    {
        $defaults = $this->templateDefaults;

        foreach (['report_copies', 'completion_days', 'ppn_rate_bps', 'pph_rate_bps'] as $field) {
            $defaults[$field] = (int) ($defaults[$field] ?? 0);
        }

        $defaults['cost_inclusions'] = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/u', $this->templateCostInclusions) ?: [],
        ), static fn (string $line): bool => $line !== ''));
        $defaults['special_assumptions'] = trim((string) ($defaults['special_assumptions'] ?? '')) ?: null;
        $defaults['payment_terms'] = array_values(array_map(static fn (array $term): array => [
            'percentage_bps' => (int) ($term['percentage_bps'] ?? 0),
            'trigger_text' => trim((string) ($term['trigger_text'] ?? '')),
            'due_days' => ($term['due_days'] ?? '') === '' || ($term['due_days'] ?? null) === null
                ? null
                : (int) $term['due_days'],
        ], (array) ($defaults['payment_terms'] ?? [])));
        $defaults['requirements'] = array_values(array_map(static fn (array $requirement): array => [
            'requirement_code' => trim((string) ($requirement['requirement_code'] ?? '')) ?: null,
            'description' => trim((string) ($requirement['description'] ?? '')),
            'emphasis_style' => (string) ($requirement['emphasis_style'] ?? 'normal'),
        ], (array) ($defaults['requirements'] ?? [])));

        $clauses = [];

        foreach (array_keys((array) config('offer-documents.clause_titles', [])) as $key) {
            $blocks = [];

            foreach ((array) data_get($this->templateClauses, "{$key}.blocks", []) as $editorBlock) {
                $type = (string) ($editorBlock['type'] ?? 'text');
                $block = ['type' => $type];
                $when = trim((string) ($editorBlock['when'] ?? ''));

                if ($when !== '') {
                    $block['when'] = $when;
                }

                if ($type === OfferTemplateBlockType::Text->value) {
                    $block['text'] = trim((string) ($editorBlock['content'] ?? ''));
                } elseif ($type === OfferTemplateBlockType::Bullets->value) {
                    $block['items'] = array_values(array_filter(array_map(
                        static fn (string $line): string => trim($line),
                        preg_split('/\R/u', (string) ($editorBlock['content'] ?? '')) ?: [],
                    ), static fn (string $line): bool => $line !== ''));
                } elseif ($type === OfferTemplateBlockType::Dynamic->value) {
                    $block['source'] = (string) ($editorBlock['source'] ?? OfferTemplateDynamicSource::Client->value);
                }

                $blocks[] = $block;
            }

            $clauses[$key] = ['blocks' => $blocks];
        }

        $constraints = $this->templateConstraints;
        $constraints['required_engagement_fields'] = array_values(array_intersect(
            OfferTemplateSchemaV2::REQUIRED_ENGAGEMENT_FIELDS,
            (array) ($constraints['required_engagement_fields'] ?? []),
        ));
        $constraints['purpose_must_equal'] = (string) ($defaults['purpose'] ?? '');
        $constraints['valuation_basis_must_equal'] = (string) ($defaults['valuation_basis'] ?? '');

        foreach (['required_asset_document', 'require_fee_per_asset', 'requires_liquidation_value', 'requires_exposure_table'] as $field) {
            $constraints[$field] = (bool) ($constraints[$field] ?? false);
        }

        return [
            'document' => [
                'opening' => trim($this->templateOpening),
                'closing' => trim($this->templateClosing),
            ],
            'defaults' => $defaults,
            'clauses' => $clauses,
            'constraints' => $constraints,
        ];
    }

    private function loadTemplateSchema(array $schema): void
    {
        $fallback = $this->defaultTemplateSchema($this->templateCategory);
        $document = is_array($schema['document'] ?? null) ? $schema['document'] : $fallback['document'];
        $this->templateOpening = (string) ($document['opening'] ?? '');
        $this->templateClosing = (string) ($document['closing'] ?? '');
        $this->templateDefaults = array_replace($fallback['defaults'], is_array($schema['defaults'] ?? null) ? $schema['defaults'] : []);
        $this->templateCostInclusions = implode("\n", (array) ($this->templateDefaults['cost_inclusions'] ?? []));
        $this->templateConstraints = array_replace($fallback['constraints'], is_array($schema['constraints'] ?? null) ? $schema['constraints'] : []);
        $this->templateClauses = [];

        foreach ((array) config('offer-documents.clause_titles', []) as $key => $title) {
            $rawBlocks = data_get($schema, "clauses.{$key}.blocks");

            if (! is_array($rawBlocks) || $rawBlocks === []) {
                $rawBlocks = data_get($fallback, "clauses.{$key}.blocks", []);
            }

            $blocks = [];

            foreach ($rawBlocks as $block) {
                if (! is_array($block)) {
                    continue;
                }

                $type = (string) ($block['type'] ?? OfferTemplateBlockType::Text->value);
                $blocks[] = [
                    'type' => $type,
                    'when' => (string) ($block['when'] ?? ''),
                    'content' => $type === OfferTemplateBlockType::Bullets->value
                        ? implode("\n", (array) ($block['items'] ?? []))
                        : (string) ($block['text'] ?? ''),
                    'source' => (string) ($block['source'] ?? OfferTemplateDynamicSource::Client->value),
                ];
            }

            $this->templateClauses[$key] = [
                'title' => $title,
                'blocks' => $blocks === [] ? [$this->emptyEditorBlock()] : $blocks,
            ];
        }
    }

    private function defaultTemplateSchema(string $category): array
    {
        $isAuction = $category === OfferTemplateCategory::PropertyAuction->value;
        $purpose = $this->categoryPurpose($category);
        $basis = match ($category) {
            OfferTemplateCategory::PropertyAuction->value => 'Nilai Pasar dan Nilai Likuidasi',
            OfferTemplateCategory::PropertyRental->value => 'Nilai Sewa Pasar',
            default => 'Nilai Pasar',
        };
        $dynamicByClause = [
            'appraiser_status' => OfferTemplateDynamicSource::AppraiserStatus->value,
            'client' => OfferTemplateDynamicSource::Client->value,
            'report_user' => OfferTemplateDynamicSource::ReportUser->value,
            'ownership_form' => OfferTemplateDynamicSource::OwnershipForm->value,
            'currency' => OfferTemplateDynamicSource::Currency->value,
            'purpose' => OfferTemplateDynamicSource::Purpose->value,
            'basis_of_value' => OfferTemplateDynamicSource::ValuationBasis->value,
            'valuation_date' => OfferTemplateDynamicSource::ValuationDate->value,
            'investigation_depth' => OfferTemplateDynamicSource::InvestigationLevel->value,
            'assumptions' => OfferTemplateDynamicSource::SpecialAssumptions->value,
            'valuation_report' => OfferTemplateDynamicSource::ReportSpecification->value,
            'completion_time' => OfferTemplateDynamicSource::CompletionTime->value,
        ];
        $clauses = [];

        foreach ((array) config('offer-documents.clause_titles', []) as $key => $title) {
            $block = isset($dynamicByClause[$key])
                ? ['type' => OfferTemplateBlockType::Dynamic->value, 'source' => $dynamicByClause[$key]]
                : ['type' => OfferTemplateBlockType::Text->value, 'text' => "Ketentuan {$title} diatur sesuai ruang lingkup penugasan yang disepakati."];

            if ($key === 'valuation_object') {
                $block = ['type' => OfferTemplateBlockType::AssetList->value];
            } elseif ($key === 'professional_fee') {
                $block = ['type' => $isAuction ? OfferTemplateBlockType::FeeTable->value : OfferTemplateBlockType::FeeSummary->value];
            } elseif ($key === 'initial_data_request') {
                $block = ['type' => OfferTemplateBlockType::Requirements->value];
            } elseif ($key === 'other_terms' && $isAuction) {
                $block = ['type' => OfferTemplateBlockType::ExposureTable->value];
            }

            $blocks = [$block];

            if ($key === 'professional_fee') {
                $blocks[] = ['type' => OfferTemplateBlockType::PaymentTerms->value];
            }

            $clauses[$key] = ['blocks' => $blocks];
        }

        return [
            'document' => [
                'opening' => 'Sehubungan dengan permintaan jasa penilaian, bersama ini kami menyampaikan penawaran berdasarkan ruang lingkup berikut.',
                'closing' => 'Demikian penawaran ini disampaikan. Kami menantikan konfirmasi untuk melanjutkan penugasan.',
            ],
            'defaults' => [
                'subject' => 'Penawaran Jasa Penilaian',
                'ownership_form' => 'Sertifikat dan/atau dokumen kepemilikan yang sah',
                'currency' => 'IDR',
                'purpose' => $purpose,
                'valuation_basis' => $basis,
                'investigation_level' => 'full',
                'report_format' => 'complete',
                'report_language' => 'id',
                'report_copies' => 2,
                'completion_days' => 14,
                'completion_day_type' => 'business',
                'tax_inclusion' => OfferTaxInclusion::Excluded->value,
                'ppn_rate_bps' => 1100,
                'pph_rate_bps' => 200,
                'fee_presentation' => $isAuction ? OfferFeePresentation::PerAsset->value : OfferFeePresentation::LumpSum->value,
                'cost_inclusions' => ['Biaya transportasi dan operasional dalam ruang lingkup yang disepakati.'],
                'special_assumptions' => null,
                'payment_terms' => [[
                    'percentage_bps' => 10_000,
                    'trigger_text' => 'Setelah laporan penilaian diserahkan',
                    'due_days' => 14,
                ]],
                'requirements' => [[
                    'requirement_code' => 'LEGAL',
                    'description' => 'Dokumen legal dan kepemilikan objek penilaian.',
                    'emphasis_style' => 'normal',
                ]],
            ],
            'clauses' => $clauses,
            'constraints' => [
                'required_engagement_fields' => OfferTemplateSchemaV2::REQUIRED_ENGAGEMENT_FIELDS,
                'purpose_must_equal' => $purpose,
                'valuation_basis_must_equal' => $basis,
                'required_asset_document' => true,
                'require_fee_per_asset' => $isAuction,
                'requires_liquidation_value' => $isAuction,
                'requires_exposure_table' => $isAuction,
            ],
        ];
    }

    private function assertSafeTemplateSchema(array $schema): void
    {
        $errors = [];
        $this->collectUnsafeTemplateText($schema, 'schema', $errors);
        $allowedTypes = array_column(OfferTemplateBlockType::cases(), 'value');
        $allowedConditions = array_column(OfferTemplateCondition::cases(), 'value');
        $allowedSources = array_column(OfferTemplateDynamicSource::cases(), 'value');

        foreach ((array) ($schema['clauses'] ?? []) as $key => $clause) {
            $blocks = (array) ($clause['blocks'] ?? []);

            if ($blocks === []) {
                $errors["templateClauses.{$key}"] = 'Setiap klausul wajib memiliki sedikitnya satu blok.';
            }

            foreach ($blocks as $index => $block) {
                $path = "templateClauses.{$key}.blocks.{$index}";

                if (! in_array($block['type'] ?? null, $allowedTypes, true)) {
                    $errors["{$path}.type"] = 'Tipe blok tidak dikenal.';
                }

                if (isset($block['when']) && ! in_array($block['when'], $allowedConditions, true)) {
                    $errors["{$path}.when"] = 'Kondisi blok tidak dikenal.';
                }

                if (($block['type'] ?? null) === OfferTemplateBlockType::Dynamic->value
                    && ! in_array($block['source'] ?? null, $allowedSources, true)) {
                    $errors["{$path}.source"] = 'Sumber data dinamis tidak dikenal.';
                }

                foreach (array_filter([
                    ($block['type'] ?? null) === OfferTemplateBlockType::Text->value ? ($block['text'] ?? null) : null,
                    ...(($block['type'] ?? null) === OfferTemplateBlockType::Bullets->value ? (array) ($block['items'] ?? []) : []),
                ], static fn ($value): bool => is_string($value)) as $text) {
                    if (preg_match('/<\?(?:php|=)?|\?>|{!!|!!}|@(?:php|endphp|inject|include|extends|section|yield|component|livewire)\b/i', $text) === 1
                        || preg_match('/<\s*\/?\s*[a-z!][^>]*>/i', $text) === 1) {
                        $errors["{$path}.content"] = 'Konten tidak boleh memuat HTML, Blade, atau PHP.';
                    }

                    preg_match_all('/{{\s*([^{}]+?)\s*}}/u', $text, $matches);

                    foreach ($matches[1] ?? [] as $token) {
                        if (! in_array($token, OfferTemplateSchemaV2::TOKENS, true)) {
                            $errors["{$path}.content"] = "Token {$token} tidak dikenal.";
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function collectUnsafeTemplateText(mixed $value, string $path, array &$errors): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->collectUnsafeTemplateText($item, "{$path}.{$key}", $errors);
            }

            return;
        }

        if (! is_string($value)) {
            return;
        }

        if (preg_match('/<\?(?:php|=)?|\?>|{!!|!!}|@(?:php|endphp|inject|include|extends|section|yield|component|livewire)\b/i', $value) === 1
            || preg_match('/<\s*\/?\s*[a-z!][^>]*>/i', $value) === 1) {
            $errors['templateSchema'] = "{$path} tidak boleh memuat HTML, Blade, atau PHP.";
        }

        preg_match_all('/{{\s*([^{}]+?)\s*}}/u', $value, $matches);

        foreach ($matches[1] ?? [] as $token) {
            if (! in_array($token, OfferTemplateSchemaV2::TOKENS, true)) {
                $errors['templateSchema'] = "{$path} memakai token yang tidak dikenal: {$token}.";
            }
        }

        $withoutTokens = preg_replace('/{{\s*[^{}]+?\s*}}/u', '', $value);

        if (is_string($withoutTokens) && (str_contains($withoutTokens, '{{') || str_contains($withoutTokens, '}}'))) {
            $errors['templateSchema'] = "{$path} memiliki sintaks token yang tidak valid.";
        }
    }

    private function storeLetterhead(): array
    {
        $temporaryPath = $this->letterheadUpload->getRealPath();
        $image = @getimagesize($temporaryPath);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);

        if (! is_array($image) || ! in_array($mime, ['image/png', 'image/jpeg'], true) || ($image['mime'] ?? null) !== $mime) {
            throw ValidationException::withMessages(['letterheadUpload' => 'File letterhead bukan PNG/JPEG yang valid.']);
        }

        $hash = hash_file('sha256', $temporaryPath);
        $size = filesize($temporaryPath);

        if (! is_string($hash) || ! is_int($size) || $size < 1 || $size > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['letterheadUpload' => 'File letterhead tidak dapat diverifikasi.']);
        }

        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $relativePath = "letterheads/{$hash}.{$extension}";
        $stored = $this->letterheadUpload->storeAs(
            'offer-document-assets/letterheads',
            "{$hash}.{$extension}",
            'local',
        );

        if (! is_string($stored) || ! Storage::disk('local')->exists($stored)) {
            throw ValidationException::withMessages(['letterheadUpload' => 'Letterhead gagal disimpan ke penyimpanan privat.']);
        }

        return [
            'letterhead_path' => $relativePath,
            'letterhead_sha256' => $hash,
            'letterhead_mime' => $mime,
            'letterhead_width_px' => (int) $image[0],
            'letterhead_height_px' => (int) $image[1],
            'letterhead_size_bytes' => $size,
        ];
    }

    private function resolveMaster(string $type, int $id): Model
    {
        return match ($type) {
            'template' => OfferTemplateVersion::query()->findOrFail($id),
            'issuer' => $this->issuerQuery()->findOrFail($id),
            'signer' => $this->signerQuery()->findOrFail($id),
            default => abort(404),
        };
    }

    /** @return Builder<IssuerProfileVersion> */
    private function issuerQuery(): Builder
    {
        return $this->scopeBranchBoundQuery(IssuerProfileVersion::query());
    }

    /** @return Builder<DocumentSignerVersion> */
    private function signerQuery(): Builder
    {
        return $this->scopeBranchBoundQuery(DocumentSignerVersion::query());
    }

    /** @return Builder<Branch> */
    private function branchQuery(): Builder
    {
        $query = Branch::query();

        if (! $this->hasCrossBranchAccess()) {
            $query->whereKey(auth()->user()?->branch_id ?? 0);
        }

        return $query;
    }

    /** @template TModel of Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopeBranchBoundQuery(Builder $query): Builder
    {
        if (! $this->hasCrossBranchAccess()) {
            $query->where('branch_id', auth()->user()?->branch_id ?? 0);
        }

        return $query;
    }

    private function assertBranchAccess(int $branchId): void
    {
        if ($this->hasCrossBranchAccess()) {
            return;
        }

        abort_unless((int) auth()->user()?->branch_id === $branchId, 404);
    }

    private function hasCrossBranchAccess(): bool
    {
        return auth()->user()?->can('offers.cross-branch') === true;
    }

    private function runWorkflowAction(callable $action, string $successMessage): bool
    {
        $this->resetErrorBag('workflow');

        try {
            $action();
        } catch (DomainException $exception) {
            $this->addError('workflow', $exception->getMessage());

            return false;
        }

        session()->flash('message', $successMessage);

        return true;
    }

    private function assertDraft(Model $model): void
    {
        if ($model->getAttribute('status') !== OfferDocumentMasterReviewStatus::Draft->value) {
            throw new DomainException('Hanya master draft yang dapat diedit. Buat versi baru untuk perubahan.');
        }
    }

    private function assertClauseKey(string $key): void
    {
        if (! array_key_exists($key, (array) config('offer-documents.clause_titles', []))) {
            abort(404);
        }
    }

    private function closeEditors(): void
    {
        $this->showTemplateEditor = false;
        $this->showIssuerEditor = false;
        $this->showSignerEditor = false;
        $this->showReviewDialog = false;
        $this->showPreview = false;
    }

    private function resetTemplateEditor(): void
    {
        $this->reset([
            'templateId', 'templateVersionId', 'templateCode', 'templateName', 'templatePurpose',
            'templateEffectiveFrom', 'templateEffectiveUntil', 'templateOpening', 'templateClosing',
            'templateDefaults', 'templateClauses', 'templateConstraints', 'templateCostInclusions',
        ]);
        $this->templateCategory = OfferTemplateCategory::PropertyCollateral->value;
        $this->resetErrorBag();
    }

    private function resetIssuerEditor(): void
    {
        $this->reset([
            'issuerVersionId', 'issuerBranchId', 'issuerLegalName', 'issuerPermitNo', 'issuerOfficeLabel',
            'issuerAddress', 'issuerCity', 'issuerPhone', 'issuerEmail', 'issuerEffectiveFrom',
            'issuerEffectiveUntil', 'letterheadUpload', 'issuerExistingLetterhead',
        ]);
        $this->resetErrorBag();
    }

    private function resetSignerEditor(): void
    {
        $this->reset([
            'signerVersionId', 'signerBranchId', 'signerKey', 'signerFullName', 'signerTitleSuffix',
            'signerPosition', 'signerPermitNo', 'signerRegistrationNo', 'signerPhone', 'signerEmail',
            'signerEffectiveFrom', 'signerEffectiveUntil',
        ]);
        $this->resetErrorBag();
    }

    private function emptyEditorBlock(): array
    {
        return [
            'type' => OfferTemplateBlockType::Text->value,
            'when' => '',
            'content' => '',
            'source' => OfferTemplateDynamicSource::Client->value,
        ];
    }

    private function categoryPurpose(string $category): string
    {
        return match ($category) {
            OfferTemplateCategory::PropertyAuction->value => 'Pelaksanaan lelang',
            OfferTemplateCategory::PropertyRental->value => 'Penentuan nilai sewa pasar',
            default => 'Penjaminan utang',
        };
    }

    private function replaceFixtureTokens(string $text): string
    {
        $fixtures = [
            'document.number' => '000/PNW/08/2026',
            'document.place' => 'Jakarta',
            'document.date' => '19 Agustus 2026',
            'document.subject' => 'Penawaran Jasa Penilaian',
            'recipient.name' => 'PT Contoh Nusantara',
            'recipient.attention' => 'Tim Kredit',
            'recipient.address' => 'Alamat penerima anonim',
            'issuer.name' => 'KJPP Contoh',
            'issuer.address' => 'Alamat kantor penerbit',
            'issuer.phone' => '(021) 0000000',
            'issuer.email' => 'kantor@example.test',
            'issuer.permit_no' => 'Izin 000/2026',
            'request.reference_no' => 'REF-ANONIM',
            'request.reference_date' => '18 Agustus 2026',
            'engagement.ownership_form' => 'Sertifikat Hak Milik',
            'engagement.currency' => 'Rupiah (IDR)',
            'engagement.purpose' => 'Tujuan sesuai template',
            'engagement.valuation_basis' => 'Dasar nilai sesuai template',
            'engagement.valuation_date' => 'Tanggal inspeksi',
            'engagement.investigation_level' => 'Investigasi penuh',
            'engagement.report_format' => 'Laporan lengkap',
            'engagement.report_language' => 'Bahasa Indonesia',
            'engagement.report_copies' => '2',
            'engagement.completion_days' => '14',
            'engagement.completion_day_type' => 'hari kerja',
            'engagement.special_assumptions' => 'Tidak ada asumsi khusus',
            'commercial.quoted_amount' => 'Rp10.000.000',
            'commercial.document_payable_total' => 'Rp11.100.000',
            'commercial.amount_in_words' => 'Sebelas juta seratus ribu rupiah',
            'signer.full_name' => 'Penilai Contoh',
            'signer.position' => 'Pimpinan Rekan',
            'signer.permit_no' => 'Izin 000/2026',
            'signer.registration_no' => 'MAPPI 00000',
        ];

        return preg_replace_callback('/{{\s*([^{}]+?)\s*}}/u', static function (array $matches) use ($fixtures): string {
            return $fixtures[$matches[1]] ?? "[{$matches[1]}]";
        }, $text) ?? $text;
    }

    private function fixtureBlock(array $block): array
    {
        $type = (string) ($block['type'] ?? 'text');
        $condition = isset($block['when']) ? 'Kondisi: '.str_replace('_', ' ', (string) $block['when']) : null;

        if ($type === OfferTemplateBlockType::Text->value) {
            return ['type' => 'Teks', 'condition' => $condition, 'lines' => [$this->replaceFixtureTokens((string) ($block['text'] ?? ''))]];
        }

        if ($type === OfferTemplateBlockType::Bullets->value) {
            return ['type' => 'Daftar', 'condition' => $condition, 'lines' => array_map(fn ($item) => $this->replaceFixtureTokens((string) $item), (array) ($block['items'] ?? []))];
        }

        if ($type === OfferTemplateBlockType::Dynamic->value) {
            return ['type' => 'Data dinamis', 'condition' => $condition, 'lines' => ['Fixture: '.str_replace('_', ' ', (string) ($block['source'] ?? ''))]];
        }

        $labels = [
            'asset_list' => 'Daftar dua aset anonim',
            'fee_summary' => 'Ringkasan fee lump sum anonim',
            'fee_table' => 'Tabel fee per aset anonim',
            'payment_terms' => 'Termin pembayaran anonim',
            'requirements' => 'Daftar persyaratan anonim',
            'exposure_table' => 'Tabel exposure dan diskon anonim',
        ];

        return ['type' => str_replace('_', ' ', $type), 'condition' => $condition, 'lines' => [$labels[$type] ?? 'Blok terstruktur']];
    }
}
