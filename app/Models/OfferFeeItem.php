<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferFeeItem extends Model
{
    protected $fillable = [
        'offer_id',
        'offer_subject_id',
        'offer_asset_id',
        'label',
        'quantity',
        'unit_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(OfferSubject::class, 'offer_subject_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(OfferAsset::class, 'offer_asset_id');
    }
}
