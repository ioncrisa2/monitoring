<?php

namespace App\Enums;

enum OfferDocumentVersionState: string
{
    case InReview = 'in_review';
    case Approved = 'approved';
    case Finalized = 'finalized';
    case Superseded = 'superseded';
    case Void = 'void';
}
