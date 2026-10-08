<?php

namespace Ulams\Youtube\Services;

use Ulams\Youtube\Dto\Contracts\YTLiveDtoContract;
use Ulams\Youtube\Dto\YTBroadcastDto;
use Ulams\Youtube\Services\Contracts\YoutubeServiceContract;
use Illuminate\Support\Collection;

class NullYoutubeService implements YoutubeServiceContract
{

    public function generateYTStream(YTBroadcastDto $YTBroadcastDto): ?YTLiveDtoContract
    {
        return null;
    }

    public function updateYTStream(YTBroadcastDto $YTBroadcastDto): ?YTLiveDtoContract
    {
        return null;
    }

    public function removeYTStream(YTBroadcastDto $YTBroadcastDto): bool
    {
        return true;
    }

    public function setRefreshToken(string $code): void
    {
        //
    }

    public function setStatusInLiveStream(YTBroadcastDto $YTBroadcastDto, string $broadcastStatus)
    {
        //
    }

    public function getYtLiveStream(YTBroadcastDto $YTBroadcastDto): Collection
    {
        return new Collection([]);
    }

    public function generateYTAuthUrl(string $email): string
    {
        return '';
    }

    public function dispatchYtError(): void
    {
        //
    }

    public function isConfigured(): bool {
        return false;
    }
}
