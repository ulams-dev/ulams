<?php

declare(strict_types=1);

namespace Ulams\Identity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Identity\Exceptions\OidcException;

class OidcExceptionQwenTest extends TestCase
{
    public function testConstructWithValidReason()
    {
        $reason = OidcException::DISCOVERY_FAILED;
        $exception = new OidcException($reason);

        $this->assertEquals($reason, $exception->reason);
        $this->assertEquals('OpenID Connect step failed: ' . $reason, $exception->getMessage());
    }

    public function testConstructWithPreviousException()
    {
        $reason = OidcException::ISSUER_MISMATCH;
        $previousException = new \Exception('Previous error');
        $exception = new OidcException($reason, $previousException);

        $this->assertEquals($reason, $exception->reason);
        $this->assertEquals('OpenID Connect step failed: ' . $reason, $exception->getMessage());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testConstructWithUnknownReason()
    {
        $reason = 'unknown_reason';
        $exception = new OidcException($reason);

        $this->assertEquals($reason, $exception->reason);
        $this->assertEquals('OpenID Connect step failed: ' . $reason, $exception->getMessage());
    }
}
