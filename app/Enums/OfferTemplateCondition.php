<?php

namespace App\Enums;

enum OfferTemplateCondition: string
{
    case HasRequestReference = 'has_request_reference';
    case HasMultipleAssets = 'has_multiple_assets';
    case HasSpecialAssumptions = 'has_special_assumptions';
    case TaxIncluded = 'tax_included';
    case TaxExcluded = 'tax_excluded';
    case FeeLumpSum = 'fee_lump_sum';
    case FeePerAsset = 'fee_per_asset';
}
