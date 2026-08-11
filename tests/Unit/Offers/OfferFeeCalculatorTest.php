<?php

namespace Tests\Unit\Offers;

use App\Services\Offers\OfferFeeCalculator;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferFeeCalculatorTest extends TestCase
{
    public function test_it_calculates_excluded_and_included_ppn_in_integer_rupiah(): void
    {
        $calculator = new OfferFeeCalculator;

        $excluded = $calculator->calculate([
            ['label' => 'Jasa', 'quantity' => 1, 'unit_amount' => 1_000_000],
        ], 'excluded', 1100, 200);

        $this->assertSame(1_000_000, $excluded['quoted_amount']);
        $this->assertSame(1_000_000, $excluded['tax_base']);
        $this->assertSame(110_000, $excluded['ppn']);
        $this->assertSame(20_000, $excluded['pph']);
        $this->assertSame(1_110_000, $excluded['document_payable_total']);

        $included = $calculator->calculate([
            ['label' => 'Jasa', 'quantity' => 1, 'unit_amount' => 1_110_000],
        ], 'included', 1100);

        $this->assertSame(1_000_000, $included['tax_base']);
        $this->assertSame(110_000, $included['ppn']);
        $this->assertSame(1_110_000, $included['document_payable_total']);
    }

    public function test_last_payment_term_receives_rounding_residual(): void
    {
        $result = (new OfferFeeCalculator)->calculate([
            ['label' => 'Jasa', 'quantity' => 1, 'unit_amount' => 101],
        ], 'non_taxable', paymentTerms: [
            ['sequence' => 1, 'percentage_bps' => 3333, 'trigger_text' => 'Awal'],
            ['sequence' => 2, 'percentage_bps' => 3333, 'trigger_text' => 'Tengah'],
            ['sequence' => 3, 'percentage_bps' => 3334, 'trigger_text' => 'Akhir'],
        ]);

        $this->assertSame([34, 34, 33], array_column($result['payment_terms'], 'amount'));
        $this->assertSame(101, array_sum(array_column($result['payment_terms'], 'amount')));
        $this->assertSame(10_000, $result['payment_term_bps_total']);
    }

    #[DataProvider('invalidIntegerProvider')]
    public function test_it_rejects_invalid_or_out_of_range_integer_input(mixed $value): void
    {
        $this->expectException(DomainException::class);

        (new OfferFeeCalculator)->calculate([
            ['label' => 'Jasa', 'quantity' => 1, 'unit_amount' => $value],
        ], 'non_taxable');
    }

    public static function invalidIntegerProvider(): array
    {
        return [
            'decimal' => ['10.5'],
            'negative' => [-1],
            'larger than php integer' => [(string) PHP_INT_MAX.'0'],
        ];
    }

    public function test_it_rejects_payment_terms_that_do_not_total_one_hundred_percent(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Total persentase termin harus tepat 100%.');

        (new OfferFeeCalculator)->calculate([
            ['label' => 'Jasa', 'quantity' => 1, 'unit_amount' => 1_000],
        ], 'non_taxable', paymentTerms: [
            ['sequence' => 1, 'percentage_bps' => 5000, 'trigger_text' => 'Awal'],
        ]);
    }
}
