<?php

namespace Ulams\Demo\Exceptions;

use RuntimeException;
use Ulams\Demo\Enums\DemoRole;

class DemoUserNotFoundException extends RuntimeException
{
    public static function forRole(DemoRole $role): self
    {
        return new self("No demo {$role->value} account on this tenant: run ulams:tenant:seed-demo or ulams:demo:reset.");
    }
}
