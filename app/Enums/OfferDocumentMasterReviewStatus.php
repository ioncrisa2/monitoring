<?php

namespace App\Enums;

enum OfferDocumentMasterReviewStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Retired = 'retired';
}
