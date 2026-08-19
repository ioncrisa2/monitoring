<?php

namespace App\Services\Offers;

use App\Enums\OfferDocumentMasterReviewStatus;
use App\Models\ActivityLog;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\OfferTemplateVersion;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class OfferDocumentMasterApprovalService
{
    public function __construct(
        private readonly OfferDocumentMasterIntegrityService $integrity,
    ) {}

    /** Submit a complete draft into the immutable review queue. */
    public function submit(Model $master, User $submitter): Model
    {
        $this->assertPersisted($master, $submitter);
        $this->assertSupported($master);

        return DB::transaction(function () use ($master, $submitter): Model {
            /** @var Model $locked */
            $locked = $master->newQuery()->lockForUpdate()->findOrFail($master->getKey());

            if ($locked->getAttribute('status') !== OfferDocumentMasterReviewStatus::Draft->value) {
                throw new DomainException('Hanya master berstatus draft yang dapat diajukan untuk review.');
            }

            $this->integrity->assertApprovable($locked);
            $submittedAt = now();
            $updated = DB::table($locked->getTable())
                ->where($locked->getKeyName(), $locked->getKey())
                ->where('status', OfferDocumentMasterReviewStatus::Draft->value)
                ->update([
                    'status' => OfferDocumentMasterReviewStatus::Submitted->value,
                    'checksum' => $this->integrity->checksum($locked),
                    'created_by' => $locked->getAttribute('created_by') ?: $submitter->getKey(),
                    'submitted_by' => $submitter->getKey(),
                    'submitted_at' => $submittedAt,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_note' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'updated_at' => $submittedAt,
                ]);

            if ($updated !== 1) {
                throw new DomainException('Status master berubah saat proses pengajuan; muat ulang data lalu coba lagi.');
            }

            /** @var Model $submitted */
            $submitted = $locked->newQuery()->findOrFail($locked->getKey());

            if (! $this->integrity->verify($submitted)) {
                throw new DomainException('Checksum master berubah saat proses pengajuan.');
            }

            $this->log($submitted, $submitter, 'SUBMIT_MASTER', 'draft', 'submitted');

            return $submitted;
        });
    }

    /** Approve a submitted v2 master after separation-of-duties checks. */
    public function approve(Model $master, User $approver): Model
    {
        return $this->approveInternal($master, $approver, false);
    }

    /**
     * Explicit compatibility hook for existing schema-v1 fixtures/imports.
     * Application UI must never expose this operation for newly authored data.
     */
    public function approveLegacy(Model $master, User $approver): Model
    {
        if ($master instanceof OfferTemplateVersion
            && ($master->schema_version !== 1 || $master->layout_version !== 'standard-v1')) {
            throw new DomainException('Approval legacy hanya mendukung schema_version 1 dan layout standard-v1.');
        }

        return $this->approveInternal($master, $approver, true);
    }

    public function reject(Model $master, User $reviewer, string $reason): Model
    {
        $this->assertPersisted($master, $reviewer);
        $this->assertSupported($master);
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new DomainException('Alasan penolakan wajib diisi dan maksimal 1000 karakter.');
        }

        return DB::transaction(function () use ($master, $reviewer, $reason): Model {
            /** @var Model $locked */
            $locked = $master->newQuery()->lockForUpdate()->findOrFail($master->getKey());

            if ($locked->getAttribute('status') !== OfferDocumentMasterReviewStatus::Submitted->value) {
                throw new DomainException('Hanya master yang sudah diajukan yang dapat ditolak.');
            }

            $this->assertIndependentReviewer($locked, $reviewer);

            if (! $this->integrity->verify($locked)) {
                throw new DomainException('Konten master berubah setelah diajukan; review tidak dapat dilanjutkan.');
            }

            $reviewedAt = now();
            $updated = DB::table($locked->getTable())
                ->where($locked->getKeyName(), $locked->getKey())
                ->where('status', OfferDocumentMasterReviewStatus::Submitted->value)
                ->where('checksum', $locked->getAttribute('checksum'))
                ->update([
                    'status' => OfferDocumentMasterReviewStatus::Rejected->value,
                    'reviewed_by' => $reviewer->getKey(),
                    'reviewed_at' => $reviewedAt,
                    'rejection_note' => $reason,
                    'approved_by' => null,
                    'approved_at' => null,
                    'updated_at' => $reviewedAt,
                ]);

            if ($updated !== 1) {
                throw new DomainException('Status master berubah saat proses penolakan; muat ulang data lalu coba lagi.');
            }

            /** @var Model $rejected */
            $rejected = $locked->newQuery()->findOrFail($locked->getKey());

            if (! $this->integrity->verify($rejected)) {
                throw new DomainException('Konten atau checksum master berubah saat proses penolakan.');
            }

            $this->log($rejected, $reviewer, 'REJECT_MASTER', 'submitted', 'rejected', $reason);

            return $rejected;
        });
    }

    public function retire(Model $master, User $actor, string $reason): Model
    {
        $this->assertPersisted($master, $actor);
        $this->assertSupported($master);
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new DomainException('Alasan retirement wajib diisi dan maksimal 1000 karakter.');
        }

        return DB::transaction(function () use ($master, $actor, $reason): Model {
            /** @var Model $locked */
            $locked = $master->newQuery()->lockForUpdate()->findOrFail($master->getKey());

            if ($locked->getAttribute('status') !== OfferDocumentMasterReviewStatus::Approved->value) {
                throw new DomainException('Hanya master berstatus approved yang dapat di-retire.');
            }

            $this->integrity->assertApprovedIntegrity($locked);
            $checksum = (string) $locked->getAttribute('checksum');
            $retiredAt = now();
            $updated = DB::table($locked->getTable())
                ->where($locked->getKeyName(), $locked->getKey())
                ->where('status', OfferDocumentMasterReviewStatus::Approved->value)
                ->where('checksum', $checksum)
                ->update([
                    'status' => OfferDocumentMasterReviewStatus::Retired->value,
                    'updated_at' => $retiredAt,
                ]);

            if ($updated !== 1) {
                throw new DomainException('Status master berubah saat proses retirement; muat ulang data lalu coba lagi.');
            }

            /** @var Model $retired */
            $retired = $locked->newQuery()->findOrFail($locked->getKey());

            if (! hash_equals($checksum, (string) $retired->getAttribute('checksum'))
                || ! $this->integrity->verify($retired)) {
                throw new DomainException('Konten atau checksum master berubah saat proses retirement.');
            }

            $this->log($retired, $actor, 'RETIRE_MASTER', 'approved', 'retired', $reason);

            return $retired;
        });
    }

    private function approveInternal(Model $master, User $approver, bool $legacy): Model
    {
        $this->assertPersisted($master, $approver);
        $this->assertSupported($master);

        return DB::transaction(function () use ($master, $approver, $legacy): Model {
            /** @var Model $locked */
            $locked = $master->newQuery()->lockForUpdate()->findOrFail($master->getKey());
            $expectedStatus = $legacy
                ? OfferDocumentMasterReviewStatus::Draft->value
                : OfferDocumentMasterReviewStatus::Submitted->value;

            if ($locked->getAttribute('status') === OfferDocumentMasterReviewStatus::Approved->value) {
                $this->integrity->assertApprovedIntegrity($locked);

                return $locked;
            }

            if ($locked->getAttribute('status') !== $expectedStatus) {
                $message = $legacy
                    ? 'Approval legacy hanya dapat dijalankan untuk master draft.'
                    : 'Master wajib diajukan untuk review sebelum dapat disetujui.';
                throw new DomainException($message);
            }

            if (! $legacy) {
                $this->assertIndependentReviewer($locked, $approver);

                if (! $this->integrity->verify($locked)) {
                    throw new DomainException('Konten master berubah setelah diajukan; approval tidak dapat dilanjutkan.');
                }
            }

            $this->integrity->assertApprovable($locked, $legacy);
            $approvedAt = now();
            $checksum = $this->integrity->checksum($locked);
            $values = [
                'status' => OfferDocumentMasterReviewStatus::Approved->value,
                'checksum' => $checksum,
                'approved_by' => $approver->getKey(),
                'approved_at' => $approvedAt,
                'updated_at' => $approvedAt,
            ];

            if (! $legacy) {
                $values['reviewed_by'] = $approver->getKey();
                $values['reviewed_at'] = $approvedAt;
                $values['rejection_note'] = null;
            }

            $query = DB::table($locked->getTable())
                ->where($locked->getKeyName(), $locked->getKey())
                ->where('status', $expectedStatus);

            if (! $legacy) {
                $query->where('checksum', $locked->getAttribute('checksum'));
            }

            if ($query->update($values) !== 1) {
                throw new DomainException('Status master berubah saat proses persetujuan; muat ulang data lalu coba lagi.');
            }

            /** @var Model $approved */
            $approved = $locked->newQuery()->findOrFail($locked->getKey());
            $this->integrity->assertApprovedIntegrity($approved);
            $this->log($approved, $approver, 'APPROVE_MASTER', $expectedStatus, 'approved');

            return $approved;
        });
    }

    private function assertIndependentReviewer(Model $master, User $reviewer): void
    {
        $reviewerId = (int) $reviewer->getKey();
        $creatorId = $master->getAttribute('created_by');
        $submitterId = $master->getAttribute('submitted_by');

        if (($creatorId !== null && (int) $creatorId === $reviewerId)
            || ($submitterId !== null && (int) $submitterId === $reviewerId)) {
            throw new DomainException('Pembuat atau pengaju master tidak boleh mereview master yang sama.');
        }
    }

    private function assertPersisted(Model $master, User $actor): void
    {
        if (! $master->exists || ! $actor->exists) {
            throw new DomainException('Master dan pengguna pelaksana harus sudah tersimpan.');
        }
    }

    private function assertSupported(Model $master): void
    {
        if (! $master instanceof OfferTemplateVersion
            && ! $master instanceof IssuerProfileVersion
            && ! $master instanceof DocumentSignerVersion) {
            throw new DomainException('Jenis master dokumen tidak didukung untuk workflow approval.');
        }
    }

    private function log(
        Model $master,
        User $actor,
        string $action,
        string $from,
        string $to,
        ?string $reason = null,
    ): void {
        ActivityLog::create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'model_type' => class_basename($master),
            'model_id' => $master->getKey(),
            'description' => sprintf(
                'Master %s #%s diubah dari %s menjadi %s oleh %s (user #%s).%s',
                class_basename($master),
                $master->getKey(),
                $from,
                $to,
                $actor->name,
                $actor->getKey(),
                $reason === null ? '' : ' Alasan: '.$reason,
            ),
        ]);
    }
}
