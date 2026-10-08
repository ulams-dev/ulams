<?php

namespace EscolaLms\Permissions\Http\Resources;

use Spatie\Permission\Models\Role;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function __construct(Role $role)
    {
        $this->role = $role;
    }

    public function toArray($request)
    {
        return [
            'id' => $this->role->id,
            'name' => $this->role->name,
            'guard_name' => $this->role->guard_name,
        ];
    }
}
