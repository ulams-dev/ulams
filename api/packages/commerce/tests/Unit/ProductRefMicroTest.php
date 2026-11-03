<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\ProductRef;

#[\PHPUnit\Framework\Attributes\TestDox("ProductRef Tests")]
class ProductRefMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testProductRefCanBeConstructed()
    {
        $productRef = new ProductRef('provider1', 'external123');
        $this->assertInstanceOf(ProductRef::class, $productRef);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProductRefHasCorrectProvider()
    {
        $productRef = new ProductRef('provider1', 'external123');
        $this->assertEquals('provider1', $productRef->provider);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProductRefHasCorrectExternalId()
    {
        $productRef = new ProductRef('provider1', 'external123');
        $this->assertEquals('external123', $productRef->externalId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProductRefPropertiesAreReadOnly()
    {
        $productRef = new ProductRef('provider1', 'external123');
        $this->expectException(\Error::class);
        $productRef->provider = 'newProvider';
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProductRefEqualityWithSameValues()
    {
        $productRef1 = new ProductRef('provider1', 'external123');
        $productRef2 = new ProductRef('provider1', 'external123');
        $this->assertTrue($productRef1 == $productRef2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProductRefEqualityWithDifferentProviders()
    {
        $productRef1 = new ProductRef('provider1', 'external123');
        $productRef2 = new ProductRef('provider2', 'external123');
        $this->assertFalse($productRef1 == $productRef2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProductRefEqualityWithDifferentExternalIds()
    {
        $productRef1 = new ProductRef('provider1', 'external123');
        $productRef2 = new ProductRef('provider1', 'external456');
        $this->assertFalse($productRef1 == $productRef2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProductRefInequality()
    {
        $productRef1 = new ProductRef('provider1', 'external123');
        $productRef2 = new ProductRef('provider2', 'external456');
        $this->assertTrue($productRef1 != $productRef2);
    }
}
