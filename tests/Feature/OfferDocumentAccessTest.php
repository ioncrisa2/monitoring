<?php

namespace Tests\Feature;

use App\Livewire\Master\RolesPermissions;
use App\Livewire\Offers\DocumentEditor;
use App\Models\Branch;
use App\Models\Debtor;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\Offer;
use App\Models\OfferTemplate;
use App\Models\OfferTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use App\Policies\OfferPolicy;
use App\Services\Offers\OfferDocumentBootstrapper;
use App\Services\Offers\OfferDocumentMasterApprovalService;
use App\Services\Offers\OfferDocumentRenderer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OfferDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private Branch $jakarta;

    private Branch $surabaya;

    private Offer $jakartaOffer;

    private Offer $surabayaOffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->jakarta = $this->createBranch('JKT', 10, 'Jakarta');
        $this->surabaya = $this->createBranch('SBY', 20, 'Surabaya');

        $debtor = Debtor::create([
            'name' => 'PT Contoh Debitur',
            'identifier' => 'DEB-001',
            'address' => 'Jakarta',
        ]);
        $client = Organization::create([
            'name' => 'PT Bank Contoh',
            'type' => 'pemberi_tugas',
            'address' => 'Jl. Contoh No. 1',
        ]);
        $creator = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'sysadmin',
        ]);

        $this->jakartaOffer = $this->createOffer($this->jakarta, 1, $debtor, $client, $creator);
        $this->surabayaOffer = $this->createOffer($this->surabaya, 2, $debtor, $client, $creator);
    }

    public function test_offer_policy_is_discovered_and_requires_action_permission_plus_branch_scope(): void
    {
        $admin = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'admin',
        ]);

        $this->assertInstanceOf(OfferPolicy::class, Gate::getPolicyFor(Offer::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewDocument', $this->jakartaOffer));
        $this->assertTrue(Gate::forUser($admin)->allows('manageDocument', $this->jakartaOffer));
        $this->assertTrue(Gate::forUser($admin)->allows('generateDocumentDraft', $this->jakartaOffer));
        $this->assertFalse(Gate::forUser($admin)->allows('generateDocumentPrintReady', $this->jakartaOffer));
        $this->assertFalse(Gate::forUser($admin)->allows('viewDocument', $this->surabayaOffer));

        $admin->givePermissionTo('offers.cross-branch');

        $this->assertTrue(Gate::forUser($admin->fresh())->allows('viewDocument', $this->surabayaOffer));
    }

    public function test_print_ready_requires_its_own_permission_and_branch_scope(): void
    {
        $draftOnlyAdmin = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'admin',
        ]);

        $this->actingAs($draftOnlyAdmin)
            ->get(route('offers.documents.print-ready', $this->jakartaOffer))
            ->assertForbidden();

        $printer = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'surveyor',
        ]);
        $printer->givePermissionTo('offers.documents.generate-print-ready');

        $this->assertTrue(Gate::forUser($printer)->allows('generateDocumentPrintReady', $this->jakartaOffer));
        $this->assertFalse(Gate::forUser($printer)->allows('generateDocumentPrintReady', $this->surabayaOffer));

        $this->actingAs($printer)
            ->get(route('offers.documents.print-ready', $this->surabayaOffer))
            ->assertForbidden();
    }

    public function test_print_ready_rejects_provisional_offer_without_writing_success_audit(): void
    {
        $printer = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'surveyor',
        ]);
        $printer->givePermissionTo('offers.documents.generate-print-ready');

        $this->actingAs($printer)
            ->get(route('offers.documents.print-ready', $this->jakartaOffer))
            ->assertStatus(422)
            ->assertHeaderMissing('content-disposition');

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $printer->id,
            'action' => 'GENERATE_PRINT_READY',
            'model_type' => 'Offer',
            'model_id' => $this->jakartaOffer->id,
        ]);
    }

    public function test_authorized_same_branch_user_can_download_an_approved_print_ready_pdf(): void
    {
        $supervisor = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'supervisor',
        ]);
        $this->makePrintReady($this->jakartaOffer, $this->jakarta, $supervisor);

        $response = $this->actingAs($supervisor)
            ->get(route('offers.documents.print-ready', $this->jakartaOffer));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringStartsWith('attachment; filename="penawaran-', $disposition);
        $this->assertStringEndsWith('.pdf"', $disposition);
        $this->assertStringNotContainsString('-draft.pdf', $disposition);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $supervisor->id,
            'action' => 'GENERATE_PRINT_READY',
            'model_type' => 'Offer',
            'model_id' => $this->jakartaOffer->id,
        ]);
    }

    public function test_print_ready_rejects_master_content_tampered_outside_the_approval_workflow(): void
    {
        $supervisor = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'supervisor',
        ]);
        $this->makePrintReady($this->jakartaOffer, $this->jakarta, $supervisor);

        $templateVersion = $this->jakartaOffer->fresh()->engagement->templateVersion;
        $schema = $templateVersion->clause_schema;
        $schema['document']['opening'] = 'Konten yang diubah setelah persetujuan.';

        DB::table('offer_template_versions')
            ->where('id', $templateVersion->getKey())
            ->update([
                'clause_schema' => json_encode($schema, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        $this->actingAs($supervisor)
            ->get(route('offers.documents.print-ready', $this->jakartaOffer))
            ->assertStatus(422)
            ->assertHeaderMissing('content-disposition');

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $supervisor->id,
            'action' => 'GENERATE_PRINT_READY',
            'model_id' => $this->jakartaOffer->id,
        ]);
    }

    public function test_print_ready_translates_only_renderer_contract_failures_to_a_generic_422(): void
    {
        $supervisor = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'supervisor',
        ]);
        $this->makePrintReady($this->jakartaOffer, $this->jakarta, $supervisor);
        $this->mock(OfferDocumentRenderer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')
                ->once()
                ->andThrow(new InvalidArgumentException('detail internal renderer'));
        });

        $this->actingAs($supervisor)
            ->get(route('offers.documents.print-ready', $this->jakartaOffer))
            ->assertStatus(422);

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $supervisor->id,
            'action' => 'GENERATE_PRINT_READY',
            'model_id' => $this->jakartaOffer->id,
        ]);
    }

    public function test_print_ready_does_not_swallow_unexpected_renderer_failures(): void
    {
        $supervisor = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'supervisor',
        ]);
        $this->makePrintReady($this->jakartaOffer, $this->jakarta, $supervisor);
        $this->mock(OfferDocumentRenderer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')
                ->once()
                ->andThrow(new RuntimeException('renderer gagal'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($supervisor)
                ->get(route('offers.documents.print-ready', $this->jakartaOffer));
            $this->fail('Kegagalan renderer yang tidak terduga seharusnya diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('renderer gagal', $exception->getMessage());
        }

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $supervisor->id,
            'action' => 'GENERATE_PRINT_READY',
            'model_id' => $this->jakartaOffer->id,
        ]);
    }

    public function test_print_ready_ui_requires_strict_check_before_revealing_download_action(): void
    {
        $supervisor = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'supervisor',
        ]);
        $this->makePrintReady($this->jakartaOffer, $this->jakarta, $supervisor);
        $downloadUrl = route('offers.documents.print-ready', $this->jakartaOffer);

        Livewire::actingAs($supervisor)
            ->test(DocumentEditor::class, ['offer' => $this->jakartaOffer->fresh()])
            ->assertSet('printReadyEligible', false)
            ->assertSee('Periksa PDF siap cetak')
            ->assertDontSeeHtml('href="'.$downloadUrl.'"')
            ->call('checkPrintReady')
            ->assertSet('preflight.errors', [])
            ->assertSet('printReadyEligible', true)
            ->assertSee('Unduh PDF siap cetak')
            ->assertSeeHtml('href="'.$downloadUrl.'"');
    }

    public function test_print_ready_endpoint_is_throttled_without_extra_success_audit(): void
    {
        $supervisor = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'supervisor',
        ]);
        $this->makePrintReady($this->jakartaOffer, $this->jakarta, $supervisor);
        $this->mock(OfferDocumentRenderer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')->times(10)->andReturn('%PDF-test');
        });

        $this->actingAs($supervisor);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->get(route('offers.documents.print-ready', $this->jakartaOffer))->assertOk();
        }

        $this->get(route('offers.documents.print-ready', $this->jakartaOffer))->assertTooManyRequests();
        $this->assertDatabaseCount('activity_logs', 10);
    }

    public function test_cross_branch_permission_alone_does_not_grant_document_access(): void
    {
        $surveyor = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'surveyor',
        ]);
        $surveyor->givePermissionTo('offers.cross-branch');

        $this->assertFalse(Gate::forUser($surveyor)->allows('viewDocument', $this->surabayaOffer));
        $this->actingAs($surveyor)
            ->get(route('offers.documents.edit', $this->surabayaOffer))
            ->assertForbidden();
    }

    public function test_editor_route_redirects_guests_and_rejects_a_foreign_branch(): void
    {
        $this->get(route('offers.documents.edit', $this->jakartaOffer))
            ->assertRedirect(route('login'));

        $admin = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('offers.documents.edit', $this->surabayaOffer))
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(DocumentEditor::class, ['offer' => $this->surabayaOffer])
            ->assertStatus(403);
    }

    public function test_same_branch_editor_renders_the_draft_contract_without_changing_the_legacy_offer(): void
    {
        $admin = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'admin',
        ]);
        $trackedFields = [
            'offer_no',
            'sequence_no',
            'offer_date',
            'branch_id',
            'debtor_id',
            'client_id',
            'fee',
            'ta',
            'dpp',
            'ppn',
            'pph',
            'outcome',
            'created_by',
        ];
        $original = $this->jakartaOffer->fresh()->only($trackedFields);

        $this->actingAs($admin)
            ->get(route('offers.documents.edit', $this->jakartaOffer))
            ->assertOk()
            ->assertSee('Dokumen Penawaran')
            ->assertSee('Penerima dan referensi')
            ->assertSee('Pihak dan objek penilaian')
            ->assertSee('Biaya, pajak, dan termin')
            ->assertSee('Simpan draft')
            ->assertDontSee('Periksa PDF siap cetak')
            ->assertDontSee('Unduh PDF siap cetak')
            ->assertSeeHtml('wire:submit="saveDraft"')
            ->assertSeeHtml('wire:model="draft.engagement.recipient_organization"')
            ->assertSeeHtml('wire:click="checkPreflight"');

        $this->assertEquals($original, $this->jakartaOffer->fresh()->only($trackedFields));
    }

    public function test_view_only_user_can_read_editor_but_cannot_run_mutating_actions(): void
    {
        $viewer = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'surveyor',
        ]);
        $viewer->givePermissionTo('offers.documents.view');

        $this->actingAs($viewer)
            ->get(route('offers.documents.edit', $this->jakartaOffer))
            ->assertOk()
            ->assertSee('Anda memiliki akses baca')
            ->assertDontSee('Simpan draft');

        Livewire::actingAs($viewer)
            ->test(DocumentEditor::class, ['offer' => $this->jakartaOffer])
            ->call('addSubject')
            ->assertStatus(403);
    }

    public function test_preview_and_download_routes_enforce_generate_permission_and_branch_scope_first(): void
    {
        $viewer = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'surveyor',
        ]);
        $viewer->givePermissionTo('offers.documents.view');

        $this->actingAs($viewer)
            ->get(route('offers.documents.preview', $this->jakartaOffer))
            ->assertForbidden();
        $this->actingAs($viewer)
            ->get(route('offers.documents.download', $this->jakartaOffer))
            ->assertForbidden();

        $admin = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('offers.documents.preview', $this->surabayaOffer))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('offers.documents.download', $this->surabayaOffer))
            ->assertForbidden();
    }

    public function test_authorized_same_branch_user_can_preview_and_download_a_provisional_pdf_draft(): void
    {
        $admin = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'admin',
        ]);

        $preview = $this->actingAs($admin)
            ->get(route('offers.documents.preview', $this->jakartaOffer));

        $preview->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
        $this->assertStringStartsWith('%PDF-', $preview->getContent());
        $this->assertStringStartsWith(
            'inline; filename="penawaran-',
            (string) $preview->headers->get('content-disposition'),
        );

        $download = $this->actingAs($admin)
            ->get(route('offers.documents.download', $this->jakartaOffer));

        $download->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringStartsWith('%PDF-', $download->getContent());
        $this->assertStringStartsWith(
            'attachment; filename="penawaran-',
            (string) $download->headers->get('content-disposition'),
        );
        $this->assertStringEndsWith('-draft.pdf"', (string) $download->headers->get('content-disposition'));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'PREVIEW_DRAFT',
            'model_type' => 'Offer',
            'model_id' => $this->jakartaOffer->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'DOWNLOAD_DRAFT',
            'model_type' => 'Offer',
            'model_id' => $this->jakartaOffer->id,
        ]);
    }

    public function test_editor_saves_and_updates_a_nested_draft_without_duplicating_rows(): void
    {
        $admin = User::factory()->create([
            'branch_id' => $this->jakarta->id,
            'role' => 'admin',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(DocumentEditor::class, ['offer' => $this->jakartaOffer])
            ->assertSet('draft.engagement.lock_version', 0)
            ->set('draft.engagement.ownership_form', 'Hak Milik')
            ->set('draft.engagement.purpose', 'Penjaminan utang')
            ->set('draft.engagement.valuation_basis', 'Nilai Pasar')
            ->set('draft.engagement.investigation_level', 'full')
            ->set('draft.engagement.report_format', 'complete')
            ->set('draft.engagement.report_copies', 2)
            ->set('draft.engagement.completion_days', 14)
            ->set('draft.engagement.completion_day_type', 'business')
            ->set('draft.engagement.tax_inclusion', 'excluded')
            ->call('addAsset', 0)
            ->set('draft.subjects.0.assets.0.description', 'Sebidang tanah dan bangunan')
            ->set('draft.subjects.0.assets.0.address', 'Jl. Aset No. 10, Jakarta')
            ->set('draft.subjects.0.assets.0.documents.0.document_type', 'SHM')
            ->set('draft.subjects.0.assets.0.documents.0.document_no', '123/Jakarta')
            ->call('addRequirement')
            ->set('draft.requirements.0.description_snapshot', 'Salinan sertifikat tanah')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('draft.engagement.lock_version', 1);

        $this->assertDatabaseCount('offer_engagements', 1);
        $this->assertDatabaseCount('offer_subjects', 1);
        $this->assertDatabaseCount('offer_assets', 1);
        $this->assertDatabaseCount('offer_asset_documents', 1);
        $this->assertDatabaseCount('offer_fee_items', 1);
        $this->assertDatabaseCount('offer_requirements', 1);
        $this->assertNotNull($this->jakartaOffer->fresh()->current_number_allocation_id);

        $component
            ->set('draft.engagement.internal_note', 'Catatan internal diperbarui')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('draft.engagement.lock_version', 2)
            ->call('checkPreflight')
            ->assertSet('preflight.errors', []);

        $this->assertDatabaseCount('offer_engagements', 1);
        $this->assertDatabaseCount('offer_subjects', 1);
        $this->assertDatabaseCount('offer_assets', 1);
        $this->assertDatabaseCount('offer_asset_documents', 1);
        $this->assertDatabaseCount('offer_fee_items', 1);
        $this->assertDatabaseCount('offer_requirements', 1);

        $component
            ->call('addPaymentTerm')
            ->set('draft.payment_terms.0.percentage_bps', 5000)
            ->set('draft.payment_terms.0.trigger_text', 'Saat penugasan dimulai')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('draft.engagement.lock_version', 3);

        $this->actingAs($admin)
            ->get(route('offers.documents.preview', $this->jakartaOffer))
            ->assertStatus(422);
    }

    public function test_document_permissions_are_seeded_grouped_and_assigned_conservatively(): void
    {
        foreach ([
            'offers.documents.view',
            'offers.documents.manage',
            'offers.documents.generate-draft',
            'offers.documents.generate-print-ready',
            'offers.cross-branch',
        ] as $permission) {
            $this->assertNotNull(Permission::findByName($permission));
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $sysadmin = User::factory()->create(['role' => 'sysadmin']);

        $this->assertTrue($admin->can('offers.documents.generate-draft'));
        $this->assertFalse($admin->can('offers.documents.generate-print-ready'));
        $this->assertTrue(User::factory()->create(['role' => 'supervisor'])->can('offers.documents.generate-print-ready'));
        $this->assertFalse($admin->can('offers.cross-branch'));
        $this->assertTrue($sysadmin->can('offers.cross-branch'));

        Livewire::actingAs($sysadmin)
            ->test(RolesPermissions::class)
            ->assertSee('Dokumen penawaran')
            ->assertSee('Lihat dokumen penawaran')
            ->assertSee('Buat PDF siap cetak')
            ->assertSee('Akses penawaran lintas cabang');
    }

    private function makePrintReady(Offer $offer, Branch $branch, User $actor): void
    {
        $clauses = [];

        foreach ((array) config('offer-documents.clause_titles') as $key => $title) {
            $clauses[$key] = ['paragraphs' => ["Redaksi disetujui untuk {$title}."]];
        }

        $template = OfferTemplate::create([
            'code' => 'STANDARD',
            'name' => 'Template Standar',
            'active' => true,
            'is_default' => true,
        ]);
        $templateVersion = OfferTemplateVersion::create([
            'offer_template_id' => $template->getKey(),
            'version_no' => 1,
            'schema_version' => 1,
            'clause_schema' => [
                'document' => [
                    'opening' => 'Pembuka yang telah disetujui.',
                    'closing' => 'Penutup yang telah disetujui.',
                ],
                'clauses' => $clauses,
            ],
            'layout_version' => 'standard-v1',
            'header_mode' => 'odd_pages',
            'effective_from' => now()->subDay(),
        ]);
        $issuer = IssuerProfileVersion::create([
            'branch_id' => $branch->getKey(),
            'version_no' => 1,
            'legal_name' => 'KJPP HJA dan Rekan',
            'address' => 'Jl. Kantor 1',
            'city' => 'Jakarta',
            'phone' => '021-123',
            'effective_from' => now()->subDay(),
        ]);
        $signer = DocumentSignerVersion::create([
            'branch_id' => $branch->getKey(),
            'signer_key' => 'partner-utama',
            'version_no' => 1,
            'full_name' => 'Penilai Utama',
            'position' => 'Partner',
            'effective_from' => now()->subDay(),
        ]);

        $approval = app(OfferDocumentMasterApprovalService::class);
        $templateVersion = $approval->approve($templateVersion, $actor);
        $issuer = $approval->approve($issuer, $actor);
        $signer = $approval->approve($signer, $actor);

        app(OfferDocumentBootstrapper::class)->saveDraft($offer, [
            'engagement' => [
                'lock_version' => 0,
                'template_version_id' => $templateVersion->getKey(),
                'issuer_profile_version_id' => $issuer->getKey(),
                'signer_version_id' => $signer->getKey(),
                'issue_city' => 'Jakarta',
                'recipient_attention' => 'Direktur',
                'recipient_organization' => 'PT Bank Contoh',
                'recipient_address' => 'Jl. Contoh No. 1',
                'recipient_city' => 'Jakarta',
                'subject' => 'Penawaran Jasa Penilaian',
                'request_reference_type' => 'letter',
                'request_reference_no' => 'REQ-001',
                'request_reference_date' => '2026-08-10',
                'ownership_form' => 'Hak Milik',
                'currency' => 'IDR',
                'purpose' => 'Penjaminan utang',
                'valuation_basis' => 'Nilai Pasar',
                'valuation_date' => '2026-08-12',
                'investigation_level' => 'full',
                'report_format' => 'complete',
                'report_language' => 'id',
                'report_copies' => 2,
                'completion_days' => 10,
                'completion_day_type' => 'business',
                'tax_inclusion' => 'excluded',
                'ppn_rate_bps' => 1100,
                'pph_rate_bps' => 200,
                'cost_inclusions' => ['Transportasi'],
            ],
            'subjects' => [[
                'debtor_id' => $offer->debtor_id,
                'name_snapshot' => 'PT Contoh Debitur',
                'identifier_snapshot' => 'DEB-001',
                'address_snapshot' => 'Jakarta',
                'is_primary' => true,
                'sort_order' => 0,
                'assets' => [[
                    'asset_type' => 'tanah',
                    'description' => 'Sebidang tanah',
                    'address' => 'Jl. Aset No. 1, Jakarta',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'sort_order' => 0,
                    'documents' => [[
                        'document_type' => 'SHM',
                        'document_no' => '123',
                        'is_primary' => true,
                        'sort_order' => 0,
                    ]],
                ]],
            ]],
            'fee_items' => [[
                'label' => 'Jasa Penilaian',
                'quantity' => 1,
                'unit_amount' => 1_000_000,
                'sort_order' => 0,
            ]],
            'payment_terms' => [[
                'sequence' => 1,
                'percentage_bps' => 10_000,
                'trigger_text' => 'Setelah laporan selesai',
            ]],
            'requirements' => [[
                'requirement_code' => 'SHM',
                'description_snapshot' => 'Salinan sertifikat tanah',
                'emphasis_style' => 'normal',
                'sort_order' => 0,
            ]],
        ], $actor);
    }

    private function createBranch(string $code, int $numberCode, string $name): Branch
    {
        return Branch::create([
            'code' => $code,
            'number_code' => $numberCode,
            'name' => $name,
            'active' => true,
        ]);
    }

    private function createOffer(
        Branch $branch,
        int $sequence,
        Debtor $debtor,
        Organization $client,
        User $creator,
    ): Offer {
        return Offer::create([
            'offer_no' => $sequence."/S.Kontrak/KJPP-HJA'R/{$branch->number_code}/VIII/2026",
            'sequence_no' => $sequence,
            'offer_date' => '2026-08-12',
            'branch_id' => $branch->id,
            'debtor_id' => $debtor->id,
            'client_id' => $client->id,
            'fee' => 1_000_000,
            'ta' => 100_000,
            'dpp' => 900_000,
            'ppn' => 99_000,
            'pph' => 18_000,
            'outcome' => 'DRAFT',
            'created_by' => $creator->id,
        ]);
    }
}
