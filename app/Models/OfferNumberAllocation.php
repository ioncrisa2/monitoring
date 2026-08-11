<?php

namespace App\Models;

use App\Enums\OfferNumberAllocationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferNumberAllocation extends Model
{
    protected $fillable = [
        'offer_id',
        'branch_id',
        'scope_key',
        'sequence_year',
        'sequence_no',
        'number_suffix',
        'format_snapshot',
        'full_number',
        'status',
        'active_slot',
        'allocated_by',
        'allocated_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'sequence_year' => 'integer',
            'sequence_no' => 'integer',
            'format_snapshot' => 'array',
            'status' => OfferNumberAllocationStatus::class,
            'active_slot' => 'integer',
            'allocated_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
