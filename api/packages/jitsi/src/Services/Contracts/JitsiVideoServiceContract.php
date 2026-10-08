<?php

namespace Ulams\Jitsi\Services\Contracts;

use Ulams\Jitsi\Dto\RecordedVideoDto;

interface JitsiVideoServiceContract
{
    public function recordedVideo(RecordedVideoDto $dto): void;
}
