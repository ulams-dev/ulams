<?php

namespace EscolaLms\Permissions\Http\Resources;


use Spatie\Permission\Models\Permission;

use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function __construct(Permission $permission)
    {
        $this->permission = $permission;
    }

    public function toArray($request)
    {
        return [
            'id' => $this->permission->id,
            'name' => $this->permission->name,
            'guard_name' => $this->permission->guard_name,
            'assigned' => boolval($this->permission->assigned),
        ];
    }
}
