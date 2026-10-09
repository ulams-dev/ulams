<?php

namespace Ulams\Courses\Console;

use Spatie\ResponseCache\Commands\ClearCommand;
use Ulams\Courses\Support\ResponseCacheTags;

/**
 * `responsecache:clear` without --url: clears the response cache tags of this tenant instead of
 * the whole cache store (on Redis, a bare clear flushes the cache database of every tenant).
 */
class ClearResponseCacheCommand extends ClearCommand
{
    protected function clear()
    {
        if ($this->option('url')) {
            parent::clear();

            return;
        }

        ResponseCacheTags::clear();
    }
}
