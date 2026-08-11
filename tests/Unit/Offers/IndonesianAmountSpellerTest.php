<?php

namespace Tests\Unit\Offers;

use App\Services\Offers\IndonesianAmountSpeller;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndonesianAmountSpellerTest extends TestCase
{
    #[DataProvider('amountProvider')]
    public function test_it_spells_integer_rupiah(int $amount, string $expected): void
    {
        $this->assertSame($expected, (new IndonesianAmountSpeller)->spell($amount));
    }

    public static function amountProvider(): array
    {
        return [
            'zero' => [0, 'Nol rupiah'],
            'eleven' => [11, 'Sebelas rupiah'],
            'one hundred' => [100, 'Seratus rupiah'],
            'one thousand' => [1_000, 'Seribu rupiah'],
            'compound million' => [1_251_000, 'Satu juta dua ratus lima puluh satu ribu rupiah'],
            'billion' => [2_000_000_001, 'Dua miliar satu rupiah'],
        ];
    }

    public function test_it_rejects_negative_or_unsupported_currency(): void
    {
        $speller = new IndonesianAmountSpeller;

        try {
            $speller->spell(-1);
            $this->fail('Nilai negatif seharusnya ditolak.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('negatif', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $speller->spell(1, 'USD');
    }
}
