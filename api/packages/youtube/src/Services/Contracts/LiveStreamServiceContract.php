<?php

namespace Ulams\Youtube\Services\Contracts;

use Ulams\Youtube\Dto\Contracts\YTLiveDtoContract;
use Ulams\Youtube\Dto\YTBroadcastDto;
use Ulams\Youtube\Dto\YTLiveDto;
use Illuminate\Support\Collection;

interface LiveStreamServiceContract
{
    public function broadcast($token, YTBroadcastDto $ytBroadcastDto): ?YTLiveDtoContract;
    public function updateBroadcast($token, YTBroadcastDto $YTBroadcastDto): ?YTLiveDto;
    public function deleteEvent($token, YTBroadcastDto $YTBroadcastDto): bool;
    public function transitionEvent($token, YTBroadcastDto $YTBroadcastDto, string $broadcastStatus);
    public function getListLiveStream($token, YTBroadcastDto $YTBroadcastDto): Collection|false;
}
