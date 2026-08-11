<?php

namespace App\Models;

use App\Enums\OfferTaxInclusion;
use App\Enums\OfferWorkflowState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferEngagement extends Model
{
    protected $fillable = [
        'offer_id',
        'workflow_state',
        'current_review_version_id',
        'current_final_version_id',
        'state_changed_by',
        'state_changed_at',
        'lock_version',
        'template_version_id',
        'issuer_profile_version_id',
        'signer_version_id',
        'issue_city',
        'recipient_attention',
        'recipient_organization',
        'recipient_address',
        'recipient_city',
        'subject',
        'request_reference_type',
        'request_reference_no',
        'request_reference_date',
        'opening_context',
        'ownership_form',
        'currency',
        'purpose',
        'valuation_basis',
        'valuation_date',
        'valuation_date_rule',
        'investigation_level',
        'report_format',
        'report_language',
        'report_copies',
        'completion_days',
        'completion_day_type',
        'tax_inclusion',
        'ppn_rate_bps',
        'pph_rate_bps',
        'cost_inclusions',
        'special_assumptions',
        'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'workflow_state' => OfferWorkflowState::class,
            'state_changed_at' => 'datetime',
            'lock_version' => 'integer',
            'request_reference_date' => 'date',
            'valuation_date' => 'date',
            'report_copies' => 'integer',
            'completion_days' => 'integer',
            'tax_inclusion' => OfferTaxInclusion::class,
            'ppn_rate_bps' => 'integer',
            'pph_rate_bps' => 'integer',
            'cost_inclusions' => 'array',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function currentReviewVersion(): BelongsTo
    {
        return $this->belongsTo(OfferDocumentVersion::class, 'current_review_version_id');
    }

    public function currentFinalVersion(): BelongsTo
    {
        return $this->belongsTo(OfferDocumentVersion::class, 'current_final_version_id');
    }

    public function stateChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'state_changed_by');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OfferTemplateVersion::class, 'template_version_id');
    }

    public function issuerProfileVersion(): BelongsTo
    {
        return $this->belongsTo(IssuerProfileVersion::class);
    }

    public function signerVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentSignerVersion::class, 'signer_version_id');
    }
}
