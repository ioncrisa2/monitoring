<?php

namespace App\Enums;

enum OfferTemplateBlockType: string
{
    case Text = 'text';
    case Bullets = 'bullets';
    case Dynamic = 'dynamic';
    case AssetList = 'asset_list';
    case FeeSummary = 'fee_summary';
    case FeeTable = 'fee_table';
    case PaymentTerms = 'payment_terms';
    case Requirements = 'requirements';
    case ExposureTable = 'exposure_table';
}
