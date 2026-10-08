<?php

namespace Ulams\Jitsi\Services\Contracts;

use Ulams\Auth\Models\User;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\Auth;


interface JitsiServiceContract
{
    public function getChannelData(
        User $user,
        string $channelDisplayName,
        bool $isModerator = false,
        array $configOverwrite = [],
        $interfaceConfigOverwrite = []
    ): array;
    public function setConfig(array $config): void;
}
