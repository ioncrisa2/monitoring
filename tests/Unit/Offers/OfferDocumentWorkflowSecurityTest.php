<?php

namespace Tests\Unit\Offers;

use App\Enums\OfferDocumentArtifactType;
use App\Enums\OfferDocumentStorageStatus;
use App\Enums\OfferDocumentVersionState;
use App\Enums\OfferWorkflowState;
use App\Models\Branch;
use App\Models\Debtor;
use App\Models\IssuerProfileVersion;
use App\Models\Offer;
use App\Models\OfferDocumentArtifact;
use App\Models\OfferDocumentVersion;
use App\Models\OfferEngagement;
use App\Models\OfferTemplate;
use App\Models\OfferTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Offers\OfferDocumentRenderer;
use App\Services\Offers\OfferDocumentWorkflowService;
use App\Services\Offers\OfferPreflightValidator;
use App\Services\Offers\OfferSnapshotBuilder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class OfferDocumentWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalization_is_closed_until_the_activation_flag_is_enabled(): void
    {
        ['service' => $service, 'version' => $version, 'actor' => $actor] = $this->approvedFixture();
        config()->set('offer-documents.features.finalization_enabled', false);

        try {
            $service->finalize($version, $actor);
            $this->fail('Finalization should remain closed before golden/UAT activation.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('belum diaktifkan', $exception->getMessage());
        }

        $this->assertSame(OfferDocumentVersionState::Approved, $version->fresh()->version_state);
        $this->assertDatabaseMissing('offer_document_artifacts', [
            'offer_document_version_id' => $version->getKey(),
            'artifact_type' => OfferDocumentArtifactType::Final->value,
        ]);
    }

    public function test_finalization_is_idempotent_and_pins_the_exact_approved_draft(): void
    {
        [
            'service' => $service,
            'version' => $version,
            'actor' => $actor,
            'approvedDraft' => $approvedDraft,
            'laterDraft' => $laterDraft,
        ] = $this->approvedFixture(withLaterDraft: true, expectRender: true);
        config()->set('offer-documents.features.finalization_enabled', true);

        $first = $service->finalize($version, $actor);
        $second = $service->finalize($version->fresh(), $actor);

        $this->assertNotNull($laterDraft);
        $this->assertNotSame($approvedDraft->getKey(), $laterDraft->getKey());
        $this->assertSame($approvedDraft->getKey(), $first->source_draft_artifact_id);
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, OfferDocumentArtifact::query()
            ->where('offer_document_version_id', $version->getKey())
            ->where('artifact_type', OfferDocumentArtifactType::Final->value)
            ->count());
    }

    public function test_finalization_rejects_an_approved_version_that_is_no_longer_active(): void
    {
        ['service' => $service, 'version' => $version, 'actor' => $actor] = $this->approvedFixture();
        config()->set('offer-documents.features.finalization_enabled', true);
        DB::table('offer_engagements')->where('offer_id', $version->offer_id)->update([
            'workflow_state' => OfferWorkflowState::DataDraft->value,
            'current_review_version_id' => null,
        ]);

        try {
            $service->finalize($version, $actor);
            $this->fail('A stale approved version must not be finalized.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('bukan lagi snapshot aktif', $exception->getMessage());
        }

        $this->assertDatabaseMissing('offer_document_artifacts', [
            'offer_document_version_id' => $version->getKey(),
            'artifact_type' => OfferDocumentArtifactType::Final->value,
        ]);
    }

    public function test_finalization_rejects_renderer_configuration_drift(): void
    {
        ['service' => $service, 'version' => $version, 'actor' => $actor] = $this->approvedFixture();
        config()->set('offer-documents.features.finalization_enabled', true);
        config()->set('offer-documents.renderer.version', 'changed-after-approval');

        try {
            $service->finalize($version, $actor);
            $this->fail('Renderer drift must require a newly reviewed document version.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Konfigurasi renderer berubah', $exception->getMessage());
        }

        $this->assertDatabaseMissing('offer_document_artifacts', [
            'offer_document_version_id' => $version->getKey(),
            'artifact_type' => OfferDocumentArtifactType::Final->value,
        ]);
    }

    public function test_artifact_reader_rejects_a_path_outside_its_version_archive(): void
    {
        ['service' => $service, 'version' => $version, 'actor' => $actor] = $this->approvedFixture(expectRender: true);
        config()->set('offer-documents.features.finalization_enabled', true);
        $artifact = $service->finalize($version, $actor);
        $foreignPdf = '%PDF-foreign-private-file';
        Storage::disk('local')->put('unrelated/private.pdf', $foreignPdf);
        DB::table('offer_document_artifacts')->where('id', $artifact->getKey())->update([
            'file_path' => 'unrelated/private.pdf',
            'file_size' => strlen($foreignPdf),
            'sha256' => hash('sha256', $foreignPdf),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tidak tersedia atau belum siap');
        $service->readArtifact($artifact->fresh());
    }

    public function test_version_and_ready_artifact_cannot_be_mutated_through_eloquent(): void
    {
        [
            'version' => $version,
            'approvedDraft' => $approvedDraft,
        ] = $this->approvedFixture();

        try {
            $version->update(['approved_render_profile_hash' => str_repeat('a', 64)]);
            $this->fail('Approved version metadata must be immutable through Eloquent.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('workflow resmi', $exception->getMessage());
        }

        try {
            $approvedDraft->update(['original_filename' => 'changed.pdf']);
            $this->fail('Ready artifact metadata must be immutable through Eloquent.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('bersifat immutable', $exception->getMessage());
        }
    }

    public function test_catalog_activation_cannot_bypass_reviewed_template_immutability(): void
    {
        ['version' => $version] = $this->approvedFixture();
        $template = $version->templateVersion->template;
        DB::table('offer_template_versions')
            ->where('id', $version->template_version_id)
            ->update(['status' => 'approved']);

        try {
            $template->update(['active' => false]);
            $this->fail('Reviewed template activation must not bypass the master workflow.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('bersifat immutable', $exception->getMessage());
        }

        $this->assertTrue($template->fresh()->active);
    }

    /**
     * @return array{
     *     service: OfferDocumentWorkflowService,
     *     version: OfferDocumentVersion,
     *     actor: User,
     *     approvedDraft: OfferDocumentArtifact,
     *     laterDraft: OfferDocumentArtifact|null
     * }
     */
    private function approvedFixture(bool $withLaterDraft = false, bool $expectRender = false): array
    {
        Storage::fake('local');

        $branch = Branch::query()->create([
            'code' => 'SEC',
            'number_code' => 91,
            'name' => 'Security Test',
            'active' => true,
        ]);
        $actor = User::factory()->create([
            'branch_id' => $branch->getKey(),
            'role' => 'supervisor',
        ]);
        $submitter = User::factory()->create([
            'branch_id' => $branch->getKey(),
            'role' => 'admin',
        ]);
        $debtor = Debtor::query()->create(['name' => 'Debitur Uji']);
        $client = Organization::query()->create([
            'name' => 'Klien Uji',
            'type' => 'pemberi_tugas',
        ]);
        $offer = Offer::query()->create([
            'offer_no' => 'SEC/001/2026',
            'offer_date' => '2026-08-19',
            'branch_id' => $branch->getKey(),
            'debtor_id' => $debtor->getKey(),
            'client_id' => $client->getKey(),
            'created_by' => $actor->getKey(),
        ]);
        $template = OfferTemplate::query()->create([
            'code' => 'SECURITY-FIXTURE',
            'name' => 'Security Fixture',
        ]);
        $templateVersion = OfferTemplateVersion::query()->create([
            'offer_template_id' => $template->getKey(),
            'version_no' => 1,
            'schema_version' => 1,
            'clause_schema' => [],
            'layout_version' => 'standard-v1',
            'effective_from' => '2026-08-01',
        ]);
        $issuer = IssuerProfileVersion::query()->create([
            'branch_id' => $branch->getKey(),
            'version_no' => 1,
            'legal_name' => 'Penerbit Uji',
            'address' => 'Alamat Uji',
            'city' => 'Jakarta',
            'effective_from' => '2026-08-01',
        ]);
        $snapshot = [
            'document' => ['number' => 'SEC/001/2026'],
            'metadata' => [
                'renderer_profile' => [
                    'engine' => config('offer-documents.renderer.engine'),
                    'version' => config('offer-documents.renderer.version'),
                    'paper' => config('offer-documents.renderer.paper'),
                    'orientation' => config('offer-documents.renderer.orientation'),
                ],
            ],
        ];
        $hash = fn (array $payload): string => hash('sha256', json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
        $snapshotHash = $hash($snapshot);
        $profileHash = $hash(['renderer_profile' => $snapshot['metadata']['renderer_profile']]);
        $version = OfferDocumentVersion::query()->create([
            'offer_id' => $offer->getKey(),
            'version_no' => 1,
            'version_state' => OfferDocumentVersionState::Approved,
            'template_version_id' => $templateVersion->getKey(),
            'issuer_profile_version_id' => $issuer->getKey(),
            'data_snapshot' => $snapshot,
            'snapshot_sha256' => $snapshotHash,
            'approved_snapshot_sha256' => $snapshotHash,
            'approved_render_profile_hash' => $profileHash,
            'submitted_by' => $submitter->getKey(),
            'submitted_at' => now()->subMinute(),
            'approved_by' => $actor->getKey(),
            'approved_at' => now(),
        ]);
        OfferEngagement::query()->create([
            'offer_id' => $offer->getKey(),
            'workflow_state' => OfferWorkflowState::Approved,
            'current_review_version_id' => $version->getKey(),
            'template_version_id' => $templateVersion->getKey(),
            'issuer_profile_version_id' => $issuer->getKey(),
        ]);

        $draftPdf = '%PDF-approved-review-draft';
        $draftPath = "offer-documents/{$offer->getKey()}/versions/1/submitted-draft.pdf";
        Storage::disk('local')->put($draftPath, $draftPdf);
        $approvedDraft = $this->readyDraft(
            $version,
            1,
            hash('sha256', 'offer-draft-v2|'.$version->getKey().'|'.$version->snapshot_sha256),
            $draftPath,
            $draftPdf,
            $actor,
        );
        DB::table('offer_document_versions')->where('id', $version->getKey())->update([
            'approved_draft_artifact_id' => $approvedDraft->getKey(),
        ]);
        $version = $version->fresh();

        $laterDraft = $withLaterDraft
            ? $this->readyDraft(
                $version,
                2,
                'later-draft-'.str_repeat('2', 35),
                $draftPath,
                $draftPdf,
                $actor,
            )
            : null;

        $snapshotBuilder = Mockery::mock(OfferSnapshotBuilder::class);
        $snapshotBuilder->shouldReceive('build')->zeroOrMoreTimes()->andReturn($snapshot);
        $snapshotBuilder->shouldReceive('hash')->zeroOrMoreTimes()->andReturnUsing($hash);
        $preflight = Mockery::mock(OfferPreflightValidator::class);
        $preflight->shouldReceive('validate')->zeroOrMoreTimes()->andReturn([
            'errors' => [],
            'warnings' => [],
        ]);
        $renderer = Mockery::mock(OfferDocumentRenderer::class);

        if ($expectRender) {
            $renderer->shouldReceive('render')->once()->andReturn('%PDF-final-artifact');
        } else {
            $renderer->shouldReceive('render')->never();
        }

        return [
            'service' => new OfferDocumentWorkflowService($snapshotBuilder, $preflight, $renderer),
            'version' => $version,
            'actor' => $actor,
            'approvedDraft' => $approvedDraft,
            'laterDraft' => $laterDraft,
        ];
    }

    private function readyDraft(
        OfferDocumentVersion $version,
        int $artifactNo,
        string $generationKey,
        string $path,
        string $pdf,
        User $actor,
    ): OfferDocumentArtifact {
        return OfferDocumentArtifact::query()->create([
            'offer_document_version_id' => $version->getKey(),
            'artifact_type' => OfferDocumentArtifactType::Draft,
            'artifact_no' => $artifactNo,
            'storage_status' => OfferDocumentStorageStatus::Ready,
            'generation_key' => $generationKey,
            'file_path' => $path,
            'original_filename' => 'review-draft.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
            'renderer_version' => trim((string) config('offer-documents.renderer.engine'))
                .'-'.trim((string) config('offer-documents.renderer.version')),
            'generated_by' => $actor->getKey(),
            'generated_at' => now(),
        ]);
    }
}
