<?php

declare(strict_types=1);

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\CheckoutRef;

class CheckoutRefMicroTest extends TestCase
{
    #[Test]
    public function testConstructionWithUrlOnly()
    {
        $checkoutRef = new CheckoutRef('https://example.com');
        $this->assertEquals('https://example.com', $checkoutRef->url);
        $this->assertNull($checkoutRef->externalId);
    }

    #[Test]
    public function testConstructionWithUrlAndExternalId()
    {
        $checkoutRef = new CheckoutRef('https://example.com', '12345');
        $this->assertEquals('https://example.com', $checkoutRef->url);
        $this->assertEquals('12345', $checkoutRef->externalId);
    }

    #[Test]
    public function testUrlIsReadOnly()
    {
        $checkoutRef = new CheckoutRef('https://example.com');
        $this->expectException(\Error::class);
        $checkoutRef->url = 'https://newexample.com';
    }

    #[Test]
    public function testExternalIdIsReadOnly()
    {
        $checkoutRef = new CheckoutRef('https://example.com', '12345');
        $this->expectException(\Error::class);
        $checkoutRef->externalId = '67890';
    }

    #[Test]
    public function testUrlMustBeString()
    {
        $this->expectException(\TypeError::class);
        new CheckoutRef(12345);
    }

    #[Test]
    public function testExternalIdCanBeNullable()
    {
        $checkoutRef = new CheckoutRef('https://example.com', null);
        $this->assertNull($checkoutRef->externalId);
    }

    #[Test]
    public function testExternalIdMustBeStringWhenNotNull()
    {
        $this->expectException(\TypeError::class);
        new CheckoutRef('https://example.com', 12345);
    }
}
