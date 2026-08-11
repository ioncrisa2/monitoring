<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferAsset extends Model
{
    protected $fillable = [
        'offer_subject_id',
        'asset_type',
        'description',
        'address',
        'city',
        'province',
        'land_area_m2',
        'building_area_m2',
        'inspection_note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'land_area_m2' => 'decimal:2',
            'building_area_m2' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(OfferSubject::class, 'offer_subject_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OfferAssetDocument::class)->orderBy('sort_order');
    }

    public function feeItems(): HasMany
    {
        return $this->hasMany(OfferFeeItem::class);
    }
}
