<?php

namespace EscolaLms\BulkNotifications\Dtos;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SendUserBulkNotificationDto extends SendBulkNotificationDto
{
    private Collection $userIds;

    public function __construct(string $channel, array $sections, array $userIds)
    {
        parent::__construct($channel, $sections);

        $this->userIds = collect($userIds);
    }

    public function getUserIds(): Collection
    {
        return $this->userIds;
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->getChannel(),
            'sections' => $this->getSections(),
            'user_ids' => $this->getUserIds(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('channel'),
            $request->input('sections'),
            $request->input('user_ids'),
        );
    }
}
