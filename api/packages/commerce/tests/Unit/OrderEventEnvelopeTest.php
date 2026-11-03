<?php

namespace Ulams\Commerce\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\OrderEventEnvelope;
use Ulams\Webhooks\Support\WebhookSignature;

class OrderEventEnvelopeTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return $override + ['version' => 'v1', 'id' => '01H', 'type' => 'order.paid', 'channel_code' => 'acme', 'order_id' => '77', 'sequence' => 3, 'items' => [['ignored' => true]]];
    }

    public function testItReadsOnlyTheEnvelope(): void
    {
        $e = OrderEventEnvelope::fromPayload($this->payload());

        $this->assertSame(['01H', 'order.paid', 'acme', '77', 3], [$e->eventId, $e->type, $e->channel, $e->orderId, $e->sequence]);
    }

    #[DataProvider('invalid')]
    public function testItRejectsAnythingThatIsNotSchemaV1(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        OrderEventEnvelope::fromPayload($this->payload($override));
    }

    public static function invalid(): array
    {
        return [
            'no id' => [['id' => null]], 'empty channel' => [['channel_code' => '']], 'array order' => [['order_id' => ['x']]],
            'version' => [['version' => 'v0']], 'type' => [['type' => 'order.shipped']], 'zero sequence' => [['sequence' => 0]],
            'string sequence' => [['sequence' => '2']], 'float sequence' => [['sequence' => 1.5]], 'long id' => [['id' => str_repeat('a', 65)]],
        ];
    }

    public function testSignatureVerifyRejectsStaleFutureAndWrongSecret(): void
    {
        $body = '{"a":1}';
        $now = 1_800_000_000;
        $header = WebhookSignature::sign('s3cret', $body, $now);

        $this->assertTrue(WebhookSignature::verify($header, $body, 's3cret', 300, $now + 299));
        $this->assertFalse(WebhookSignature::verify($header, $body, 's3cret', 300, $now + 301), 'replay after the tolerance');
        $this->assertFalse(WebhookSignature::verify($header, $body, 's3cret', 300, $now - 301), 'timestamp from the future');
        $this->assertFalse(WebhookSignature::verify($header, $body, 'other', 300, $now));
        $this->assertFalse(WebhookSignature::verify($header, $body . ' ', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify('', $body, 's3cret', 300, $now));
    }
}
