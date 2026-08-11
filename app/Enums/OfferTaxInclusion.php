<?php

namespace App\Enums;

enum OfferTaxInclusion: string
{
    case Included = 'included';
    case Excluded = 'excluded';
    case NonTaxable = 'non_taxable';
}
