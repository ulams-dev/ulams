<?php

namespace EscolaLms\Settings\Events;

use Illuminate\Contracts\Auth\Authenticatable;

class SettingPackageConfigUpdated
{
    private Authenticatable $user;
    private array $config;

    public function __construct(Authenticatable $user, array $config)
    {
        $this->user = $user;
        $this->config = $config;
    }

    public function getUser(): Authenticatable
    {
        return $this->user;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
