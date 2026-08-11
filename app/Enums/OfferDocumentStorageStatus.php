<?php

namespace App\Enums;

enum OfferDocumentStorageStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
    case Void = 'void';
}
