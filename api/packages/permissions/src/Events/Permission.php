<?php

namespace EscolaLms\Permissions\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\Contracts\Role;

abstract class Permission
{
    use Dispatchable, SerializesModels;

    private Authenticatable $user;

    public function __construct(Authenticatable $user, Role $role)
    {
        $this->user = $user;
        $this->role = $role;
    }

    public function getUser(): Authenticatable
    {
        return $this->user;
    }

    public function getRole(): Role
    {
        return $this->role;
    }
}
