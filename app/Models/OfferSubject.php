<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferSubject extends Model
{
    protected $fillable = [
        'offer_id',
        'debtor_id',
        'name_snapshot',
        'identifier_snapshot',
        'address_snapshot',
        'primary_slot',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'primary_slot' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Debtor::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(OfferAsset::class)->orderBy('sort_order');
    }

    public function feeItems(): HasMany
    {
        return $this->hasMany(OfferFeeItem::class);
    }

    public function isPrimary(): bool
    {
        return $this->primary_slot === 1;
    }
}
