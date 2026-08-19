<?php

namespace App\Enums;

enum OfferTemplateCategory: string
{
    case PropertyCollateral = 'property-collateral';
    case PropertyAuction = 'property-auction';
    case PropertyRental = 'property-rental';
}
