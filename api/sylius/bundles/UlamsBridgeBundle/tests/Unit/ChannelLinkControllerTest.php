<?php

declare(strict_types=1);

namespace Ulams\Bridge\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Ulams\Bridge\Controller\ChannelLinkController;
use Ulams\Bridge\Entity\ChannelLink;
use Ulams\Bridge\Service\SecretBox;

/** The delivery target is set by the LMS provisioner only; the bridge never posts to a URL it was not given there. */
final class ChannelLinkControllerTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    private function controller(?string $insecureHosts = null, ?ChannelLink $existing = null): ChannelLinkController
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($existing);
        $em->method('persist')->willReturnCallback(function (object $o): void {
            $this->persisted[] = $o;
        });

        return new ChannelLinkController($em, new SecretBox('app-secret'), $insecureHosts);
    }

    private function put(ChannelLinkController $c, array $body): \Symfony\Component\HttpFoundation\JsonResponse
    {
        return $c->put('acme', Request::create('/x', 'PUT', content: json_encode($body)));
    }

    public function testAnHttpsWebhookUrlIsStoredWithTheSecretSealedAndNeverReturned(): void
    {
        $response = $this->put($this->controller(), ['webhookUrl' => 'https://api.acme.example/api/commerce/webhooks/sylius', 'secret' => 'whsec_0123456789abcdef01234567']);

        self::assertSame(201, $response->getStatusCode());
        self::assertStringNotContainsString('whsec_', (string) $response->getContent());
        /** @var ChannelLink $link */
        $link = $this->persisted[0];
        self::assertStringNotContainsString('whsec_', $link->getSealedSecret());
        self::assertSame('whsec_0123456789abcdef01234567', (new SecretBox('app-secret'))->open($link->getSealedSecret()));
    }

    public function testAnExistingLinkIsUpdatedNotDuplicated(): void
    {
        $existing = new ChannelLink('acme', 'https://old.example/api/commerce/webhooks/sylius', 'x');
        $response = $this->put($this->controller(null, $existing), ['webhookUrl' => 'https://new.example/api/commerce/webhooks/sylius', 'secret' => 'whsec_0123456789abcdef01234567']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://new.example/api/commerce/webhooks/sylius', $existing->getWebhookUrl());
        self::assertSame([], $this->persisted);
    }

    /** @return iterable<string,array{string,?string}> */
    public static function refusedUrls(): iterable
    {
        yield 'plain http without an allowed host' => ['http://api.acme.example/api/commerce/webhooks/sylius', null];
        yield 'plain http for another host' => ['http://evil.example/api/commerce/webhooks/sylius', '.localhost'];
        yield 'a host that only ends like the allowed one' => ['http://acme.notlocalhost/api/commerce/webhooks/sylius', '.localhost'];
        yield 'another path' => ['https://api.acme.example/internal/admin', null];
        yield 'credentials in the url' => ['https://user:pw@api.acme.example/api/commerce/webhooks/sylius', null];
        yield 'a query string' => ['https://api.acme.example/api/commerce/webhooks/sylius?x=1', null];
        yield 'another scheme' => ['ftp://api.acme.example/api/commerce/webhooks/sylius', null];
        yield 'not a url' => ['nonsense', null];
    }

    #[DataProvider('refusedUrls')]
    public function testUrlsTheLmsDidNotProvisionAreRefused(string $url, ?string $hosts): void
    {
        $response = $this->put($this->controller($hosts), ['webhookUrl' => $url, 'secret' => 'whsec_0123456789abcdef01234567']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $this->persisted);
    }

    public function testPlainHttpIsAcceptedForTheDevelopmentHostsOnly(): void
    {
        self::assertSame(201, $this->put($this->controller('.localhost, api.dev'), ['webhookUrl' => 'http://acme.localhost/api/commerce/webhooks/sylius', 'secret' => 'whsec_0123456789abcdef01234567'])->getStatusCode());
        self::assertSame(201, $this->put($this->controller('.localhost, api.dev'), ['webhookUrl' => 'http://api.dev/api/commerce/webhooks/sylius', 'secret' => 'whsec_0123456789abcdef01234567'])->getStatusCode());
    }

    public function testAShortSecretIsRefused(): void
    {
        self::assertSame(422, $this->put($this->controller(), ['webhookUrl' => 'https://a.example/api/commerce/webhooks/sylius', 'secret' => 'short'])->getStatusCode());
    }
}
