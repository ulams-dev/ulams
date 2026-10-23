<?php

declare(strict_types=1);

namespace Ulams\Bridge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Bridge\Service\SecretBox;

final class SecretBoxTest extends TestCase
{
    public function testASecretRoundTripsAndIsNotStoredInTheClear(): void
    {
        $box = new SecretBox('app-secret-one');
        $sealed = $box->seal('whsec_super_secret_value_1234');

        self::assertStringNotContainsString('whsec_super', $sealed);
        self::assertStringNotContainsString('whsec_super', (string) base64_decode($sealed));
        self::assertSame('whsec_super_secret_value_1234', $box->open($sealed));
        self::assertNotSame($sealed, $box->seal('whsec_super_secret_value_1234'), 'a fresh nonce every time');
    }

    public function testAnotherAppSecretCannotOpenIt(): void
    {
        $sealed = (new SecretBox('app-secret-one'))->seal('x-secret-value');

        $this->expectException(\RuntimeException::class);
        (new SecretBox('app-secret-two'))->open($sealed);
    }

    public function testATamperedOrMalformedValueIsRefused(): void
    {
        $box = new SecretBox('app-secret-one');
        $raw = base64_decode($box->seal('x-secret-value'));
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";

        foreach ([base64_encode($raw), 'not base64 !!', base64_encode('short')] as $bad) {
            try {
                $box->open($bad);
                self::fail('expected a refusal');
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
        }
    }
}
