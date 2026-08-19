<?php

namespace App\Services\Offers;

use App\Enums\OfferDocumentArtifactType;
use App\Enums\OfferDocumentOutputMode;
use App\Enums\OfferDocumentStorageStatus;
use App\Enums\OfferDocumentVersionState;
use App\Enums\OfferWorkflowState;
use App\Models\ActivityLog;
use App\Models\Offer;
use App\Models\OfferDocumentArtifact;
use App\Models\OfferDocumentVersion;
use App\Models\OfferEngagement;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class OfferDocumentWorkflowService
{
    private const ARCHIVE_DISK = 'local';

    public function __construct(
        private readonly OfferSnapshotBuilder $snapshotBuilder,
        private readonly OfferPreflightValidator $preflightValidator,
        private readonly OfferDocumentRenderer $renderer,
    ) {}

    /**
     * Freeze the current live draft into a review version and persist the exact
     * watermarked PDF that the reviewer will inspect.
     */
    public function submit(Offer $offer, User $submitter): OfferDocumentVersion
    {
        [$version, $created] = DB::transaction(function () use ($offer, $submitter): array {
            $lockedOffer = Offer::query()->lockForUpdate()->findOrFail($offer->getKey());
            $engagement = OfferEngagement::query()
                ->where('offer_id', $lockedOffer->getKey())
                ->lockForUpdate()
                ->first();

            if (! $engagement instanceof OfferEngagement) {
                throw new DomainException('Simpan data dokumen sebelum mengajukannya untuk ditinjau.');
            }

            $snapshot = $this->snapshotBuilder->build($lockedOffer);
            $this->assertStrictPreflight($snapshot);
            $snapshotHash = $this->snapshotBuilder->hash($snapshot);

            $currentReview = $engagement->current_review_version_id
                ? OfferDocumentVersion::query()
                    ->whereKey($engagement->current_review_version_id)
                    ->where('offer_id', $lockedOffer->getKey())
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($currentReview instanceof OfferDocumentVersion
                && $currentReview->version_state === OfferDocumentVersionState::InReview
                && $engagement->workflow_state === OfferWorkflowState::InReview
                && hash_equals($currentReview->snapshot_sha256, $snapshotHash)) {
                return [$currentReview, false];
            }

            // A draft save intentionally clears the active review pointer. Mark
            // every remaining in-review archive for this offer as superseded
            // before opening the next immutable review version.
            DB::table('offer_document_versions')
                ->where('offer_id', $lockedOffer->getKey())
                ->where('version_state', OfferDocumentVersionState::InReview->value)
                ->update([
                    'version_state' => OfferDocumentVersionState::Superseded->value,
                    'updated_at' => now(),
                ]);

            $latest = OfferDocumentVersion::query()
                ->where('offer_id', $lockedOffer->getKey())
                ->lockForUpdate()
                ->latest('version_no')
                ->first();
            $nextVersion = ((int) ($latest?->version_no ?? 0)) + 1;
            $templateId = $snapshot['metadata']['template']['id'] ?? null;
            $issuerId = $snapshot['metadata']['issuer_profile']['id'] ?? null;
            $signerId = $snapshot['metadata']['signer']['id'] ?? null;

            if (! is_int($templateId) || ! is_int($issuerId)) {
                throw new DomainException('Template dan profil penerbit resmi wajib dipilih sebelum pengajuan.');
            }

            $version = OfferDocumentVersion::query()->create([
                'offer_id' => $lockedOffer->getKey(),
                'version_no' => $nextVersion,
                'version_state' => OfferDocumentVersionState::InReview,
                'template_version_id' => $templateId,
                'issuer_profile_version_id' => $issuerId,
                'signer_version_id' => is_int($signerId) ? $signerId : null,
                'data_snapshot' => $snapshot,
                'snapshot_sha256' => $snapshotHash,
                'submitted_by' => $submitter->getKey(),
                'submitted_at' => now(),
                'supersedes_id' => $latest?->getKey(),
                'lock_version' => 0,
            ]);

            OfferEngagement::query()->whereKey($engagement->getKey())->update([
                'workflow_state' => OfferWorkflowState::InReview->value,
                'current_review_version_id' => $version->getKey(),
                'state_changed_by' => $submitter->getKey(),
                'state_changed_at' => now(),
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

            return [$version, true];
        }, 5);

        $artifact = $this->ensureDraftArtifact($version, $submitter);

        if ($created) {
            $this->audit(
                $submitter,
                'SUBMIT_OFFER_DOCUMENT',
                $version,
                "Mengajukan dokumen penawaran versi {$version->version_no} untuk ditinjau; artifact draft #{$artifact->getKey()} diarsipkan.",
            );
        }

        return $version->fresh(['artifacts', 'approvedDraftArtifact']);
    }

    /** Approve only the submitted snapshot, never a newly rebuilt live draft. */
    public function approve(OfferDocumentVersion $version, User $reviewer): OfferDocumentVersion
    {
        $approved = DB::transaction(function () use ($version, $reviewer): OfferDocumentVersion {
            $locked = OfferDocumentVersion::query()->lockForUpdate()->findOrFail($version->getKey());

            if ($locked->version_state === OfferDocumentVersionState::Approved
                || $locked->version_state === OfferDocumentVersionState::Finalized) {
                $this->assertVersionIntegrity($locked, true);
                $draftArtifact = $this->approvedDraftArtifact($locked);
                $this->assertArtifactIntegrity($draftArtifact);

                if (! hash_equals(
                    (string) $locked->approved_render_profile_hash,
                    $this->renderProfileHash((array) $locked->data_snapshot),
                )) {
                    throw new DomainException('Profil renderer snapshot tidak sama dengan profil yang disetujui.');
                }

                return $locked;
            }

            if ((int) $locked->submitted_by === (int) $reviewer->getKey()) {
                throw new DomainException('Pengaju dokumen tidak boleh menyetujui pengajuannya sendiri.');
            }

            $engagement = OfferEngagement::query()
                ->where('offer_id', $locked->offer_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $engagement->current_review_version_id !== (int) $locked->getKey()) {
                throw new DomainException('Versi ini bukan lagi snapshot aktif yang sedang ditinjau.');
            }

            if ($locked->version_state !== OfferDocumentVersionState::InReview) {
                throw new DomainException('Hanya versi yang sedang ditinjau yang dapat disetujui.');
            }

            $this->assertVersionIntegrity($locked);
            $this->assertLiveSnapshotUnchanged($locked);
            $this->assertStrictPreflight((array) $locked->data_snapshot);
            $draftArtifact = $this->readyDraftArtifact($locked);
            $this->assertArtifactIntegrity($draftArtifact);
            $this->assertCurrentRendererProfile((array) $locked->data_snapshot);
            $profileHash = $this->renderProfileHash((array) $locked->data_snapshot);
            $approvedAt = now();

            $updated = DB::table('offer_document_versions')
                ->where('id', $locked->getKey())
                ->where('version_state', OfferDocumentVersionState::InReview->value)
                ->where('snapshot_sha256', $locked->snapshot_sha256)
                ->update([
                    'version_state' => OfferDocumentVersionState::Approved->value,
                    'approved_snapshot_sha256' => $locked->snapshot_sha256,
                    'approved_draft_artifact_id' => $draftArtifact->getKey(),
                    'approved_render_profile_hash' => $profileHash,
                    'approved_by' => $reviewer->getKey(),
                    'approved_at' => $approvedAt,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => $approvedAt,
                ]);

            if ($updated !== 1) {
                throw new DomainException('Versi berubah saat proses persetujuan; muat ulang lalu coba lagi.');
            }

            OfferEngagement::query()->whereKey($engagement->getKey())->update([
                'workflow_state' => OfferWorkflowState::Approved->value,
                'state_changed_by' => $reviewer->getKey(),
                'state_changed_at' => $approvedAt,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => $approvedAt,
            ]);

            return OfferDocumentVersion::query()->findOrFail($locked->getKey());
        }, 5);

        $this->audit(
            $reviewer,
            'APPROVE_OFFER_DOCUMENT',
            $approved,
            "Menyetujui snapshot dokumen penawaran versi {$approved->version_no}.",
        );

        return $approved;
    }

    /** Return an in-review snapshot to the editor without deleting its archive. */
    public function reject(OfferDocumentVersion $version, User $reviewer, string $reason): OfferDocumentVersion
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new DomainException('Catatan penolakan wajib diisi dan maksimal 1000 karakter.');
        }

        $rejected = DB::transaction(function () use ($version, $reviewer): OfferDocumentVersion {
            $locked = OfferDocumentVersion::query()->lockForUpdate()->findOrFail($version->getKey());

            if ($locked->version_state !== OfferDocumentVersionState::InReview) {
                throw new DomainException('Hanya versi yang sedang ditinjau yang dapat ditolak.');
            }

            $engagement = OfferEngagement::query()
                ->where('offer_id', $locked->offer_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $engagement->current_review_version_id !== (int) $locked->getKey()) {
                throw new DomainException('Versi ini bukan lagi snapshot aktif yang sedang ditinjau.');
            }

            DB::table('offer_document_versions')->where('id', $locked->getKey())->update([
                'version_state' => OfferDocumentVersionState::Void->value,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

            if ((int) $engagement->current_review_version_id === (int) $locked->getKey()) {
                OfferEngagement::query()->whereKey($engagement->getKey())->update([
                    'workflow_state' => OfferWorkflowState::DataDraft->value,
                    'current_review_version_id' => null,
                    'state_changed_by' => $reviewer->getKey(),
                    'state_changed_at' => now(),
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
            }

            return OfferDocumentVersion::query()->findOrFail($locked->getKey());
        }, 5);

        $this->audit(
            $reviewer,
            'REJECT_OFFER_DOCUMENT',
            $rejected,
            "Menolak dokumen penawaran versi {$rejected->version_no}. Catatan: {$reason}",
        );

        return $rejected;
    }

    /**
     * Create the single clean final artifact from the approved immutable
     * snapshot. Repeated calls return the same artifact.
     */
    public function finalize(OfferDocumentVersion $version, User $finalizer): OfferDocumentArtifact
    {
        if (! config('offer-documents.features.finalization_enabled', false)) {
            throw new DomainException('Finalisasi PDF belum diaktifkan; selesaikan golden test dan UAT cetak terlebih dahulu.');
        }

        [$lockedVersion, $artifact, $mustRender] = DB::transaction(
            function () use ($version, $finalizer): array {
                $locked = OfferDocumentVersion::query()->lockForUpdate()->findOrFail($version->getKey());
                $existing = OfferDocumentArtifact::query()
                    ->where('offer_document_version_id', $locked->getKey())
                    ->where('artifact_type', OfferDocumentArtifactType::Final->value)
                    ->where('final_slot', 1)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof OfferDocumentArtifact
                    && $existing->storage_status === OfferDocumentStorageStatus::Ready) {
                    if (! in_array($locked->version_state, [
                        OfferDocumentVersionState::Finalized,
                        OfferDocumentVersionState::Superseded,
                    ], true)) {
                        throw new DomainException('Artifact final tersedia pada status versi yang tidak valid.');
                    }

                    $this->assertVersionIntegrity($locked, true);
                    $approvedDraft = $this->approvedDraftArtifact($locked);
                    $this->assertArtifactIntegrity($approvedDraft);
                    $this->assertFinalArtifactProvenance($locked, $existing, $approvedDraft);
                    $this->assertArtifactIntegrity($existing);

                    return [$locked, $existing, false];
                }

                if ($locked->version_state !== OfferDocumentVersionState::Approved) {
                    throw new DomainException('Hanya versi yang telah disetujui yang dapat difinalkan.');
                }

                $engagement = OfferEngagement::query()
                    ->where('offer_id', $locked->offer_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $engagement->current_review_version_id !== (int) $locked->getKey()
                    || $engagement->workflow_state !== OfferWorkflowState::Approved) {
                    throw new DomainException('Versi approved ini bukan lagi snapshot aktif yang dapat difinalkan.');
                }

                $this->assertVersionIntegrity($locked, true);
                $this->assertLiveSnapshotUnchanged($locked);
                $this->assertStrictPreflight((array) $locked->data_snapshot);
                $draftArtifact = $this->approvedDraftArtifact($locked);
                $this->assertArtifactIntegrity($draftArtifact);

                if (! hash_equals(
                    (string) $locked->approved_render_profile_hash,
                    $this->renderProfileHash((array) $locked->data_snapshot),
                )) {
                    throw new DomainException('Profil renderer snapshot tidak sama dengan profil yang disetujui.');
                }

                $this->assertCurrentRendererProfile((array) $locked->data_snapshot);

                if ($existing instanceof OfferDocumentArtifact
                    && $existing->storage_status === OfferDocumentStorageStatus::Pending) {
                    throw new DomainException('Finalisasi versi ini sedang diproses.');
                }

                $generationKey = hash('sha256', implode('|', [
                    'offer-final-v2',
                    $locked->getKey(),
                    $locked->approved_snapshot_sha256,
                    $locked->approved_render_profile_hash,
                    $draftArtifact->sha256,
                ]));
                $now = now();

                if ($existing instanceof OfferDocumentArtifact) {
                    DB::table('offer_document_artifacts')->where('id', $existing->getKey())->update([
                        'storage_status' => OfferDocumentStorageStatus::Pending->value,
                        'generation_key' => $generationKey,
                        'source_draft_artifact_id' => $draftArtifact->getKey(),
                        'file_path' => null,
                        'file_size' => null,
                        'sha256' => null,
                        'generated_by' => $finalizer->getKey(),
                        'generated_at' => null,
                        'failure_code' => null,
                        'failure_message' => null,
                        'updated_at' => $now,
                    ]);
                    $artifact = OfferDocumentArtifact::query()->findOrFail($existing->getKey());
                } else {
                    $artifact = OfferDocumentArtifact::query()->create([
                        'offer_document_version_id' => $locked->getKey(),
                        'artifact_type' => OfferDocumentArtifactType::Final,
                        'artifact_no' => $this->nextArtifactNumber($locked),
                        'final_slot' => 1,
                        'storage_status' => OfferDocumentStorageStatus::Pending,
                        'generation_key' => $generationKey,
                        'source_draft_artifact_id' => $draftArtifact->getKey(),
                        'original_filename' => $this->filename($locked, false),
                        'mime_type' => 'application/pdf',
                        'renderer_version' => $this->rendererVersion(),
                        'generated_by' => $finalizer->getKey(),
                    ]);
                }

                return [$locked, $artifact, true];
            },
            5,
        );

        if (! $mustRender) {
            return $artifact;
        }

        try {
            $pdf = $this->renderer->render(
                (array) $lockedVersion->data_snapshot,
                OfferDocumentOutputMode::PrintReady,
            );
            $path = $this->artifactPath($lockedVersion, 'final.pdf');
            $this->storePdf($path, $pdf);

            $artifact = DB::transaction(function () use (
                $lockedVersion,
                $artifact,
                $finalizer,
                $path,
                $pdf,
            ): OfferDocumentArtifact {
                $lockedArtifact = OfferDocumentArtifact::query()->lockForUpdate()->findOrFail($artifact->getKey());
                $locked = OfferDocumentVersion::query()->lockForUpdate()->findOrFail($lockedVersion->getKey());
                $engagement = OfferEngagement::query()
                    ->where('offer_id', $locked->offer_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedArtifact->storage_status !== OfferDocumentStorageStatus::Pending
                    || $locked->version_state !== OfferDocumentVersionState::Approved) {
                    throw new DomainException('Status finalisasi berubah sebelum artifact selesai disimpan.');
                }

                if ((int) $engagement->current_review_version_id !== (int) $locked->getKey()
                    || $engagement->workflow_state !== OfferWorkflowState::Approved) {
                    throw new DomainException('Snapshot aktif berubah sebelum artifact selesai disimpan.');
                }

                $now = now();
                DB::table('offer_document_artifacts')->where('id', $lockedArtifact->getKey())->update([
                    'storage_status' => OfferDocumentStorageStatus::Ready->value,
                    'file_path' => $path,
                    'original_filename' => $this->filename($locked, false),
                    'mime_type' => 'application/pdf',
                    'file_size' => strlen($pdf),
                    'sha256' => hash('sha256', $pdf),
                    'renderer_version' => $this->rendererVersion(),
                    'generated_by' => $finalizer->getKey(),
                    'generated_at' => $now,
                    'failure_code' => null,
                    'failure_message' => null,
                    'updated_at' => $now,
                ]);

                DB::table('offer_document_versions')->where('id', $locked->getKey())->update([
                    'version_state' => OfferDocumentVersionState::Finalized->value,
                    'finalized_by' => $finalizer->getKey(),
                    'finalized_at' => $now,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => $now,
                ]);

                $previousFinalId = $engagement->current_final_version_id;

                if ($previousFinalId !== null && (int) $previousFinalId !== (int) $locked->getKey()) {
                    DB::table('offer_document_versions')
                        ->where('id', $previousFinalId)
                        ->where('version_state', OfferDocumentVersionState::Finalized->value)
                        ->update([
                            'version_state' => OfferDocumentVersionState::Superseded->value,
                            'updated_at' => $now,
                        ]);
                }

                OfferEngagement::query()->whereKey($engagement->getKey())->update([
                    'workflow_state' => OfferWorkflowState::Finalized->value,
                    'current_review_version_id' => null,
                    'current_final_version_id' => $locked->getKey(),
                    'state_changed_by' => $finalizer->getKey(),
                    'state_changed_at' => $now,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => $now,
                ]);

                return OfferDocumentArtifact::query()->findOrFail($lockedArtifact->getKey());
            }, 5);
        } catch (Throwable $exception) {
            DB::table('offer_document_artifacts')
                ->where('id', $artifact->getKey())
                ->where('storage_status', OfferDocumentStorageStatus::Pending->value)
                ->update([
                    'storage_status' => OfferDocumentStorageStatus::Failed->value,
                    'failure_code' => 'render_or_store_failed',
                    'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }

        $this->assertArtifactIntegrity($artifact);

        $this->audit(
            $finalizer,
            'FINALIZE_OFFER_DOCUMENT',
            $lockedVersion,
            "Memfinalkan dokumen penawaran versi {$lockedVersion->version_no}; artifact final #{$artifact->getKey()} diarsipkan.",
        );

        return $artifact;
    }

    private function ensureDraftArtifact(OfferDocumentVersion $version, User $actor): OfferDocumentArtifact
    {
        $artifact = DB::transaction(function () use ($version, $actor): OfferDocumentArtifact {
            $locked = OfferDocumentVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $existing = OfferDocumentArtifact::query()
                ->where('offer_document_version_id', $locked->getKey())
                ->where('artifact_type', OfferDocumentArtifactType::Draft->value)
                ->orderByDesc('artifact_no')
                ->lockForUpdate()
                ->first();

            if ($existing instanceof OfferDocumentArtifact
                && $existing->storage_status === OfferDocumentStorageStatus::Ready) {
                $this->assertArtifactIntegrity($existing);

                return $existing;
            }

            if ($existing instanceof OfferDocumentArtifact
                && $existing->storage_status === OfferDocumentStorageStatus::Pending) {
                throw new DomainException('Pembuatan artifact draft versi ini sedang diproses.');
            }

            $generationKey = hash('sha256', 'offer-draft-v2|'.$locked->getKey().'|'.$locked->snapshot_sha256);

            if ($existing instanceof OfferDocumentArtifact) {
                DB::table('offer_document_artifacts')->where('id', $existing->getKey())->update([
                    'storage_status' => OfferDocumentStorageStatus::Pending->value,
                    'generation_key' => $generationKey,
                    'file_path' => null,
                    'file_size' => null,
                    'sha256' => null,
                    'generated_by' => $actor->getKey(),
                    'generated_at' => null,
                    'failure_code' => null,
                    'failure_message' => null,
                    'updated_at' => now(),
                ]);

                return OfferDocumentArtifact::query()->findOrFail($existing->getKey());
            }

            return OfferDocumentArtifact::query()->create([
                'offer_document_version_id' => $locked->getKey(),
                'artifact_type' => OfferDocumentArtifactType::Draft,
                'artifact_no' => $this->nextArtifactNumber($locked),
                'storage_status' => OfferDocumentStorageStatus::Pending,
                'generation_key' => $generationKey,
                'original_filename' => $this->filename($locked, true),
                'mime_type' => 'application/pdf',
                'renderer_version' => $this->rendererVersion(),
                'generated_by' => $actor->getKey(),
            ]);
        }, 5);

        if ($artifact->storage_status === OfferDocumentStorageStatus::Ready) {
            return $artifact;
        }

        try {
            $pdf = $this->renderer->render((array) $version->data_snapshot, OfferDocumentOutputMode::Draft);
            $path = $this->artifactPath($version, 'submitted-draft.pdf');
            $this->storePdf($path, $pdf);
            $now = now();

            DB::table('offer_document_artifacts')->where('id', $artifact->getKey())->update([
                'storage_status' => OfferDocumentStorageStatus::Ready->value,
                'file_path' => $path,
                'original_filename' => $this->filename($version, true),
                'mime_type' => 'application/pdf',
                'file_size' => strlen($pdf),
                'sha256' => hash('sha256', $pdf),
                'renderer_version' => $this->rendererVersion(),
                'generated_by' => $actor->getKey(),
                'generated_at' => $now,
                'failure_code' => null,
                'failure_message' => null,
                'updated_at' => $now,
            ]);

            return OfferDocumentArtifact::query()->findOrFail($artifact->getKey());
        } catch (Throwable $exception) {
            DB::table('offer_document_artifacts')
                ->where('id', $artifact->getKey())
                ->where('storage_status', OfferDocumentStorageStatus::Pending->value)
                ->update([
                    'storage_status' => OfferDocumentStorageStatus::Failed->value,
                    'failure_code' => 'render_or_store_failed',
                    'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    private function assertStrictPreflight(array $snapshot): void
    {
        $result = $this->preflightValidator->validate($snapshot, OfferPreflightValidator::MODE_PRINT_READY);

        if (($result['errors'] ?? []) !== []) {
            throw new DomainException('Preflight ketat gagal: '.implode(' ', $result['errors']));
        }
    }

    private function assertVersionIntegrity(OfferDocumentVersion $version, bool $approved = false): void
    {
        $snapshot = (array) $version->data_snapshot;
        $actual = $this->snapshotBuilder->hash($snapshot);

        if (! hash_equals((string) $version->snapshot_sha256, $actual)) {
            throw new DomainException('Hash snapshot versi dokumen tidak valid.');
        }

        if ($approved) {
            if (! is_string($version->approved_snapshot_sha256)
                || ! hash_equals($version->snapshot_sha256, $version->approved_snapshot_sha256)) {
                throw new DomainException('Hash snapshot tidak sama dengan snapshot yang disetujui.');
            }

            if ($version->approved_by === null
                || $version->approved_at === null
                || $version->submitted_by === null
                || (int) $version->approved_by === (int) $version->submitted_by
                || $version->approved_draft_artifact_id === null
                || ! is_string($version->approved_render_profile_hash)
                || preg_match('/\A[a-f0-9]{64}\z/i', $version->approved_render_profile_hash) !== 1) {
                throw new DomainException('Metadata persetujuan versi dokumen tidak lengkap, tidak independen, atau tidak valid.');
            }
        }
    }

    private function assertLiveSnapshotUnchanged(OfferDocumentVersion $version): void
    {
        $liveSnapshot = $this->snapshotBuilder->build(Offer::query()->findOrFail($version->offer_id));
        $liveHash = $this->snapshotBuilder->hash($liveSnapshot);

        if (! hash_equals($version->snapshot_sha256, $liveHash)) {
            throw new DomainException('Data penawaran berubah setelah snapshot dibuat; ajukan versi baru.');
        }
    }

    private function readyDraftArtifact(OfferDocumentVersion $version): OfferDocumentArtifact
    {
        $artifact = OfferDocumentArtifact::query()
            ->where('offer_document_version_id', $version->getKey())
            ->where('artifact_type', OfferDocumentArtifactType::Draft->value)
            ->where('storage_status', OfferDocumentStorageStatus::Ready->value)
            ->latest('artifact_no')
            ->first();

        if (! $artifact instanceof OfferDocumentArtifact) {
            throw new DomainException('Artifact draft yang ditinjau belum tersedia.');
        }

        return $artifact;
    }

    private function approvedDraftArtifact(OfferDocumentVersion $version): OfferDocumentArtifact
    {
        $artifactId = $version->approved_draft_artifact_id;

        if ($artifactId === null) {
            throw new DomainException('Artifact draft yang disetujui belum ditetapkan.');
        }

        $artifact = OfferDocumentArtifact::query()
            ->whereKey($artifactId)
            ->where('offer_document_version_id', $version->getKey())
            ->where('artifact_type', OfferDocumentArtifactType::Draft->value)
            ->where('storage_status', OfferDocumentStorageStatus::Ready->value)
            ->first();

        if (! $artifact instanceof OfferDocumentArtifact) {
            throw new DomainException('Artifact draft yang disetujui tidak tersedia atau tidak sesuai dengan versi dokumen.');
        }

        return $artifact;
    }

    private function assertFinalArtifactProvenance(
        OfferDocumentVersion $version,
        OfferDocumentArtifact $finalArtifact,
        OfferDocumentArtifact $approvedDraft,
    ): void {
        $expectedGenerationKey = hash('sha256', implode('|', [
            'offer-final-v2',
            $version->getKey(),
            $version->approved_snapshot_sha256,
            $version->approved_render_profile_hash,
            $approvedDraft->sha256,
        ]));

        if ((int) $finalArtifact->source_draft_artifact_id !== (int) $approvedDraft->getKey()
            || ! is_string($finalArtifact->generation_key)
            || ! hash_equals($expectedGenerationKey, $finalArtifact->generation_key)
            || ! hash_equals(
                $this->snapshotRendererVersion((array) $version->data_snapshot),
                (string) $finalArtifact->renderer_version,
            )) {
            throw new DomainException('Provenance artifact final tidak sesuai dengan snapshot dan draft yang disetujui.');
        }
    }

    private function assertDraftArtifactProvenance(
        OfferDocumentVersion $version,
        OfferDocumentArtifact $artifact,
    ): void {
        $expectedGenerationKey = hash(
            'sha256',
            'offer-draft-v2|'.$version->getKey().'|'.$version->snapshot_sha256,
        );

        if (! is_string($artifact->generation_key)
            || ! hash_equals($expectedGenerationKey, $artifact->generation_key)
            || ! hash_equals(
                $this->snapshotRendererVersion((array) $version->data_snapshot),
                (string) $artifact->renderer_version,
            )) {
            throw new DomainException('Provenance artifact draft tidak sesuai dengan snapshot versi dokumen.');
        }
    }

    private function assertCurrentRendererProfile(array $snapshot): void
    {
        $profile = data_get($snapshot, 'metadata.renderer_profile');

        if (! is_array($profile)) {
            throw new DomainException('Profil renderer tidak tersedia pada snapshot dokumen.');
        }

        foreach ([
            'engine' => 'engine',
            'version' => 'version',
            'paper' => 'paper',
            'orientation' => 'orientation',
        ] as $profileKey => $configKey) {
            $approvedValue = $profile[$profileKey] ?? null;
            $currentValue = config("offer-documents.renderer.{$configKey}");

            if (! is_scalar($approvedValue)
                || ! is_scalar($currentValue)
                || (string) $approvedValue !== (string) $currentValue) {
                throw new DomainException('Konfigurasi renderer berubah setelah snapshot dibuat; ajukan versi baru.');
            }
        }
    }

    private function snapshotRendererVersion(array $snapshot): string
    {
        $engine = data_get($snapshot, 'metadata.renderer_profile.engine');
        $version = data_get($snapshot, 'metadata.renderer_profile.version');

        if (! is_scalar($engine) || ! is_scalar($version)
            || trim((string) $engine) === '' || trim((string) $version) === '') {
            throw new DomainException('Versi renderer tidak tersedia pada snapshot dokumen.');
        }

        return trim((string) $engine).'-'.trim((string) $version);
    }

    public function assertArtifactIntegrity(OfferDocumentArtifact $artifact): void
    {
        $this->readArtifact($artifact);
    }

    public function readArtifact(OfferDocumentArtifact $artifact): string
    {
        $version = $artifact->relationLoaded('version')
            ? $artifact->getRelation('version')
            : $artifact->version()->first();
        $expectedFilename = $artifact->artifact_type === OfferDocumentArtifactType::Final
            ? 'final.pdf'
            : 'submitted-draft.pdf';
        $expectedPath = $version instanceof OfferDocumentVersion
            ? $this->artifactPath($version, $expectedFilename)
            : null;

        if ($artifact->artifact_type === OfferDocumentArtifactType::Final
            && $version instanceof OfferDocumentVersion) {
            if (! in_array($version->version_state, [
                OfferDocumentVersionState::Finalized,
                OfferDocumentVersionState::Superseded,
            ], true)) {
                throw new DomainException('Status versi untuk artifact final tidak valid.');
            }

            $this->assertVersionIntegrity($version, true);
            $approvedDraft = $this->approvedDraftArtifact($version);
            $this->readArtifact($approvedDraft);
            $this->assertFinalArtifactProvenance($version, $artifact, $approvedDraft);
        } elseif ($artifact->artifact_type === OfferDocumentArtifactType::Draft
            && $version instanceof OfferDocumentVersion) {
            $this->assertDraftArtifactProvenance($version, $artifact);
        }

        if ($artifact->storage_status !== OfferDocumentStorageStatus::Ready
            || ! $version instanceof OfferDocumentVersion
            || ! is_string($artifact->file_path)
            || ! is_string($expectedPath)
            || ! hash_equals($expectedPath, $artifact->file_path)
            || ! is_string($artifact->sha256)
            || preg_match('/\A[a-f0-9]{64}\z/i', $artifact->sha256) !== 1
            || $artifact->mime_type !== 'application/pdf'
            || ! is_string($artifact->renderer_version)
            || trim($artifact->renderer_version) === ''
            || $artifact->generated_at === null
            || ! Storage::disk(self::ARCHIVE_DISK)->exists($artifact->file_path)) {
            throw new DomainException('Artifact dokumen tidak tersedia atau belum siap.');
        }

        $contents = Storage::disk(self::ARCHIVE_DISK)->get($artifact->file_path);

        if (! str_starts_with($contents, '%PDF-')
            || ! hash_equals(mb_strtolower($artifact->sha256), hash('sha256', $contents))
            || (int) $artifact->file_size !== strlen($contents)) {
            throw new DomainException('Integritas artifact dokumen tidak valid.');
        }

        return $contents;
    }

    private function storePdf(string $path, string $pdf): void
    {
        if (! str_starts_with($pdf, '%PDF-')
            || ! Storage::disk(self::ARCHIVE_DISK)->put($path, $pdf)) {
            throw new DomainException('Artifact PDF gagal disimpan pada penyimpanan privat.');
        }
    }

    private function nextArtifactNumber(OfferDocumentVersion $version): int
    {
        return ((int) OfferDocumentArtifact::query()
            ->where('offer_document_version_id', $version->getKey())
            ->max('artifact_no')) + 1;
    }

    private function renderProfileHash(array $snapshot): string
    {
        return $this->snapshotBuilder->hash([
            'renderer_profile' => $snapshot['metadata']['renderer_profile'] ?? [],
        ]);
    }

    private function rendererVersion(): string
    {
        return trim((string) config('offer-documents.renderer.engine', 'dompdf'))
            .'-'.trim((string) config('offer-documents.renderer.version', 'unknown'));
    }

    private function artifactPath(OfferDocumentVersion $version, string $filename): string
    {
        return 'offer-documents/'.$version->offer_id.'/versions/'.$version->version_no.'/'.$filename;
    }

    private function filename(OfferDocumentVersion $version, bool $draft): string
    {
        $number = (string) data_get($version->data_snapshot, 'document.number', $version->offer_id);
        $number = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F\x7F]+/u', '-', $number) ?? '';
        $number = trim(mb_substr($number, 0, 150), ' .-');
        $suffix = $draft ? '-DRAFT' : '';

        return 'Penawaran-'.($number !== '' ? $number : $version->offer_id)."-v{$version->version_no}{$suffix}.pdf";
    }

    private function audit(
        User $actor,
        string $action,
        OfferDocumentVersion $version,
        string $description,
    ): void {
        ActivityLog::query()->create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'model_type' => 'OfferDocumentVersion',
            'model_id' => $version->getKey(),
            'description' => $description,
        ]);
    }
}
