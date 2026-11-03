<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\SellableRef;
use InvalidArgumentException;

#[Test]
class SellableRefMicroTest extends TestCase
{
    public function testCourseTypeIsValid()
    {
        $sellable = SellableRef::course(1);
        $this->assertEquals(SellableRef::COURSE, $sellable->type);
    }

    public function testBundleTypeIsValid()
    {
        $sellable = new SellableRef(SellableRef::BUNDLE, 1);
        $this->assertEquals(SellableRef::BUNDLE, $sellable->type);
    }

    public function testInvalidTypeThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        new SellableRef('unknown', 1);
    }

    public function testInvalidIdThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        new SellableRef(SellableRef::COURSE, 0);
    }

    public function testNegativeIdThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        new SellableRef(SellableRef::COURSE, -1);
    }

    public function testEqualityWithSameValues()
    {
        $sellable1 = new SellableRef(SellableRef::COURSE, 1);
        $sellable2 = new SellableRef(SellableRef::COURSE, 1);
        $this->assertTrue($sellable1 == $sellable2);
    }

    public function testEqualityWithDifferentTypes()
    {
        $sellable1 = new SellableRef(SellableRef::COURSE, 1);
        $sellable2 = new SellableRef(SellableRef::BUNDLE, 1);
        $this->assertFalse($sellable1 == $sellable2);
    }

    public function testEqualityWithDifferentIds()
    {
        $sellable1 = new SellableRef(SellableRef::COURSE, 1);
        $sellable2 = new SellableRef(SellableRef::COURSE, 2);
        $this->assertFalse($sellable1 == $sellable2);
    }
}
