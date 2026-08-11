<?php

namespace App\Models;

use App\Enums\OfferDocumentArtifactType;
use App\Enums\OfferDocumentStorageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferDocumentArtifact extends Model
{
    protected $fillable = [
        'offer_document_version_id',
        'artifact_type',
        'artifact_no',
        'final_slot',
        'storage_status',
        'generation_key',
        'source_draft_artifact_id',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'sha256',
        'renderer_version',
        'generated_by',
        'generated_at',
        'failure_code',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'artifact_type' => OfferDocumentArtifactType::class,
            'artifact_no' => 'integer',
            'final_slot' => 'integer',
            'storage_status' => OfferDocumentStorageStatus::class,
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(OfferDocumentVersion::class, 'offer_document_version_id');
    }

    public function sourceDraftArtifact(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_draft_artifact_id');
    }

    public function derivedArtifacts(): HasMany
    {
        return $this->hasMany(self::class, 'source_draft_artifact_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
