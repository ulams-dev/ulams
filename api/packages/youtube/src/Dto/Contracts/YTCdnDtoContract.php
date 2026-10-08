<?php

namespace Ulams\Youtube\Dto\Contracts;

interface YTCdnDtoContract
{
    public function getStreamUrl(): ?string;
    public function getStreamName(): ?string;
}
