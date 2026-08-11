<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportStaging extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'branch_code',
        'offer_no',
        'contract_date',
        'debtor_name',
        'client_name',
        'report_user_name',
        'fee',
        'ta',
        'status',
        'report_no',
        'report_date',
        'resume_value',
        'report_value',
        'sent_date',
        'courier',
        'tracking_no',
        'received_date',
        'recipient_name',
        'is_processed',
        'error_message',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'report_date' => 'date',
        'sent_date' => 'date',
        'received_date' => 'date',
        'fee' => 'decimal:2',
        'ta' => 'decimal:2',
        'resume_value' => 'decimal:2',
        'report_value' => 'decimal:2',
        'is_processed' => 'boolean',
    ];
}
