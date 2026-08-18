<?php

namespace App\Services\Offers;

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

    public function approve(Model $master, User $approver): Model
    {
        if (! $master->exists || ! $approver->exists) {
            throw new DomainException('Master dan pengguna penyetuju harus sudah tersimpan.');
        }

        $this->assertSupported($master);

        return DB::transaction(function () use ($master, $approver): Model {
            /** @var Model $locked */
            $locked = $master->newQuery()->lockForUpdate()->findOrFail($master->getKey());

            if ($locked->getAttribute('status') === 'approved') {
                $this->integrity->assertApprovedIntegrity($locked);

                return $locked;
            }

            if ($locked->getAttribute('status') !== 'draft') {
                throw new DomainException('Hanya master berstatus draft yang dapat disetujui.');
            }

            $this->integrity->assertContentValid($locked);
            $approvedAt = now();
            $updated = DB::table($locked->getTable())
                ->where($locked->getKeyName(), $locked->getKey())
                ->where('status', 'draft')
                ->update([
                    'status' => 'approved',
                    'checksum' => $this->integrity->checksum($locked),
                    'approved_by' => $approver->getKey(),
                    'approved_at' => $approvedAt,
                    'updated_at' => $approvedAt,
                ]);

            if ($updated !== 1) {
                throw new DomainException('Status master berubah saat proses persetujuan; muat ulang data lalu coba lagi.');
            }

            /** @var Model $approved */
            $approved = $locked->newQuery()->findOrFail($locked->getKey());
            $this->integrity->assertApprovedIntegrity($approved);

            return $approved;
        });
    }

    public function retire(Model $master, User $actor, string $reason): Model
    {
        if (! $master->exists || ! $actor->exists) {
            throw new DomainException('Master dan pengguna yang melakukan retirement harus sudah tersimpan.');
        }

        $this->assertSupported($master);
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new DomainException('Alasan retirement wajib diisi dan maksimal 1000 karakter.');
        }

        return DB::transaction(function () use ($master, $actor, $reason): Model {
            /** @var Model $locked */
            $locked = $master->newQuery()->lockForUpdate()->findOrFail($master->getKey());

            if ($locked->getAttribute('status') !== 'approved') {
                throw new DomainException('Hanya master berstatus approved yang dapat di-retire.');
            }

            $this->integrity->assertApprovedIntegrity($locked);
            $checksum = (string) $locked->getAttribute('checksum');
            $retiredAt = now();
            $updated = DB::table($locked->getTable())
                ->where($locked->getKeyName(), $locked->getKey())
                ->where('status', 'approved')
                ->where('checksum', $checksum)
                ->update([
                    'status' => 'retired',
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

            ActivityLog::create([
                'user_id' => $actor->getKey(),
                'action' => 'RETIRE_MASTER',
                'model_type' => class_basename($retired),
                'model_id' => $retired->getKey(),
                'description' => sprintf(
                    'Master %s #%s diubah dari approved menjadi retired oleh %s (user #%s). Alasan: %s',
                    class_basename($retired),
                    $retired->getKey(),
                    $actor->name,
                    $actor->getKey(),
                    $reason,
                ),
            ]);

            return $retired;
        });
    }

    private function assertSupported(Model $master): void
    {
        if (! $master instanceof OfferTemplateVersion
            && ! $master instanceof IssuerProfileVersion
            && ! $master instanceof DocumentSignerVersion) {
            throw new DomainException('Jenis master dokumen tidak didukung untuk approval.');
        }
    }
}
