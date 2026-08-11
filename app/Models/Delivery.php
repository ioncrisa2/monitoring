<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'sent_date',
        'courier',
        'tracking_no',
        'received_date',
        'recipient_name',
        'note',
    ];

    protected $casts = [
        'sent_date' => 'date',
        'received_date' => 'date',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
