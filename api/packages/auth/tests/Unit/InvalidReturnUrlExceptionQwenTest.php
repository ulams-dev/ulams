<?php

declare(strict_types=1);

namespace Ulams\Auth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Auth\Exceptions\InvalidReturnUrlException;

class InvalidReturnUrlExceptionQwenTest extends TestCase
{
    public function testExceptionMessage()
    {
        $exception = new InvalidReturnUrlException();
        $this->assertEquals('The return URL is not allowed for this site.', $exception->getMessage());
    }

    public function testExceptionCode()
    {
        $exception = new InvalidReturnUrlException();
        $this->assertEquals(422, $exception->getCode());
    }

    public function testExceptionInstanceOfRuntimeException()
    {
        $exception = new InvalidReturnUrlException();
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionToString()
    {
        $exception = new InvalidReturnUrlException();
        $expectedString = 'Ulams\Auth\Exceptions\InvalidReturnUrlException: The return URL is not allowed for this site. in ';
        $this->assertStringStartsWith($expectedString, (string) $exception);
    }

    public function testExceptionWithoutPreviousException()
    {
        $exception = new InvalidReturnUrlException();
        $this->assertNull($exception->getPrevious());
    }
}
