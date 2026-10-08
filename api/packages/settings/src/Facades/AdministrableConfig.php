<?php

namespace Ulams\Settings\Facades;

use Ulams\Settings\Services\Contracts\AdministrableConfigServiceContract;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool  registerConfig(string $key, array $rules = ['required'], bool $public = true, bool $readonly = false)
 * @method static bool  storeConfig()
 * @method static bool  loadConfigFromCache()
 * @method static bool  loadConfigFromDatabase()
 * @method static array getPublicConfig()
 * @method static array getConfig(?string $key = null)
 * @method static void  setConfig(array $config)
 *
 * @see \Ulams\Settings\Services\AdministrableConfigService
 */
class AdministrableConfig extends Facade
{
    protected static function getFacadeAccessor()
    {
        return AdministrableConfigServiceContract::class;
    }
}
