<?php

namespace App\Enums;

enum OfferDocumentArtifactType: string
{
    case Draft = 'draft';
    case Final = 'final';
    case SignedScan = 'signed_scan';
}
