<?php

declare(strict_types=1);

namespace Ulams\Bridge\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Where a channel's events are delivered: the LMS API of the tenant that owns the channel (ADR 0131). Only the
 * provisioner creates links, so the bridge never posts to a URL an admin typed (no SSRF). The secret is stored
 * sealed with a key derived from APP_SECRET.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ulams_channel_link')]
class ChannelLink
{
    #[ORM\Id]
    #[ORM\Column(name: 'channel_code', type: 'string', length: 64)]
    private string $channelCode;

    #[ORM\Column(name: 'webhook_url', type: 'string', length: 500)]
    private string $webhookUrl;

    #[ORM\Column(name: 'sealed_secret', type: 'text')]
    private string $sealedSecret;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $channelCode, string $webhookUrl, string $sealedSecret)
    {
        $this->channelCode = $channelCode;
        $this->webhookUrl = $webhookUrl;
        $this->sealedSecret = $sealedSecret;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getChannelCode(): string { return $this->channelCode; }
    public function getWebhookUrl(): string { return $this->webhookUrl; }
    public function getSealedSecret(): string { return $this->sealedSecret; }

    public function update(string $webhookUrl, string $sealedSecret): void
    {
        $this->webhookUrl = $webhookUrl;
        $this->sealedSecret = $sealedSecret;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
