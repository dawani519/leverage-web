<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_parses_user_input_into_exact_kobo(string $input, int $kobo): void
    {
        $this->assertSame($kobo, Money::parse($input));
    }

    public static function validAmounts(): array
    {
        return [
            'whole naira' => ['500', 500_00],
            'thousands separator' => ['1,250', 1_250_00],
            'with kobo' => ['1,250.50', 1_250_50],
            'one decimal place means tens of kobo' => ['10.5', 10_50],
            'naira sign and spaces' => ['₦ 2 000', 2_000_00],
            'leading zero kobo' => ['3.05', 3_05],
            'zero' => ['0', 0],
            'float-trap value is exact' => ['19.99', 19_99],
            'another float trap' => ['0.29', 29],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_anything_that_is_not_a_plain_amount(string $input): void
    {
        $this->assertNull(Money::parse($input));
    }

    public static function invalidAmounts(): array
    {
        return [['abc'], [''], ['-5'], ['1.234'], ['1e3'], ['12.'], ['.5'], ['1,2,3x'], ['9999999999999'], ['0x10']];
    }

    public function test_formats_kobo_as_naira(): void
    {
        $this->assertSame('₦0.00', Money::naira(0));
        $this->assertSame('₦0.05', Money::naira(5));
        $this->assertSame('₦1,250.50', Money::naira(1_250_50));
        $this->assertSame('₦12,345,678.90', Money::naira(12_345_678_90));
        $this->assertSame('-₦10.00', Money::naira(-10_00));
    }

    public function test_formatting_and_parsing_round_trip(): void
    {
        foreach ([0, 1, 99, 100, 12_345, 99_999_999_99] as $kobo) {
            $this->assertSame($kobo, Money::parse(Money::naira($kobo)));
        }
    }
}
