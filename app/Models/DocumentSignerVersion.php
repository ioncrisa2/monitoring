<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSignerVersion extends Model
{
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
        'checksum',
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
        return $this->hasMany(OfferDocumentVersion::class, 'signer_version_id');
    }
}
