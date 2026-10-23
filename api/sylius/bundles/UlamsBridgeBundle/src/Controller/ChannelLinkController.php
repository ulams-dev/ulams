<?php

declare(strict_types=1);

namespace Ulams\Bridge\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Ulams\Bridge\Entity\ChannelLink;
use Ulams\Bridge\Service\SecretBox;

/**
 * The per-channel delivery target, written by the LMS provisioner through the admin API (ROLE_API_ACCESS) and never
 * by a tenant admin. The secret is write-only: it is sealed on arrival and never returned.
 */
#[AsController]
final class ChannelLinkController
{
    private const PATH_SUFFIX = '/api/commerce/webhooks/sylius';

    /** @param list<string> $insecureHosts hosts that may be reached over plain http (local development) */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SecretBox $secrets,
        #[Autowire('%env(default::ULAMS_BRIDGE_HTTP_HOSTS)%')] private readonly ?string $insecureHosts = null,
    ) {
    }

    #[Route('/api/v2/admin/ulams/channel-links/{code}', name: 'ulams_bridge_channel_link_put', methods: ['PUT'], requirements: ['code' => '[A-Za-z0-9_-]{1,64}'])]
    public function put(string $code, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $url = is_array($data) && is_string($data['webhookUrl'] ?? null) ? $data['webhookUrl'] : '';
        $secret = is_array($data) && is_string($data['secret'] ?? null) ? $data['secret'] : '';
        if (strlen($secret) < 24 || !$this->allowedUrl($url)) {
            return new JsonResponse(['detail' => 'webhookUrl must be the LMS commerce webhook URL (https) and secret at least 24 characters.'], 422);
        }
        $link = $this->em->find(ChannelLink::class, $code);
        $sealed = $this->secrets->seal($secret);
        if ($link === null) {
            $this->em->persist(new ChannelLink($code, $url, $sealed));
        } else {
            $link->update($url, $sealed);
        }
        $this->em->flush();

        return new JsonResponse(['channelCode' => $code, 'webhookUrl' => $url], $link === null ? 201 : 200);
    }

    #[Route('/api/v2/admin/ulams/channel-links/{code}', name: 'ulams_bridge_channel_link_get', methods: ['GET'], requirements: ['code' => '[A-Za-z0-9_-]{1,64}'])]
    public function get(string $code): JsonResponse
    {
        $link = $this->em->find(ChannelLink::class, $code);

        return $link === null
            ? new JsonResponse(['detail' => 'Not found.'], 404)
            : new JsonResponse(['channelCode' => $code, 'webhookUrl' => $link->getWebhookUrl()]);
    }

    private function allowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }
        if (!str_ends_with((string) ($parts['path'] ?? ''), self::PATH_SUFFIX)) {
            return false;
        }
        if ($parts['scheme'] === 'https') {
            return true;
        }
        $hosts = array_filter(array_map('trim', explode(',', (string) $this->insecureHosts)));

        return $parts['scheme'] === 'http' && in_array(strtolower($parts['host']), $hosts, true);
    }
}
