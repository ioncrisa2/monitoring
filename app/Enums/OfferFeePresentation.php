<?php

namespace App\Enums;

enum OfferFeePresentation: string
{
    case LumpSum = 'lump_sum';
    case PerAsset = 'per_asset';
}
