<?php

namespace Ulams\Commerce\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;

class SupportTest extends TestCase
{
    public function testPriceNormalisesTheCurrencyAndRejectsBadValues(): void
    {
        $this->assertSame('USD', (new Price(4900, 'usd'))->currency);
        $this->expectException(InvalidArgumentException::class);
        new Price(-1, 'USD');
    }

    public function testPriceNeedsAnIsoCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Price(100, 'dollars');
    }

    public function testSellableMustBeACourseOrBundleWithAnId(): void
    {
        $this->assertSame(['course', 7], [SellableRef::course(7)->type, SellableRef::course(7)->id]);
        $this->expectException(InvalidArgumentException::class);
        new SellableRef('webinar', 1);
    }
}
