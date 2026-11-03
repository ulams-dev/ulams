<?php

declare(strict_types=1);

namespace Ulams\Identity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Identity\Exceptions\SsoRefusedException;

class SsoRefusedExceptionQwenTest extends TestCase
{
    public function testConstructorSetsReasonAndDetail()
    {
        $reason = 'link_required';
        $detail = 'specific_detail';
        $exception = new SsoRefusedException($reason, $detail);

        $this->assertEquals($reason, $exception->reason);
        $this->assertEquals($detail, $exception->detail);
    }

    public function testConstructorSetsMessage()
    {
        $reason = 'email_required';
        $exception = new SsoRefusedException($reason);

        $this->assertEquals('Sign-in refused: ' . $reason, $exception->getMessage());
    }

    public function testConstructorWithEmptyDetail()
    {
        $reason = 'email_unverified';
        $exception = new SsoRefusedException($reason);

        $this->assertEquals('', $exception->detail);
    }

    public function testConstructorWithNonEmptyDetail()
    {
        $reason = 'account_unavailable';
        $detail = 'user_not_found';
        $exception = new SsoRefusedException($reason, $detail);

        $this->assertEquals($detail, $exception->detail);
    }

    public function testConstructorSetsDefaultMessage()
    {
        $reason = 'signup_disabled';
        $exception = new SsoRefusedException($reason);

        $this->assertEquals('Sign-in refused: ' . $reason, $exception->getMessage());
    }
}
