<?php

declare(strict_types=1);

namespace Ulams\Bridge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Bridge\Service\WebhookSigner;

/** The LMS verifies this signature (api/packages/commerce/tests/Unit/WebhookSignatureVectorTest reads the same file). */
final class WebhookSignerTest extends TestCase
{
    public function testItSignsTheRecordedVectorExactly(): void
    {
        $v = json_decode((string) file_get_contents(__DIR__ . '/../../../../../packages/commerce/tests/fixtures/webhook-signature-vector.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame($v['header'], (new WebhookSigner())->sign($v['body'], $v['secret'], $v['timestamp']));
    }

    public function testTheTimestampDefaultsToNow(): void
    {
        self::assertMatchesRegularExpression('/^t=\d{10},v1=[0-9a-f]{64}$/', (new WebhookSigner())->sign('{}', 'secret'));
    }
}
