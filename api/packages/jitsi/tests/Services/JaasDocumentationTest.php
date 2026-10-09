<?php

namespace Ulams\Jitsi\Tests\Services;

use Illuminate\Support\Facades\Config;
use ReflectionMethod;
use Ulams\Jitsi\Exceptions\RecordingUrlNotAllowedException;
use Ulams\Jitsi\Http\Middleware\VerifyJitsiWebhook;
use Ulams\Jitsi\Services\JitsiVideoService;
use Ulams\Jitsi\Tests\TestCase;

/**
 * Checks the webhook verification against the example published by 8x8
 * (https://developer.8x8.com/jaas/docs/webhooks-signatures, read 2026-10-09) and the recording link
 * format of RECORDING_UPLOADED (https://developer.8x8.com/jaas/docs/webhooks-payload).
 */
class JaasDocumentationTest extends TestCase
{
    private const SECRET = 'whsec_9635df66714a4cf088ee9d0979dd3bf6';
    private const SIGNATURE = 'xlzqEojlh4qb21sQpXYsWgyK8x9HVpz+RQldsv18rV0=';
    private const TIMESTAMP = 1632490060;
    private const PAYLOAD = '{"eventType":"PARTICIPANT_JOINED","sessionId":"9a441d60-ceaf-4eba-b0a8-a7d940a76e1b","timestamp":1632490058278,"fqn":"vpaas-magic-cookie-96f0941768964ab380ed0fbada7a502f/sampleappromanticshiftsstripas","idempotencyKey":"9e9e7420-562d-4659-8e22-44b9b22aaa49","customerId":"96f0941768964ab380ed0fbada7a502f","appId":"vpaas-magic-cookie-96f0941768964ab380ed0fbada7a502f","data":{"avatar":"","name":"Test User","id":"auth0|5f903d7a77f3b4006eb8e67d","participantJid":"fc1ea14a-9bca-4218-a563-8c627e803d56@8x8.vc","moderator":true,"email":"test.user@company.com"}}';

    public function testAcceptsTheSignatureFromTheJaasDocumentation(): void
    {
        $header = 't=' . self::TIMESTAMP . ',v1=' . self::SIGNATURE;

        $this->assertTrue(VerifyJitsiWebhook::isValidSignature(self::PAYLOAD, $header, self::SECRET, 300, self::TIMESTAMP + 5));
    }

    public function testRejectsAnOldTimestampAndATamperedBody(): void
    {
        $header = 't=' . self::TIMESTAMP . ',v1=' . self::SIGNATURE;

        $this->assertFalse(VerifyJitsiWebhook::isValidSignature(self::PAYLOAD, $header, self::SECRET, 300, self::TIMESTAMP + 3600));
        $this->assertFalse(VerifyJitsiWebhook::isValidSignature(self::PAYLOAD . ' ', $header, self::SECRET, 300, self::TIMESTAMP));
    }

    public function testIgnoresSchemesOtherThanV1(): void
    {
        $header = 't=' . self::TIMESTAMP . ',v0=' . self::SIGNATURE;

        $this->assertFalse(VerifyJitsiWebhook::isValidSignature(self::PAYLOAD, $header, self::SECRET, 300, self::TIMESTAMP));
    }

    public function testAllowsTheObjectStorageHostJaasUsesForRecordings(): void
    {
        $url = 'https://objectstorage.us-phoenix-1.oraclecloud.com/p/abc/n/ns/b/vpaas-recordings-stage-8x8-us-phoenix-1/o/vpaas-magic-cookie-c2824d584eac4489a1e32e4e164d5a3c/testroom2_2020-09-24-12-01-07.mp4';
        $method = new ReflectionMethod(JitsiVideoService::class, 'allowedRecordingExtension');
        $service = (new \ReflectionClass(JitsiVideoService::class))->newInstanceWithoutConstructor();

        Config::set('jitsi.recording_hosts', ['*.oraclecloud.com']);
        $this->assertSame('mp4', $method->invoke($service, $url));

        Config::set('jitsi.recording_hosts', ['*.8x8.vc']);
        $this->expectException(RecordingUrlNotAllowedException::class);
        $method->invoke($service, $url);
    }
}
