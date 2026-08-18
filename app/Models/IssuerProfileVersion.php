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
        'effective_from',
        'effective_until',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
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

    public function documentVersions(): HasMany
    {
        return $this->hasMany(OfferDocumentVersion::class, 'issuer_profile_version_id');
    }
}
