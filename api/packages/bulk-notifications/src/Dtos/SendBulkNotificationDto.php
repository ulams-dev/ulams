<?php

namespace EscolaLms\BulkNotifications\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SendBulkNotificationDto implements DtoContract, InstantiateFromRequest
{
    private string $channel;

    private Collection $sections;

    public function __construct(string $channel, array $sections)
    {
        $this->channel = $channel;
        $this->sections = collect($sections);
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->getChannel(),
            'sections' => $this->getSections(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('channel'),
            $request->input('sections'),
        );
    }
}
