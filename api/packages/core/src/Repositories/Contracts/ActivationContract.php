<?php

namespace Ulams\Core\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ActivationContract
{
    public function getActive(): Collection;
}
