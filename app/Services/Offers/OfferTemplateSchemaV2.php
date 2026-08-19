<?php

namespace App\Services\Offers;

final class OfferTemplateSchemaV2
{
    public const SCHEMA_VERSION = 2;

    public const LAYOUT_VERSION = 'offer-a4-v2';

    public const HEADER_MODE = 'all_pages';

    public const ROOT_KEYS = ['document', 'defaults', 'clauses', 'constraints'];

    public const DOCUMENT_KEYS = ['opening', 'closing'];

    public const DEFAULT_KEYS = [
        'subject',
        'ownership_form',
        'currency',
        'purpose',
        'valuation_basis',
        'investigation_level',
        'report_format',
        'report_language',
        'report_copies',
        'completion_days',
        'completion_day_type',
        'tax_inclusion',
        'ppn_rate_bps',
        'pph_rate_bps',
        'fee_presentation',
        'cost_inclusions',
        'special_assumptions',
        'payment_terms',
        'requirements',
    ];

    public const CONSTRAINT_KEYS = [
        'required_engagement_fields',
        'purpose_must_equal',
        'valuation_basis_must_equal',
        'required_asset_document',
        'require_fee_per_asset',
        'requires_liquidation_value',
        'requires_exposure_table',
    ];

    public const REQUIRED_ENGAGEMENT_FIELDS = [
        'recipient_organization',
        'recipient_address',
        'recipient_city',
        'issue_city',
        'subject',
        'ownership_form',
        'currency',
        'purpose',
        'valuation_basis',
        'valuation_date_or_rule',
        'investigation_level',
        'report_format',
        'report_language',
        'report_copies',
        'completion_days',
        'completion_day_type',
        'tax_inclusion',
        'fee_presentation',
    ];

    /** Tokens are resolved by the renderer; templates cannot introduce data paths. */
    public const TOKENS = [
        'document.number',
        'document.place',
        'document.date',
        'document.subject',
        'recipient.name',
        'recipient.attention',
        'recipient.address',
        'issuer.name',
        'issuer.address',
        'issuer.phone',
        'issuer.email',
        'issuer.permit_no',
        'request.reference_no',
        'request.reference_date',
        'engagement.ownership_form',
        'engagement.currency',
        'engagement.purpose',
        'engagement.valuation_basis',
        'engagement.valuation_date',
        'engagement.investigation_level',
        'engagement.report_format',
        'engagement.report_language',
        'engagement.report_copies',
        'engagement.completion_days',
        'engagement.completion_day_type',
        'engagement.special_assumptions',
        'commercial.quoted_amount',
        'commercial.document_payable_total',
        'commercial.amount_in_words',
        'signer.full_name',
        'signer.position',
        'signer.permit_no',
        'signer.registration_no',
    ];

    private function __construct() {}
}
