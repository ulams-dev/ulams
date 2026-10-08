<?php

namespace Ulams\Auth\Models\Traits;

use Illuminate\Database\Eloquent\Model;

trait ExtendableModelTrait
{
    abstract protected function getTraitOwner(): Model;
}
