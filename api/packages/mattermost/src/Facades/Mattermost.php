<?php

namespace Ulams\Mattermost\Facades;

use Illuminate\Support\Facades\Facade;
use Ulams\Mattermost\Support\MattermostManager;

/**
 * @method static \Gnello\Mattermost\Driver server(?string $name = null)
 * @method static string getDefaultServer()
 *
 * @see MattermostManager
 */
class Mattermost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MattermostManager::class;
    }
}
