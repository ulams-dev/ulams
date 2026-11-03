<?php

declare(strict_types=1);

namespace Ulams\Auth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Auth\Exceptions\LoginHandoffException;

class LoginHandoffExceptionQwenTest extends TestCase
{
    public function testConstructWithMalformedReason()
    {
        $exception = new LoginHandoffException(LoginHandoffException::MALFORMED);
        $this->assertEquals('Login hand-off refused: malformed', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    public function testConstructWithBadSignatureReason()
    {
        $exception = new LoginHandoffException(LoginHandoffException::BAD_SIGNATURE);
        $this->assertEquals('Login hand-off refused: bad_signature', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    public function testConstructWithExpiredReason()
    {
        $exception = new LoginHandoffException(LoginHandoffException::EXPIRED);
        $this->assertEquals('Login hand-off refused: expired', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    public function testConstructWithReplayedReason()
    {
        $exception = new LoginHandoffException(LoginHandoffException::REPLAYED);
        $this->assertEquals('Login hand-off refused: replayed', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    public function testConstructWithUnknownReason()
    {
        $exception = new LoginHandoffException(LoginHandoffException::UNKNOWN);
        $this->assertEquals('Login hand-off refused: unknown', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    public function testConstructWithWrongProviderReason()
    {
        $exception = new LoginHandoffException(LoginHandoffException::WRONG_PROVIDER);
        $this->assertEquals('Login hand-off refused: wrong_provider', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }
}
