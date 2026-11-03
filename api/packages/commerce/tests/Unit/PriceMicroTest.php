<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\Price;
use InvalidArgumentException;

#[Test]
class PriceMicroTest extends TestCase
{
    public function testValidConstruction()
    {
        $price = new Price(100, 'usd');
        $this->assertEquals(100, $price->amountMinor);
        $this->assertEquals('USD', $price->currency);
    }

    public function testCurrencyUppercasing()
    {
        $price = new Price(200, 'eur');
        $this->assertEquals('EUR', $price->currency);
    }

    public function testNegativeAmountThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A price cannot be negative.');
        new Price(-50, 'gbp');
    }

    public function testBadCurrencyThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The currency is a three-letter code like USD.');
        new Price(300, 'us');
    }

    public function testBadCurrencyTooLongThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The currency is a three-letter code like USD.');
        new Price(400, 'usdollar');
    }

    public function testBadCurrencyNonAlphaThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The currency is a three-letter code like USD.');
        new Price(500, 'us1');
    }

    public function testZeroAmountIsValid()
    {
        $price = new Price(0, 'jpy');
        $this->assertEquals(0, $price->amountMinor);
        $this->assertEquals('JPY', $price->currency);
    }
}
