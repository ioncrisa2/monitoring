<?php

namespace Tests\Unit\Offers;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\Offer;
use App\Models\OfferSubject;
use App\Models\OfferTemplate;
use App\Models\OfferTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Offers\OfferDocumentBootstrapper;
use App\Services\Offers\OfferDocumentMasterApprovalService;
use App\Services\Offers\OfferDocumentMasterIntegrityService;
use App\Services\Offers\OfferPreflightValidator;
use App\Services\Offers\OfferSnapshotBuilder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfferDocumentDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_offer_builds_a_renderable_provisional_snapshot_with_draft_warnings(): void
    {
        [$offer] = $this->offerFixture();

        $snapshot = app(OfferSnapshotBuilder::class)->build($offer);
        $preflight = app(OfferPreflightValidator::class)->validate($snapshot);
        $printReadyPreflight = app(OfferPreflightValidator::class)->validate(
            $snapshot,
            OfferPreflightValidator::MODE_PRINT_READY,
        );

        $this->assertCount(25, $snapshot['clauses']);
        $this->assertSame(range(1, 25), array_column($snapshot['clauses'], 'number'));
        $this->assertArrayNotHasKey('internal_note', $snapshot['engagement']);
        $this->assertSame([], $preflight['errors']);
        $this->assertNotEmpty($preflight['warnings']);
        $this->assertContains('Versi template legal belum disetujui.', $printReadyPreflight['errors']);
        $this->assertContains('Profil penerbit belum disetujui.', $printReadyPreflight['errors']);
        $this->assertContains('Profil penandatangan belum disetujui.', $printReadyPreflight['errors']);
        $this->assertContains(
            'Redaksi provisional DRAF tidak boleh digunakan untuk PDF siap cetak.',
            $printReadyPreflight['errors'],
        );
        $this->assertTrue($snapshot['metadata']['uses_provisional_copy']);
        $this->assertTrue($snapshot['metadata']['uses_provisional_issuer']);
    }

    public function test_full_simple_draft_is_scoped_snapshotted_and_passes_strict_preflight(): void
    {
        [$offer, $user, $branch] = $this->offerFixture();
        [$templateVersion, $issuer, $signer] = $this->approvedMasters($branch, $user);
        $bootstrapper = app(OfferDocumentBootstrapper::class);
        $engagement = $bootstrapper->saveDraft($offer, $this->completePayload(
            $offer,
            $templateVersion,
            $issuer,
            $signer,
        ), $user);
        $secondEngagement = $bootstrapper->saveDraft(
            $offer->fresh(),
            $bootstrapper->loadForm($offer->fresh()),
            $user,
        );

        $snapshotBuilder = app(OfferSnapshotBuilder::class);
        $snapshot = $snapshotBuilder->build($offer->fresh());
        $preflight = app(OfferPreflightValidator::class)->validate(
            $snapshot,
            OfferPreflightValidator::MODE_PRINT_READY,
        );

        $this->assertSame(1, $engagement->lock_version);
        $this->assertSame(2, $secondEngagement->lock_version);
        $this->assertSame(0, $offer->subjects()->sole()->sort_order);
        $this->assertSame(0, $offer->subjects()->sole()->assets()->sole()->sort_order);
        $this->assertSame(0, $offer->subjects()->sole()->assets()->sole()->documents()->sole()->sort_order);
        $this->assertSame(0, $offer->feeItems()->sole()->sort_order);
        $this->assertSame(1, $offer->paymentTerms()->sole()->sequence);
        $this->assertSame(0, $offer->requirements()->sole()->sort_order);
        $this->assertSame([], $preflight['errors']);
        $this->assertSame([], $preflight['warnings']);
        $this->assertFalse($snapshot['metadata']['uses_provisional_copy']);
        $this->assertFalse($snapshot['metadata']['uses_provisional_issuer']);
        $this->assertSame('approved', $snapshot['metadata']['template']['status']);
        $this->assertSame('approved', $snapshot['metadata']['issuer_profile']['status']);
        $this->assertSame('approved', $snapshot['metadata']['signer']['status']);
        $this->assertSame(1_110_000, $snapshot['commercial']['document_payable_total']);
        $this->assertSame('Satu juta seratus sepuluh ribu rupiah', $snapshot['commercial']['amount_in_words']);
        $this->assertStringContainsString('SHM 123', $snapshot['clauses'][3]['items'][0]);
        $this->assertArrayNotHasKey('internal_note', $snapshot['engagement']);
        $this->assertSame(
            $snapshotBuilder->hash($snapshot),
            $snapshotBuilder->hash(array_reverse($snapshot, true)),
        );
        $this->assertDatabaseHas('offer_number_allocations', [
            'offer_id' => $offer->getKey(),
            'full_number' => $offer->offer_no,
        ]);
    }

    public function test_snapshot_reloads_a_locked_offer_and_its_nested_rows_inside_an_existing_transaction(): void
    {
        [$offer, $user, $branch] = $this->offerFixture();
        [$templateVersion, $issuer, $signer] = $this->approvedMasters($branch, $user);
        $bootstrapper = app(OfferDocumentBootstrapper::class);
        $bootstrapper->saveDraft(
            $offer,
            $this->completePayload($offer, $templateVersion, $issuer, $signer),
            $user,
        );

        $staleOffer = Offer::query()->with([
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
        ])->findOrFail($offer->getKey());

        $updatedPayload = $bootstrapper->loadForm($offer->fresh());
        $updatedPayload['engagement']['subject'] = 'Penawaran yang telah diperbarui';
        $updatedPayload['subjects'][0]['name_snapshot'] = 'Subject versi terbaru';
        $updatedPayload['subjects'][0]['assets'][0]['description'] = 'Aset versi terbaru';
        $bootstrapper->saveDraft($offer->fresh(), $updatedPayload, $user);

        $snapshot = DB::transaction(
            fn (): array => app(OfferSnapshotBuilder::class)->build($staleOffer),
        );

        $this->assertSame(2, $snapshot['engagement']['lock_version']);
        $this->assertSame('Penawaran yang telah diperbarui', $snapshot['document']['subject']);
        $this->assertSame('Subject versi terbaru', $snapshot['subjects'][0]['name_snapshot']);
        $this->assertSame('Aset versi terbaru', $snapshot['subjects'][0]['assets'][0]['description']);
    }

    public function test_strict_preflight_requires_complete_output_and_signing_identity(): void
    {
        [$offer, $user, $branch] = $this->offerFixture();
        [$templateVersion, $issuer, $signer] = $this->approvedMasters($branch, $user);
        app(OfferDocumentBootstrapper::class)->saveDraft(
            $offer,
            $this->completePayload($offer, $templateVersion, $issuer, $signer),
            $user,
        );
        $snapshot = app(OfferSnapshotBuilder::class)->build($offer->fresh());
        $snapshot['engagement']['report_copies'] = 0;
        $snapshot['engagement']['valuation_date'] = null;
        $snapshot['engagement']['valuation_date_rule'] = null;
        $snapshot['issuer']['name'] = '';
        $snapshot['issuer']['address_lines'] = [];
        $snapshot['signatures']['issuer_name'] = '';
        $snapshot['signatures']['issuer_title'] = '';

        $errors = app(OfferPreflightValidator::class)
            ->validate($snapshot, OfferPreflightValidator::MODE_PRINT_READY)['errors'];

        $this->assertContains('Jumlah eksemplar laporan harus sedikitnya satu.', $errors);
        $this->assertContains('Tanggal penilaian atau aturan tanggal penilaian belum diisi.', $errors);
        $this->assertContains('Nama penerbit belum diisi.', $errors);
        $this->assertContains('Alamat penerbit belum diisi.', $errors);
        $this->assertContains('Nama penandatangan belum diisi.', $errors);
        $this->assertContains('Jabatan penandatangan belum diisi.', $errors);
        $this->assertSame(OfferPreflightValidator::MODE_PRINT_READY, OfferPreflightValidator::MODE_REVIEW);
        $this->assertSame(OfferPreflightValidator::MODE_PRINT_READY, OfferPreflightValidator::MODE_FINAL);
    }

    public function test_save_draft_rejects_stale_lock_and_nested_ids_from_another_offer(): void
    {
        [$offer, $user] = $this->offerFixture();
        [$otherOffer] = $this->offerFixture('SBY', 20, 'other@example.test', 2);
        $foreignSubject = OfferSubject::create([
            'offer_id' => $otherOffer->getKey(),
            'debtor_id' => $otherOffer->debtor_id,
            'name_snapshot' => 'Subject Asing',
            'primary_slot' => 1,
            'sort_order' => 0,
        ]);
        $bootstrapper = app(OfferDocumentBootstrapper::class);
        $firstPayload = [
            'engagement' => ['lock_version' => 0],
            'subjects' => [],
            'fee_items' => [],
            'payment_terms' => [],
            'requirements' => [],
        ];
        $bootstrapper->saveDraft($offer, $firstPayload, $user);

        try {
            $bootstrapper->saveDraft($offer->fresh(), $firstPayload, $user);
            $this->fail('Stale optimistic lock seharusnya ditolak.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Muat ulang', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('scope penawaran');

        $bootstrapper->saveDraft($offer->fresh(), [
            'engagement' => ['lock_version' => 1],
            'fee_items' => [[
                'offer_subject_id' => $foreignSubject->getKey(),
                'label' => 'Fee asing',
                'quantity' => 1,
                'unit_amount' => 100,
                'sort_order' => 0,
            ]],
        ], $user);
    }

    public function test_legacy_number_adoption_rolls_back_when_nested_draft_is_invalid(): void
    {
        [$offer, $user] = $this->offerFixture();

        try {
            app(OfferDocumentBootstrapper::class)->saveDraft($offer, [
                'engagement' => ['lock_version' => 0],
                'subjects' => [[
                    'debtor_id' => $offer->debtor_id,
                    'name_snapshot' => 'PT Debitur',
                    'is_primary' => true,
                    'sort_order' => 0,
                    'assets' => [[
                        'asset_type' => 'invalid',
                        'sort_order' => 0,
                        'documents' => [],
                    ]],
                ]],
            ], $user);
            $this->fail('Jenis aset invalid seharusnya ditolak.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Jenis aset', $exception->getMessage());
        }

        $this->assertNull($offer->fresh()->current_number_allocation_id);
        $this->assertDatabaseMissing('offer_number_allocations', ['offer_id' => $offer->getKey()]);
        $this->assertDatabaseMissing('offer_engagements', ['offer_id' => $offer->getKey()]);
    }

    public function test_master_approval_computes_canonical_checksums_and_makes_approved_rows_immutable(): void
    {
        [, $user, $branch] = $this->offerFixture();
        [$template, $issuer, $signer] = $this->approvedMasters($branch, $user);
        $integrity = app(OfferDocumentMasterIntegrityService::class);

        $this->assertTrue($integrity->verify($template));
        $this->assertTrue($integrity->verify($issuer));
        $this->assertTrue($integrity->verify($signer));
        $this->assertSame($user->getKey(), $template->approved_by);
        $this->assertNotNull($template->approved_at);

        try {
            $issuer->update(['phone' => '021-999']);
            $this->fail('Master approved seharusnya tidak dapat diubah.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tidak dapat dihapus');
        $signer->delete();
    }

    public function test_template_approval_rejects_incomplete_unknown_and_provisional_clauses(): void
    {
        [, $user] = $this->offerFixture();
        $template = OfferTemplate::create([
            'code' => 'INVALID',
            'name' => 'Template Invalid',
            'active' => true,
        ]);
        $version = OfferTemplateVersion::create([
            'offer_template_id' => $template->getKey(),
            'version_no' => 1,
            'schema_version' => 1,
            'clause_schema' => [
                'document' => [
                    'opening' => '[DRAF] Pembuka sementara.',
                    'closing' => 'Penutup resmi.',
                    'ignored_field' => 'Tidak boleh diabaikan diam-diam.',
                ],
                'clauses' => [
                    'unknown_clause' => ['paragraphs' => 'bukan-list'],
                ],
            ],
            'condition_schema' => ['operator' => 'unsupported'],
            'layout_version' => 'standard-v1',
            'header_mode' => 'odd_pages',
            'effective_from' => now()->subDay(),
        ]);

        try {
            app(OfferDocumentMasterApprovalService::class)->approve($version, $user);
            $this->fail('Schema template invalid seharusnya tidak dapat disetujui.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Klausul wajib belum tersedia', $exception->getMessage());
            $this->assertStringContainsString('Klausul tidak dikenal', $exception->getMessage());
            $this->assertStringContainsString('condition_schema belum didukung', $exception->getMessage());
            $this->assertStringContainsString('Field document tidak dikenal', $exception->getMessage());
            $this->assertStringContainsString('DRAF', $exception->getMessage());
        }

        $this->assertSame('draft', $version->fresh()->status);
        $this->assertNull($version->fresh()->approved_by);
    }

    public function test_model_cannot_bypass_the_master_approval_service(): void
    {
        [, $user, $branch] = $this->offerFixture();
        $issuer = IssuerProfileVersion::create([
            'branch_id' => $branch->getKey(),
            'version_no' => 1,
            'legal_name' => 'KJPP HJA dan Rekan',
            'address' => 'Jl. Kantor 1',
            'city' => 'Jakarta',
            'checksum' => str_repeat('f', 64),
        ]);

        $this->assertNotSame(str_repeat('f', 64), $issuer->checksum);

        try {
            $issuer->update([
                'status' => 'approved',
                'approved_by' => $user->getKey(),
                'approved_at' => now(),
            ]);
            $this->fail('Status approved seharusnya hanya dapat diberikan oleh approval service.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('layanan approval resmi', $exception->getMessage());
        }

        $this->assertSame('draft', $issuer->fresh()->status);
        $this->assertNull($issuer->fresh()->approved_by);
    }

    /** @return array{Offer, User, Branch} */
    private function offerFixture(
        string $branchCode = 'JKT',
        int $branchNumber = 10,
        string $email = 'operator@example.test',
        int $sequence = 1,
    ): array {
        $branch = Branch::create([
            'code' => $branchCode,
            'number_code' => $branchNumber,
            'name' => 'Cabang '.$branchCode,
            'active' => true,
        ]);
        $debtor = Debtor::create([
            'name' => 'PT Debitur '.$branchCode,
            'identifier' => 'ID-'.$branchCode,
            'address' => 'Jl. Aset '.$branchCode,
        ]);
        $client = Organization::create([
            'name' => 'PT Klien '.$branchCode,
            'type' => 'pemberi_tugas',
            'address' => 'Jl. Klien '.$branchCode,
        ]);
        $user = User::create([
            'branch_id' => $branch->getKey(),
            'name' => 'Operator '.$branchCode,
            'email' => $email,
            'password' => 'password',
            'role' => 'admin',
            'active' => true,
        ]);
        $offer = Offer::create([
            'offer_no' => "{$sequence}/S.Kontrak/KJPP-HJA'R/{$branchNumber}/VIII/2026",
            'sequence_no' => $sequence,
            'offer_date' => '2026-08-12',
            'branch_id' => $branch->getKey(),
            'debtor_id' => $debtor->getKey(),
            'client_id' => $client->getKey(),
            'fee' => 1_000_000,
            'created_by' => $user->getKey(),
        ]);

        return [$offer, $user, $branch];
    }

    /** @return array{OfferTemplateVersion, IssuerProfileVersion, DocumentSignerVersion} */
    private function approvedMasters(Branch $branch, User $user): array
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
        $templateVersion = $approval->approve($templateVersion, $user);
        $issuer = $approval->approve($issuer, $user);
        $signer = $approval->approve($signer, $user);

        return [$templateVersion, $issuer, $signer];
    }

    private function completePayload(
        Offer $offer,
        OfferTemplateVersion $template,
        IssuerProfileVersion $issuer,
        DocumentSignerVersion $signer,
    ): array {
        return [
            'engagement' => [
                'lock_version' => 0,
                'template_version_id' => $template->getKey(),
                'issuer_profile_version_id' => $issuer->getKey(),
                'signer_version_id' => $signer->getKey(),
                'issue_city' => 'Jakarta',
                'recipient_attention' => 'Direktur',
                'recipient_organization' => 'PT Klien JKT',
                'recipient_address' => 'Jl. Klien JKT',
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
                'internal_note' => 'Tidak boleh masuk snapshot.',
            ],
            'subjects' => [[
                'debtor_id' => $offer->debtor_id,
                'name_snapshot' => 'PT Debitur JKT',
                'identifier_snapshot' => 'ID-JKT',
                'address_snapshot' => 'Jl. Aset JKT',
                'is_primary' => true,
                'sort_order' => 0,
                'assets' => [[
                    'asset_type' => 'tanah',
                    'description' => 'Sebidang tanah',
                    'address' => 'Jl. Aset JKT',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'land_area_m2' => '100.50',
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
        ];
    }
}
