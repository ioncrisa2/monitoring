<?php

namespace App\Services;

use App\Models\Offer;
use Carbon\Carbon;

class OfferNumberService
{
    private const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /**
     * Compose the enforced offer number: {sequence}/S.Kontrak/KJPP-HJA'R/{branch}/{roman month}/{year}
     */
    public static function build(int $sequence, int $branchNumberCode, Carbon $offerDate): string
    {
        $roman = self::ROMAN_MONTHS[(int) $offerDate->format('n')];

        return sprintf(
            "%d/S.Kontrak/KJPP-HJA'R/%d/%s/%d",
            $sequence,
            $branchNumberCode,
            $roman,
            $offerDate->year
        );
    }

    /**
     * Last sequence number used across all branches for the given year.
     */
    public static function lastSequence(int $year): int
    {
        return (int) Offer::whereYear('offer_date', $year)->max('sequence_no');
    }

    /**
     * Suggested next sequence number for the given year (resets every year, shared across branches).
     */
    public static function nextSequence(int $year): int
    {
        return self::lastSequence($year) + 1;
    }
}
