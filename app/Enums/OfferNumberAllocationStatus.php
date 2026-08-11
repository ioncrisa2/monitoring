<?php

namespace App\Enums;

enum OfferNumberAllocationStatus: string
{
    case Allocated = 'allocated';
    case Void = 'void';
}
