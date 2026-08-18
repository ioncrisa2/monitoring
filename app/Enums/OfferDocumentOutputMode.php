<?php

namespace App\Enums;

enum OfferDocumentOutputMode: string
{
    case Draft = 'draft';
    case PrintReady = 'print_ready';
}
