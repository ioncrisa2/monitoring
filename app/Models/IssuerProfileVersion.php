<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableOfferDocumentApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssuerProfileVersion extends Model
{
    use HasImmutableOfferDocumentApproval;

    protected $fillable = [
        'branch_id',
        'version_no',
        'legal_name',
        'permit_no',
        'office_label',
        'address',
        'city',
        'phone',
        'email',
        'letterhead_path',
        'letterhead_sha256',
        'letterhead_mime',
        'letterhead_width_px',
        'letterhead_height_px',
        'letterhead_size_bytes',
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
            'letterhead_width_px' => 'integer',
            'letterhead_height_px' => 'integer',
            'letterhead_size_bytes' => 'integer',
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
        return $this->hasMany(OfferDocumentVersion::class, 'issuer_profile_version_id');
    }
}
