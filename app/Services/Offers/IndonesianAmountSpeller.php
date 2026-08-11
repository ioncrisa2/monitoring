<?php

namespace App\Services\Offers;

use DomainException;

class IndonesianAmountSpeller
{
    private const WORDS = [
        0 => 'nol',
        1 => 'satu',
        2 => 'dua',
        3 => 'tiga',
        4 => 'empat',
        5 => 'lima',
        6 => 'enam',
        7 => 'tujuh',
        8 => 'delapan',
        9 => 'sembilan',
        10 => 'sepuluh',
        11 => 'sebelas',
    ];

    public function spell(int $amount, string $currency = 'IDR'): string
    {
        if ($amount < 0) {
            throw new DomainException('Nilai terbilang tidak boleh negatif.');
        }

        if (strtoupper($currency) !== 'IDR') {
            throw new DomainException('Versi pertama terbilang hanya mendukung mata uang IDR.');
        }

        return ucfirst($this->number($amount)).' rupiah';
    }

    private function number(int $number): string
    {
        if ($number < 12) {
            return self::WORDS[$number];
        }

        if ($number < 20) {
            return $this->number($number - 10).' belas';
        }

        if ($number < 100) {
            return $this->joined($this->number(intdiv($number, 10)), 'puluh', $this->remainder($number, 10));
        }

        if ($number < 200) {
            return $this->joined('seratus', $this->remainder($number, 100));
        }

        if ($number < 1_000) {
            return $this->joined($this->number(intdiv($number, 100)), 'ratus', $this->remainder($number, 100));
        }

        if ($number < 2_000) {
            return $this->joined('seribu', $this->remainder($number, 1_000));
        }

        foreach ([
            1_000_000_000_000_000 => 'kuadriliun',
            1_000_000_000_000 => 'triliun',
            1_000_000_000 => 'miliar',
            1_000_000 => 'juta',
            1_000 => 'ribu',
        ] as $scale => $word) {
            if ($number >= $scale) {
                return $this->joined(
                    $this->number(intdiv($number, $scale)),
                    $word,
                    $this->remainder($number, $scale),
                );
            }
        }

        throw new DomainException('Nilai terbilang berada di luar rentang yang didukung.');
    }

    private function remainder(int $number, int $divisor): string
    {
        $remainder = $number % $divisor;

        return $remainder === 0 ? '' : $this->number($remainder);
    }

    private function joined(string ...$parts): string
    {
        return implode(' ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
    }
}
