<?php

namespace App\Enums;

enum OfferTemplateDynamicSource: string
{
    case AppraiserStatus = 'appraiser_status';
    case Client = 'client';
    case ReportUser = 'report_user';
    case OwnershipForm = 'ownership_form';
    case Currency = 'currency';
    case Purpose = 'purpose';
    case ValuationBasis = 'valuation_basis';
    case ValuationDate = 'valuation_date';
    case InvestigationLevel = 'investigation_level';
    case SpecialAssumptions = 'special_assumptions';
    case ReportSpecification = 'report_specification';
    case CompletionTime = 'completion_time';
}
