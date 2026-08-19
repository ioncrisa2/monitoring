<?php

namespace App\Models;

use App\Enums\OfferDocumentVersionState;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferDocumentVersion extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            foreach ([
                'offer_id',
                'version_no',
                'template_version_id',
                'issuer_profile_version_id',
                'signer_version_id',
                'data_snapshot',
                'snapshot_sha256',
                'submitted_by',
                'submitted_at',
                'supersedes_id',
            ] as $immutableAttribute) {
                if ($version->isDirty($immutableAttribute)) {
                    throw new DomainException('Snapshot versi dokumen bersifat immutable; buat versi baru untuk perubahan.');
                }
            }

            if (in_array($version->getRawOriginal('version_state'), [
                OfferDocumentVersionState::Finalized->value,
                OfferDocumentVersionState::Superseded->value,
                OfferDocumentVersionState::Void->value,
            ], true)) {
                throw new DomainException('Versi dokumen yang telah ditutup bersifat immutable.');
            }

            throw new DomainException('Arsip versi dokumen hanya dapat berubah melalui layanan workflow resmi.');
        });

        static::deleting(function (): void {
            throw new DomainException('Arsip versi dokumen tidak dapat dihapus.');
        });
    }

    protected $fillable = [
        'offer_id',
        'version_no',
        'version_state',
        'template_version_id',
        'issuer_profile_version_id',
        'signer_version_id',
        'data_snapshot',
        'snapshot_sha256',
        'approved_snapshot_sha256',
        'approved_draft_artifact_id',
        'approved_render_profile_hash',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'finalized_by',
        'finalized_at',
        'supersedes_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'version_state' => OfferDocumentVersionState::class,
            'data_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OfferTemplateVersion::class, 'template_version_id');
    }

    public function issuerProfileVersion(): BelongsTo
    {
        return $this->belongsTo(IssuerProfileVersion::class);
    }

    public function signerVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentSignerVersion::class, 'signer_version_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(OfferDocumentArtifact::class)->orderBy('artifact_no');
    }

    public function approvedDraftArtifact(): BelongsTo
    {
        return $this->belongsTo(OfferDocumentArtifact::class, 'approved_draft_artifact_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }
}
