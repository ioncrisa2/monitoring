<?php

namespace App\Enums;

enum OfferWorkflowState: string
{
    case DataDraft = 'data_draft';
    case ReadyForReview = 'ready_for_review';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Finalized = 'finalized';
    case Sent = 'sent';
    case Void = 'void';
}
