<?php

namespace Tests\Unit;

use App\Support\RapMoney;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RapMoneyTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_amounts_are_exact_or_unknown(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, RapMoney::normalize($input));
    }

    public static function amounts(): iterable
    {
        yield ['146500000000', '146500000000.00'];
        yield ['-1 234,56', '-1234.56'];
        yield ["+1\u{00a0}234,5", '1234.50'];
        yield ["1\u{202f}234,56", '1234.56'];
        yield ['1.234,56', '1234.56'];
        yield ['1234.56', '1234.56'];
        yield ['0001234', '1234.00'];
        yield ['-0', '0.00'];
        yield ['9999999999999.99', '9999999999999.99'];
        yield ['10000000000000', null];
        yield ['9223372036854775807', null];
        yield ['107 982 187 077 107 982 187 077', null];
        yield ['1e20', null];
        yield ['12 EUR', null];
        yield ['12,345', null];
        yield ['1 23', null];
        yield ['', null];
        yield [null, null];
    }
}
