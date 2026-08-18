<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableOfferDocumentApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferTemplateVersion extends Model
{
    use HasImmutableOfferDocumentApproval;

    protected $fillable = [
        'offer_template_id',
        'version_no',
        'schema_version',
        'clause_schema',
        'condition_schema',
        'layout_version',
        'header_mode',
        'status',
        'effective_from',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'schema_version' => 'integer',
            'clause_schema' => 'array',
            'condition_schema' => 'array',
            'effective_from' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OfferTemplate::class, 'offer_template_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(OfferDocumentVersion::class, 'template_version_id');
    }
}
