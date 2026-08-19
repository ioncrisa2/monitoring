<?php

namespace Tests\Feature;

use App\Livewire\Master\OfferDocumentMasters;
use App\Models\Branch;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\OfferTemplate;
use App\Models\OfferTemplateVersion;
use App\Models\User;
use App\Services\Offers\OfferDocumentMasterApprovalService;
use App\Services\Offers\OfferDocumentMasterIntegrityService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OfferDocumentMastersTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $sysadmin;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->branch = Branch::create([
            'code' => 'PST',
            'number_code' => 0,
            'name' => 'Kantor Pusat',
            'active' => true,
        ]);
        $this->sysadmin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'sysadmin',
        ]);
        $this->sysadmin->syncRoles(['sysadmin']);
        $this->supervisor = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'supervisor',
        ]);
        $this->supervisor->syncRoles(['supervisor']);
    }

    public function test_route_and_component_require_the_dedicated_view_permission(): void
    {
        $this->get(route('master.offer-documents'))->assertRedirect(route('login'));

        $admin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'admin',
        ]);

        $this->actingAs($admin)->get(route('master.offer-documents'))->assertForbidden();
        Livewire::actingAs($admin)->test(OfferDocumentMasters::class)->assertStatus(403);

        $this->actingAs($this->supervisor)
            ->get(route('master.offer-documents'))
            ->assertOk()
            ->assertSee('Master Dokumen Penawaran')
            ->assertSee('Profil penerbit')
            ->assertSee('Penandatangan')
            ->assertDontSee('Buat template');

        $this->actingAs($this->sysadmin)
            ->get(route('master.offer-documents'))
            ->assertOk()
            ->assertSee('Buat template');
    }

    public function test_sysadmin_can_create_a_safe_v2_template_with_exactly_twenty_five_clauses(): void
    {
        Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->call('createTemplate')
            ->set('templateCode', 'property-custom')
            ->set('templateName', 'Properti Kustom')
            ->set('templatePurpose', 'Penugasan properti khusus')
            ->call('saveTemplate')
            ->assertHasNoErrors()
            ->assertSet('showTemplateEditor', false);

        $template = OfferTemplate::query()->where('code', 'property-custom')->firstOrFail();
        $version = $template->versions()->firstOrFail();

        $this->assertSame('draft', $version->status);
        $this->assertSame(2, $version->schema_version);
        $this->assertSame('offer-a4-v2', $version->layout_version);
        $this->assertSame('all_pages', $version->header_mode);
        $this->assertSame(['document', 'defaults', 'clauses', 'constraints'], array_keys($version->clause_schema));
        $this->assertCount(25, $version->clause_schema['clauses']);

        foreach ($version->clause_schema['clauses'] as $clause) {
            $this->assertSame(['blocks'], array_keys($clause));
            $this->assertNotEmpty($clause['blocks']);
        }
    }

    public function test_template_editor_rejects_html_and_unknown_tokens_before_persistence(): void
    {
        Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->call('createTemplate')
            ->set('templateCode', 'unsafe-template')
            ->set('templateName', 'Unsafe')
            ->set('templatePurpose', 'Uji keamanan')
            ->set('templateClauses.information_sources.blocks.0.content', '<script>alert(1)</script> {{system.shell}}')
            ->call('saveTemplate')
            ->assertHasErrors('templateClauses.information_sources.blocks.0.content');

        $this->assertDatabaseMissing('offer_templates', ['code' => 'unsafe-template']);
    }

    public function test_template_workflow_uses_separation_of_duties_and_copy_creates_a_new_draft(): void
    {
        $component = Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->call('createTemplate')
            ->set('templateCode', 'reviewed-template')
            ->set('templateName', 'Reviewed Template')
            ->set('templatePurpose', 'Uji workflow')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $version = OfferTemplateVersion::query()->firstOrFail();

        $this->assertSame([], app(OfferDocumentMasterIntegrityService::class)->templateSchemaErrorsFor($version));

        $component
            ->call('submitMaster', 'template', $version->id)
            ->assertHasNoErrors();

        $this->assertSame('submitted', $version->fresh()->status);

        Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->call('approveMaster', 'template', $version->id)
            ->assertForbidden();

        Livewire::actingAs($this->supervisor)
            ->test(OfferDocumentMasters::class)
            ->call('approveMaster', 'template', $version->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $version->fresh()->status);

        Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->call('copyTemplate', $version->id)
            ->assertHasNoErrors()
            ->assertSet('showTemplateEditor', true);

        $copy = OfferTemplateVersion::query()->where('version_no', 2)->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertSame($version->fresh()->clause_schema, $copy->clause_schema);
        $this->assertNull($copy->submitted_at);
        $this->assertNull($copy->approved_at);
    }

    public function test_official_letterhead_is_stored_privately_with_verified_metadata(): void
    {
        Storage::fake('local');
        config()->set('offer-documents.renderer.approved_asset_path', Storage::disk('local')->path('offer-document-assets'));

        Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->call('setTab', 'issuers')
            ->call('createIssuer')
            ->set('issuerLegalName', 'KJPP Contoh dan Rekan')
            ->set('issuerPermitNo', '2.24.0001')
            ->set('issuerOfficeLabel', 'Kantor Pusat')
            ->set('issuerAddress', 'Jl. Contoh No. 1')
            ->set('issuerCity', 'Jakarta')
            ->set('issuerPhone', '021000000')
            ->set('issuerEmail', 'office@example.test')
            ->set('letterheadUpload', UploadedFile::fake()->image('letterhead.png', 1200, 240))
            ->call('saveIssuer')
            ->assertHasNoErrors();

        $issuer = IssuerProfileVersion::query()->firstOrFail();
        $this->assertSame('image/png', $issuer->letterhead_mime);
        $this->assertSame(1200, $issuer->letterhead_width_px);
        $this->assertSame(240, $issuer->letterhead_height_px);
        $this->assertMatchesRegularExpression('/\Aletterheads\/[a-f0-9]{64}\.png\z/', $issuer->letterhead_path);
        Storage::disk('local')->assertExists('offer-document-assets/'.$issuer->letterhead_path);
        $this->assertSame(
            $issuer->letterhead_sha256,
            hash_file('sha256', Storage::disk('local')->path('offer-document-assets/'.$issuer->letterhead_path)),
        );

        Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->call('submitMaster', 'issuer', $issuer->id)
            ->assertHasNoErrors();

        Livewire::actingAs($this->supervisor)
            ->test(OfferDocumentMasters::class)
            ->call('approveMaster', 'issuer', $issuer->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $issuer->fresh()->status);
    }

    public function test_signer_master_is_text_only_and_can_complete_review_workflow(): void
    {
        $component = Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->call('setTab', 'signers')
            ->call('createSigner')
            ->assertSee('tidak ada unggahan tanda tangan atau stempel')
            ->assertDontSeeHtml('name="signature"')
            ->assertDontSeeHtml('name="stamp"')
            ->set('signerKey', 'pimpinan-rekan')
            ->set('signerFullName', 'Budi Penilai')
            ->set('signerTitleSuffix', 'M.Ec.Dev., MAPPI (Cert.)')
            ->set('signerPosition', 'Pimpinan Rekan')
            ->set('signerPermitNo', 'P-1.00.00001')
            ->set('signerRegistrationNo', 'MAPPI 00-S-00001')
            ->set('signerEmail', 'signer@example.test')
            ->call('saveSigner')
            ->assertHasNoErrors();

        $signer = DocumentSignerVersion::query()->firstOrFail();
        $this->assertNull($signer->getRawOriginal('signature_path'));
        $this->assertNull($signer->getRawOriginal('stamp_path'));

        $component
            ->call('submitMaster', 'signer', $signer->id)
            ->assertHasNoErrors();

        $review = Livewire::actingAs($this->supervisor)
            ->test(OfferDocumentMasters::class)
            ->call('approveMaster', 'signer', $signer->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $signer->fresh()->status);

        $review
            ->call('openReviewDialog', 'retire', 'signer', $signer->id)
            ->assertSet('showReviewDialog', true)
            ->set('reviewNote', 'Masa registrasi penandatangan berakhir.')
            ->call('confirmReviewAction')
            ->assertHasNoErrors()
            ->assertSet('showReviewDialog', false);

        $this->assertSame('retired', $signer->fresh()->status);
    }

    public function test_branch_bound_masters_are_hidden_and_forged_ids_cannot_bypass_scope(): void
    {
        [$foreignBranch, $foreignCreator, $foreignIssuer, $foreignSigner] = $this->foreignBranchMasters();
        app(OfferDocumentMasterApprovalService::class)->submit($foreignSigner, $foreignCreator);

        Livewire::actingAs($this->supervisor)
            ->test(OfferDocumentMasters::class)
            ->assertViewHas('issuers', fn ($issuers): bool => ! $issuers->contains('id', $foreignIssuer->id))
            ->assertViewHas('signers', fn ($signers): bool => ! $signers->contains('id', $foreignSigner->id))
            ->assertViewHas('branches', fn ($branches): bool => ! $branches->contains('id', $foreignBranch->id));

        $this->assertScopedMasterCallIsNotFound(
            $this->supervisor,
            'approveMaster',
            'signer',
            $foreignSigner->id,
        );

        foreach (['reject', 'retire'] as $action) {
            $this->assertScopedMasterCallIsNotFound(
                $this->supervisor,
                'openReviewDialog',
                $action,
                'signer',
                $foreignSigner->id,
            );
        }

        // Grant manage explicitly to prove the resource scope remains enforced
        // independently from the normal Supervisor permission set.
        $this->supervisor->givePermissionTo('offers.document-masters.manage');

        foreach ([
            ['editIssuer', $foreignIssuer->id],
            ['copyIssuer', $foreignIssuer->id],
            ['editSigner', $foreignSigner->id],
            ['copySigner', $foreignSigner->id],
        ] as [$method, $id]) {
            $this->assertScopedMasterCallIsNotFound($this->supervisor, $method, $id);
        }

        $this->assertSame('submitted', $foreignSigner->fresh()->status);
        $this->assertSame(1, IssuerProfileVersion::query()->where('branch_id', $foreignBranch->id)->count());
        $this->assertSame(1, DocumentSignerVersion::query()->where('branch_id', $foreignBranch->id)->count());
    }

    public function test_cross_branch_permission_allows_catalog_management_or_review_according_to_role(): void
    {
        [$foreignBranch, $foreignCreator, $foreignIssuer, $foreignSigner] = $this->foreignBranchMasters();
        app(OfferDocumentMasterApprovalService::class)->submit($foreignSigner, $foreignCreator);

        $this->assertTrue($this->sysadmin->can('offers.document-masters.view'));
        $this->assertTrue($this->sysadmin->can('offers.document-masters.manage'));
        $this->assertTrue($this->sysadmin->can('offers.cross-branch'));
        $this->assertFalse($this->sysadmin->can('offers.document-masters.approve'));
        $this->assertTrue($this->supervisor->can('offers.document-masters.approve'));
        $this->assertFalse($this->supervisor->can('offers.document-masters.manage'));

        Livewire::actingAs($this->sysadmin)
            ->test(OfferDocumentMasters::class)
            ->assertViewHas('issuers', fn ($issuers): bool => $issuers->contains('id', $foreignIssuer->id))
            ->assertViewHas('signers', fn ($signers): bool => $signers->contains('id', $foreignSigner->id))
            ->assertViewHas('branches', fn ($branches): bool => $branches->contains('id', $foreignBranch->id))
            ->call('editIssuer', $foreignIssuer->id)
            ->assertSet('issuerVersionId', $foreignIssuer->id)
            ->call('copyIssuer', $foreignIssuer->id)
            ->assertHasNoErrors();

        $this->assertSame(2, IssuerProfileVersion::query()->where('branch_id', $foreignBranch->id)->count());

        $this->supervisor->givePermissionTo('offers.cross-branch');

        $review = Livewire::actingAs($this->supervisor)
            ->test(OfferDocumentMasters::class)
            ->call('approveMaster', 'signer', $foreignSigner->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $foreignSigner->fresh()->status);

        $review
            ->call('openReviewDialog', 'retire', 'signer', $foreignSigner->id)
            ->assertSet('showReviewDialog', true)
            ->set('reviewNote', 'Registrasi lintas cabang telah berakhir.')
            ->call('confirmReviewAction')
            ->assertHasNoErrors();

        $this->assertSame('retired', $foreignSigner->fresh()->status);
    }

    /** @return array{Branch, User, IssuerProfileVersion, DocumentSignerVersion} */
    private function foreignBranchMasters(): array
    {
        $branch = Branch::query()->create([
            'code' => 'BDG',
            'number_code' => 3,
            'name' => 'Cabang Bandung',
            'active' => true,
        ]);
        $creator = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'sysadmin',
        ]);
        $creator->syncRoles(['sysadmin']);
        $issuer = IssuerProfileVersion::query()->create([
            'branch_id' => $branch->id,
            'version_no' => 1,
            'legal_name' => 'KJPP Cabang Bandung Rahasia',
            'address' => 'Jl. Cabang No. 2',
            'city' => 'Bandung',
            'effective_from' => now()->toDateString(),
            'created_by' => $creator->id,
        ]);
        $signer = DocumentSignerVersion::query()->create([
            'branch_id' => $branch->id,
            'signer_key' => 'pimpinan-cabang-bandung',
            'version_no' => 1,
            'full_name' => 'Penandatangan Cabang Bandung Rahasia',
            'position' => 'Pimpinan Cabang',
            'effective_from' => now()->toDateString(),
            'created_by' => $creator->id,
        ]);

        return [$branch, $creator, $issuer, $signer];
    }

    private function assertScopedMasterCallIsNotFound(User $actor, string $method, mixed ...$parameters): void
    {
        try {
            Livewire::actingAs($actor)
                ->test(OfferDocumentMasters::class)
                ->call($method, ...$parameters);

            $this->fail("Aksi {$method} dengan ID master luar cabang seharusnya tidak ditemukan.");
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }
}
