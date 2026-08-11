<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_no',
        'offer_date',
        'branch_id',
        'debtor_id',
        'client_id',
        'report_user_id',
        'fee',
        'ta',
        'dpp',
        'ppn',
        'pph',
        'outcome',
        'note',
        'created_by',
    ];

    protected $casts = [
        'offer_date' => 'date',
        'fee' => 'decimal:2',
        'ta' => 'decimal:2',
        'dpp' => 'decimal:2',
        'ppn' => 'decimal:2',
        'pph' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Debtor::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'client_id');
    }

    public function reportUser(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'report_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }
}
