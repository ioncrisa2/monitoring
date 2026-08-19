<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableOfferDocumentApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSignerVersion extends Model
{
    use HasImmutableOfferDocumentApproval;

    protected $hidden = [
        'signature_path',
        'signature_sha256',
        'signature_mime',
        'stamp_path',
        'stamp_sha256',
        'stamp_mime',
    ];

    protected $fillable = [
        'branch_id',
        'signer_key',
        'version_no',
        'full_name',
        'title_suffix',
        'position',
        'permit_no',
        'registration_no',
        'phone',
        'email',
        'effective_from',
        'effective_until',
        'status',
        'created_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_note',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(OfferDocumentVersion::class, 'signer_version_id');
    }
}
