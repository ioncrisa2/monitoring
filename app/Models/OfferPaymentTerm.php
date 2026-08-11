<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferPaymentTerm extends Model
{
    protected $fillable = [
        'offer_id',
        'sequence',
        'percentage_bps',
        'trigger_text',
        'due_days',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'percentage_bps' => 'integer',
            'due_days' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
