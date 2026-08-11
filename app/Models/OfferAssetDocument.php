<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferAssetDocument extends Model
{
    protected $fillable = [
        'offer_asset_id',
        'document_type',
        'document_no',
        'issued_at',
        'issuer',
        'primary_slot',
        'sort_order',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'primary_slot' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(OfferAsset::class, 'offer_asset_id');
    }

    public function isPrimary(): bool
    {
        return $this->primary_slot === 1;
    }
}
