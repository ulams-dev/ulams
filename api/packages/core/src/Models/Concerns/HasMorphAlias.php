<?php

namespace Ulams\Core\Models\Concerns;

use Ulams\Core\Support\MorphMap;

/**
 * Makes a model (and every subclass of it) store the morph alias of its nearest mapped ancestor, so an
 * application model that extends a package model is saved under the package model's alias.
 */
trait HasMorphAlias
{
    public function getMorphClass()
    {
        return MorphMap::aliasOf(static::class) ?? parent::getMorphClass();
    }
}
