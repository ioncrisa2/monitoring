<?php

namespace App\Services\Offers;

final class OfferDocumentContentGuard
{
    private const PROVISIONAL_PATTERN = '/(?:\[DRAF\]|\bDRAFT?\s*(?:—|–|-|\/)\s*|\bPROVISIONAL\b)/iu';

    public static function containsProvisionalMarker(mixed $value): bool
    {
        if (is_string($value)) {
            return preg_match(self::PROVISIONAL_PATTERN, $value) === 1;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (self::containsProvisionalMarker($item)) {
                return true;
            }
        }

        return false;
    }
}
