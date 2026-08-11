<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'report_no',
        'report_date',
        'purpose',
        'resume_value',
        'report_value',
        'print_date',
        'final_at',
        'created_by',
    ];

    protected $casts = [
        'report_date' => 'date',
        'print_date' => 'date',
        'final_at' => 'datetime',
        'resume_value' => 'decimal:2',
        'report_value' => 'decimal:2',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(WorkOrderAsset::class, 'report_assets');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
