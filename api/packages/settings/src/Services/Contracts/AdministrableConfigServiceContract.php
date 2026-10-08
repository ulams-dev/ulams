<?php

namespace EscolaLms\Settings\Services\Contracts;

/**
 * @see \EscolaLms\Settings\Facades\AdministrableConfig
 */
interface AdministrableConfigServiceContract
{
    public function registerConfig(string $key, array $rules = [], bool $public = true, bool $readonly = false): bool;

    public function getConfig(string $key = null): array;
    public function getPublicConfig(): array;

    public function loadConfigFromCache(): bool;
    public function loadConfigFromDatabase(): bool;

    public function setConfig(array $config): void;
    public function storeConfig(): bool;
}
