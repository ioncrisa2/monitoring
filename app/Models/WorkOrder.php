<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'contract_no',
        'contract_date',
        'survey_required',
        'sla_date',
        'current_status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'sla_date' => 'date',
        'survey_required' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkOrderAssignment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class)->latest();
    }

    public function surveyors(): HasMany
    {
        return $this->hasMany(WorkOrderAssignment::class)->where('role_type', 'surveyor');
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(WorkOrderAssignment::class)->where('role_type', 'reviewer');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(WorkOrderAsset::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest();
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->sla_date || in_array($this->current_status, ['SELESAI', 'BATAL'])) {
            return false;
        }

        return Carbon::today()->greaterThan($this->sla_date);
    }

    public function getAgingDaysAttribute(): int
    {
        $lastHistory = $this->statusHistories()->first();
        $date = $lastHistory ? $lastHistory->created_at : $this->created_at;

        return (int) Carbon::now()->diffInDays($date);
    }
}
