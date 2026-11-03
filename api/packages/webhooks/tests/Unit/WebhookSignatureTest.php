<?php

namespace Ulams\Webhooks\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Webhooks\Support\WebhookSignature;

class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test';

    public function testASignatureVerifiesAgainstTheSameBody(): void
    {
        $header = WebhookSignature::sign(self::SECRET, '{"a":1}', 1_700_000_000);

        $this->assertMatchesRegularExpression('/^t=1700000000,v1=[0-9a-f]{64}$/', $header);
        $this->assertTrue(WebhookSignature::verify($header, '{"a":1}', self::SECRET, 300, 1_700_000_100));
    }

    public function testATamperedBodyOrAWrongSecretFails(): void
    {
        $header = WebhookSignature::sign(self::SECRET, '{"a":1}', 1_700_000_000);

        $this->assertFalse(WebhookSignature::verify($header, '{"a":2}', self::SECRET, 300, 1_700_000_000));
        $this->assertFalse(WebhookSignature::verify($header, '{"a":1}', 'whsec_other', 300, 1_700_000_000));
    }

    public function testAnOldTimestampIsRejectedSoACapturedRequestCannotBeReplayed(): void
    {
        $header = WebhookSignature::sign(self::SECRET, 'body', 1_700_000_000);

        $this->assertFalse(WebhookSignature::verify($header, 'body', self::SECRET, 300, 1_700_000_301));
        $this->assertFalse(WebhookSignature::verify($header, 'body', self::SECRET, 300, 1_699_999_699));
        $this->assertTrue(WebhookSignature::verify($header, 'body', self::SECRET, 300, 1_700_000_300));
    }

    public function testChangingTheTimestampInTheHeaderInvalidatesTheSignature(): void
    {
        $header = WebhookSignature::sign(self::SECRET, 'body', 1_700_000_000);
        $forged = preg_replace('/^t=\d+/', 't=1700000200', $header);

        $this->assertFalse(WebhookSignature::verify($forged, 'body', self::SECRET, 300, 1_700_000_200));
    }

    public function testMalformedHeadersFail(): void
    {
        foreach (['', 'garbage', 't=abc,v1=00', 't=1700000000', 'v1=00', 't=1700000000,v1='] as $header) {
            $this->assertFalse(WebhookSignature::verify($header, 'body', self::SECRET, 0, 1_700_000_000), $header);
        }
    }

    public function testASecondV1ValueIsAcceptedForARotationWindow(): void
    {
        $good = WebhookSignature::sign(self::SECRET, 'body', 1_700_000_000);
        $header = 't=1700000000,v1=' . str_repeat('0', 64) . ',' . substr($good, strlen('t=1700000000,'));

        $this->assertTrue(WebhookSignature::verify($header, 'body', self::SECRET, 300, 1_700_000_000));
    }

    public function testSecretsAreRandomAndPrefixed(): void
    {
        $a = WebhookSignature::newSecret();

        $this->assertStringStartsWith('whsec_', $a);
        $this->assertSame(54, strlen($a));
        $this->assertNotSame($a, WebhookSignature::newSecret());
    }
}
