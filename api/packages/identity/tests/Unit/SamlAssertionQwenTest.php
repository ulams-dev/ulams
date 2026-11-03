<?php

declare(strict_types=1);

namespace Ulams\Identity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Identity\Services\Saml\SamlAssertion;

class SamlAssertionQwenTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $nameId = 'user123';
        $nameIdFormat = 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified';
        $sessionIndex = 'session123';
        $attributes = [
            'email' => ['user@example.com'],
            'role' => ['admin'],
        ];
        $assertionId = 'assertion123';
        $expiresAt = time() + 3600;
        $usedSha1 = true;

        $samlAssertion = new SamlAssertion(
            $nameId,
            $nameIdFormat,
            $sessionIndex,
            $attributes,
            $assertionId,
            $expiresAt,
            $usedSha1
        );

        $this->assertEquals($nameId, $samlAssertion->nameId);
        $this->assertEquals($nameIdFormat, $samlAssertion->nameIdFormat);
        $this->assertEquals($sessionIndex, $samlAssertion->sessionIndex);
        $this->assertEquals($attributes, $samlAssertion->attributes);
        $this->assertEquals($assertionId, $samlAssertion->assertionId);
        $this->assertEquals($expiresAt, $samlAssertion->expiresAt);
        $this->assertEquals($usedSha1, $samlAssertion->usedSha1);
    }

    public function testFirstReturnsFirstNonEmptyValue()
    {
        $attributes = [
            'email' => ['user@example.com'],
            'role' => ['admin'],
        ];

        $samlAssertion = new SamlAssertion(
            'user123',
            null,
            null,
            $attributes,
            'assertion123',
            time() + 3600,
            false
        );

        $this->assertEquals('user@example.com', $samlAssertion->first('email', 'role'));
        $this->assertEquals('admin', $samlAssertion->first('role', 'email'));
    }

    public function testFirstReturnsNullIfNoNonEmptyValue()
    {
        $attributes = [
            'email' => [''],
            'role' => [''],
        ];

        $samlAssertion = new SamlAssertion(
            'user123',
            null,
            null,
            $attributes,
            'assertion123',
            time() + 3600,
            false
        );

        $this->assertNull($samlAssertion->first('email', 'role'));
    }

    public function testFirstReturnsFirstNonEmptyValueFromMultipleAttributes()
    {
        $attributes = [
            'email' => [''],
            'role' => ['admin'],
            'phone' => ['123-456-7890'],
        ];

        $samlAssertion = new SamlAssertion(
            'user123',
            null,
            null,
            $attributes,
            'assertion123',
            time() + 3600,
            false
        );

        $this->assertEquals('admin', $samlAssertion->first('email', 'role', 'phone'));
        $this->assertEquals('123-456-7890', $samlAssertion->first('phone', 'email', 'role'));
    }

    public function testFirstReturnsNullIfAttributesAreMissing()
    {
        $attributes = [];

        $samlAssertion = new SamlAssertion(
            'user123',
            null,
            null,
            $attributes,
            'assertion123',
            time() + 3600,
            false
        );

        $this->assertNull($samlAssertion->first('email', 'role'));
    }
}
