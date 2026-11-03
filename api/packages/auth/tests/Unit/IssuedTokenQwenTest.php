<?php

declare(strict_types=1);

namespace Ulams\Auth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Auth\Support\IssuedToken;
use Laravel\Passport\Token;
use Ulams\Auth\Models\ApiTokenMeta;

class IssuedTokenQwenTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $secret = 'test_secret';
        $token = new Token();
        $meta = new ApiTokenMeta();

        $issuedToken = new IssuedToken($secret, $token, $meta);

        $this->assertEquals($secret, $issuedToken->secret);
        $this->assertEquals($token, $issuedToken->token);
        $this->assertEquals($meta, $issuedToken->meta);
    }

    public function testPropertiesAreReadOnly()
    {
        $secret = 'test_secret';
        $token = new Token();
        $meta = new ApiTokenMeta();

        $issuedToken = new IssuedToken($secret, $token, $meta);

        $this->expectException(\Error::class);
        $issuedToken->secret = 'new_secret';
    }

    public function testTokenMetaIsCorrect()
    {
        $secret = 'test_secret';
        $token = new Token();
        $meta = new ApiTokenMeta(['scope' => 'read']);

        $issuedToken = new IssuedToken($secret, $token, $meta);

        $this->assertEquals(['scope' => 'read'], $issuedToken->meta->toArray());
    }
}
