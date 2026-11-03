<?php

declare(strict_types=1);

namespace Ulams\Bridge\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Ulams\Bridge\Command\DeliverOutboxCommand;
use Ulams\Bridge\Entity\ChannelLink;
use Ulams\Bridge\Entity\OutboxEvent;
use Ulams\Bridge\Repository\OutboxEventRepository;
use Ulams\Bridge\Service\SecretBox;
use Ulams\Bridge\Service\WebhookSigner;

final class DeliverOutboxCommandTest extends TestCase
{
    private const SECRET = 'whsec_0123456789abcdef01234567';

    /** @var list<array{string,string,array<string,mixed>}> */
    private array $requests = [];

    private function event(string $id = '01HZX0000000000000000000AA'): OutboxEvent
    {
        return new OutboxEvent($id, 'acme', 'tok', 1, 'order.paid', ['version' => 'v1', 'id' => $id, 'type' => 'order.paid', 'channel_code' => 'acme', 'order_id' => 'tok', 'sequence' => 1]);
    }

    private function command(array $events, ?ChannelLink $link, int ...$statuses): DeliverOutboxCommand
    {
        $repo = $this->createMock(OutboxEventRepository::class);
        $repo->method('due')->willReturn($events);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($link);
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$statuses) {
            $this->requests[] = [$method, $url, $options];

            return new MockResponse('', ['http_code' => array_shift($statuses) ?? 202]);
        });

        return new DeliverOutboxCommand($repo, $em, $client, new SecretBox('app-secret'), new WebhookSigner());
    }

    private function link(): ChannelLink
    {
        return new ChannelLink('acme', 'https://api.acme.example/api/commerce/webhooks/sylius', (new SecretBox('app-secret'))->seal(self::SECRET));
    }

    public function testAnEventIsPostedSignedWithItsIdAndMarkedDelivered(): void
    {
        $event = $this->event();

        self::assertSame(1, $this->command([$event], $this->link(), 202)->pass(10));

        self::assertNotNull($event->getDeliveredAt());
        [$method, $url, $options] = $this->requests[0];
        self::assertSame(['POST', 'https://api.acme.example/api/commerce/webhooks/sylius'], [$method, $url]);
        $headers = [];
        foreach ($options['headers'] as $h) {
            [$k, $v] = explode(': ', $h, 2);
            $headers[strtolower($k)] = $v;
        }
        self::assertSame($event->getId(), $headers['ulams-event-id']);
        $body = $options['body'];
        self::assertSame($event->getPayload()['id'], json_decode($body, true)['id']);
        // the header is what the LMS recomputes: HMAC-SHA256 over "<t>.<raw body>" with the channel's secret
        preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $headers['ulams-signature'], $m);
        self::assertSame(hash_hmac('sha256', $m[1] . '.' . $body, self::SECRET), $m[2]);
        self::assertSame(0, $options['max_redirects']);
    }

    public function testADuplicateAnswerOf200CountsAsDelivered(): void
    {
        $event = $this->event();
        $this->command([$event], $this->link(), 200)->pass(10);

        self::assertNotNull($event->getDeliveredAt());
    }

    public function testAnErrorAnswerKeepsTheEventForRetry(): void
    {
        $event = $this->event();

        self::assertSame(0, $this->command([$event], $this->link(), 500)->pass(10));

        self::assertNull($event->getDeliveredAt());
        self::assertSame([1, 'The LMS answered HTTP 500.'], [$event->getAttempts(), $event->getLastError()]);
        self::assertGreaterThan(new \DateTimeImmutable(), $event->getNextAttemptAt());
    }

    public function testAnEventOfAChannelWithoutALinkIsNotSentAnywhere(): void
    {
        $event = $this->event();

        self::assertSame(0, $this->command([$event], null)->pass(10));

        self::assertSame([], $this->requests);
        self::assertStringContainsString('No channel link', (string) $event->getLastError());
    }

    public function testATransportFailureIsRecordedNotThrown(): void
    {
        $repo = $this->createMock(OutboxEventRepository::class);
        $event = $this->event();
        $repo->method('due')->willReturn([$event]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($this->link());
        $client = new MockHttpClient(fn () => throw new \Symfony\Component\HttpClient\Exception\TransportException('connection refused'));

        $delivered = (new DeliverOutboxCommand($repo, $em, $client, new SecretBox('app-secret'), new WebhookSigner()))->pass(10);

        self::assertSame(0, $delivered);
        self::assertStringContainsString('connection refused', (string) $event->getLastError());
    }
}
