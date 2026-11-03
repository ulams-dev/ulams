<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Webhooks\Support\WebhookSignature;

/**
 * The signing side lives in the Sylius bridge (api/sylius) and the verifying side here. Both test the same recorded
 * vector, so a change to either that breaks the other fails in both suites (the bridge's WebhookSignerTest reads
 * this file too).
 */
class WebhookSignatureVectorTest extends TestCase
{
    private function vector(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../fixtures/webhook-signature-vector.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testTheBridgeSignatureVerifiesHere(): void
    {
        $v = $this->vector();

        $this->assertTrue(WebhookSignature::verify($v['header'], $v['body'], $v['secret'], 300, $v['timestamp'] + 10));
        $this->assertSame($v['header'], WebhookSignature::sign($v['secret'], $v['body'], $v['timestamp']));
    }

    public function testAnyChangeToBodyOrSecretOrAgeIsRefused(): void
    {
        $v = $this->vector();

        $this->assertFalse(WebhookSignature::verify($v['header'], $v['body'] . ' ', $v['secret'], 300, $v['timestamp']));
        $this->assertFalse(WebhookSignature::verify($v['header'], $v['body'], $v['secret'] . 'x', 300, $v['timestamp']));
        $this->assertFalse(WebhookSignature::verify($v['header'], $v['body'], $v['secret'], 300, $v['timestamp'] + 301));
    }
}
