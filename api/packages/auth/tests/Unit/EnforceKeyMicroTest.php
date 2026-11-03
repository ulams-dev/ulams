<?php

declare(strict_types=1);

namespace Ulams\Auth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Auth\Support\EnforceKey;

final class EnforceKeyMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testKeyFormatWithNonEmptyId(): void
    {
        $tokenId = '12345';
        $expectedKey = 'ulams-token:12345';
        $actualKey = EnforceKey::for($tokenId);

        $this->assertEquals($expectedKey, $actualKey);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testKeyFormatWithEmptyId(): void
    {
        $tokenId = '';
        $expectedKey = 'ulams-token:';
        $actualKey = EnforceKey::for($tokenId);

        $this->assertEquals($expectedKey, $actualKey);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDifferentIdsGiveDifferentKeys(): void
    {
        $tokenId1 = '12345';
        $tokenId2 = '67890';
        $key1 = EnforceKey::for($tokenId1);
        $key2 = EnforceKey::for($tokenId2);

        $this->assertNotEquals($key1, $key2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testKeyFormatWithNumericId(): void
    {
        $tokenId = 12345;
        $expectedKey = 'ulams-token:12345';
        $actualKey = EnforceKey::for((string)$tokenId);

        $this->assertEquals($expectedKey, $actualKey);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testKeyFormatWithSpecialCharactersId(): void
    {
        $tokenId = '!@#$%^&*()';
        $expectedKey = 'ulams-token:!@#$%^&*()';
        $actualKey = EnforceKey::for($tokenId);

        $this->assertEquals($expectedKey, $actualKey);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testKeyFormatWithWhitespaceId(): void
    {
        $tokenId = '   ';
        $expectedKey = 'ulams-token:   ';
        $actualKey = EnforceKey::for($tokenId);

        $this->assertEquals($expectedKey, $actualKey);
    }
}
