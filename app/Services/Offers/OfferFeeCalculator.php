<?php

namespace App\Services\Offers;

use App\Enums\OfferTaxInclusion;
use DomainException;
use OverflowException;

class OfferFeeCalculator
{
    /**
     * @param  iterable<array<string, mixed>|object>  $items
     * @param  iterable<array<string, mixed>|object>  $paymentTerms
     * @return array<string, mixed>
     */
    public function calculate(
        iterable $items,
        OfferTaxInclusion|string $taxInclusion,
        int $ppnRateBps = 1100,
        int $pphRateBps = 0,
        iterable $paymentTerms = [],
    ): array {
        $taxMode = $taxInclusion instanceof OfferTaxInclusion
            ? $taxInclusion
            : OfferTaxInclusion::tryFrom($taxInclusion);

        if ($taxMode === null) {
            throw new DomainException('Mode pajak penawaran tidak valid.');
        }

        $this->assertRate($ppnRateBps, 'Tarif PPN');
        $this->assertRate($pphRateBps, 'Tarif PPh');

        $normalizedItems = [];
        $quotedAmount = 0;

        foreach ($items as $index => $item) {
            $quantity = $this->positiveInteger($this->value($item, 'quantity', 1), 'Kuantitas fee');
            $unitAmount = $this->nonNegativeInteger($this->value($item, 'unit_amount'), 'Nilai satuan fee');
            $lineTotal = $this->multiply($quantity, $unitAmount);
            $quotedAmount = $this->add($quotedAmount, $lineTotal);

            $normalizedItems[] = [
                'id' => $this->value($item, 'id'),
                'label' => trim((string) $this->value($item, 'label', 'Item '.($index + 1))),
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'line_total' => $lineTotal,
            ];
        }

        [$taxBase, $ppn, $payable] = match ($taxMode) {
            OfferTaxInclusion::Excluded => [
                $quotedAmount,
                $this->roundRatio($quotedAmount, $ppnRateBps, 10_000),
                null,
            ],
            OfferTaxInclusion::Included => $this->includedTax($quotedAmount, $ppnRateBps),
            OfferTaxInclusion::NonTaxable => [$quotedAmount, 0, $quotedAmount],
        };

        if ($payable === null) {
            $payable = $this->add($taxBase, $ppn);
        }

        $pph = $this->roundRatio($taxBase, $pphRateBps, 10_000);
        $terms = $this->calculateTerms($paymentTerms, $payable);

        return [
            'tax_inclusion' => $taxMode->value,
            'ppn_rate_bps' => $ppnRateBps,
            'pph_rate_bps' => $pphRateBps,
            'line_items' => $normalizedItems,
            'quoted_amount' => $quotedAmount,
            'tax_base' => $taxBase,
            'ppn' => $ppn,
            'pph' => $pph,
            'document_payable_total' => $payable,
            'payment_terms' => $terms,
            'payment_term_bps_total' => array_sum(array_column($terms, 'percentage_bps')),
        ];
    }

    /**
     * @param  iterable<array<string, mixed>|object>  $paymentTerms
     * @return list<array<string, mixed>>
     */
    private function calculateTerms(iterable $paymentTerms, int $payable): array
    {
        $terms = [];

        foreach ($paymentTerms as $index => $term) {
            $percentage = $this->nonNegativeInteger(
                $this->value($term, 'percentage_bps'),
                'Persentase termin',
            );
            $this->assertRate($percentage, 'Persentase termin');

            $terms[] = [
                'id' => $this->value($term, 'id'),
                'sequence' => $this->positiveInteger(
                    $this->value($term, 'sequence', $index + 1),
                    'Urutan termin',
                ),
                'percentage_bps' => $percentage,
                'trigger_text' => trim((string) $this->value($term, 'trigger_text', '')),
                'due_days' => $this->nullableNonNegativeInteger($this->value($term, 'due_days')),
            ];
        }

        if ($terms === []) {
            return [];
        }

        if (array_sum(array_column($terms, 'percentage_bps')) !== 10_000) {
            throw new DomainException('Total persentase termin harus tepat 100%.');
        }

        $allocated = 0;
        $lastIndex = array_key_last($terms);

        foreach ($terms as $index => &$term) {
            $term['amount'] = $index === $lastIndex
                ? $payable - $allocated
                : $this->roundRatio($payable, $term['percentage_bps'], 10_000);
            $allocated = $this->add($allocated, $term['amount']);
        }
        unset($term);

        return $terms;
    }

    /** @return array{int, int, int} */
    private function includedTax(int $quotedAmount, int $ppnRateBps): array
    {
        $taxBase = $this->roundRatio($quotedAmount, 10_000, 10_000 + $ppnRateBps);

        return [$taxBase, $quotedAmount - $taxBase, $quotedAmount];
    }

    private function roundRatio(int $amount, int $multiplier, int $divisor): int
    {
        if ($amount === 0 || $multiplier === 0) {
            return 0;
        }

        $product = $this->multiply($amount, $multiplier);
        $quotient = intdiv($product, $divisor);
        $remainder = $product % $divisor;

        return $remainder * 2 >= $divisor ? $quotient + 1 : $quotient;
    }

    private function multiply(int $left, int $right): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw new OverflowException('Nilai perhitungan fee melampaui kapasitas integer.');
        }

        return $left * $right;
    }

    private function add(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new OverflowException('Total fee melampaui kapasitas integer.');
        }

        return $left + $right;
    }

    private function assertRate(int $rate, string $label): void
    {
        if ($rate < 0 || $rate > 10_000) {
            throw new DomainException("{$label} harus berada pada rentang 0 sampai 10000 basis point.");
        }
    }

    private function positiveInteger(mixed $value, string $label): int
    {
        $integer = $this->nonNegativeInteger($value, $label);

        if ($integer < 1) {
            throw new DomainException("{$label} harus minimal 1.");
        }

        return $integer;
    }

    private function nonNegativeInteger(mixed $value, string $label): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 0,
                    'max_range' => PHP_INT_MAX,
                ],
            ]);

            if ($filtered === false) {
                throw new DomainException("{$label} berada di luar rentang integer yang didukung.");
            }

            $integer = $filtered;
        } else {
            throw new DomainException("{$label} harus berupa bilangan bulat.");
        }

        if ($integer < 0) {
            throw new DomainException("{$label} tidak boleh negatif.");
        }

        return $integer;
    }

    private function nullableNonNegativeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->nonNegativeInteger($value, 'Jatuh tempo termin');
    }

    private function value(array|object $source, string $key, mixed $default = null): mixed
    {
        if (is_array($source)) {
            return $source[$key] ?? $default;
        }

        return $source->{$key} ?? $default;
    }
}
