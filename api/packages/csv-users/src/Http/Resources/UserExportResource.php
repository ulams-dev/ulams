<?php

namespace EscolaLms\CsvUsers\Http\Resources;

use EscolaLms\Auth\Models\User;
use EscolaLms\ModelFields\Enum\MetaFieldVisibilityEnum;
use EscolaLms\ModelFields\Facades\ModelFields;
use Illuminate\Http\Resources\Json\JsonResource;

class UserExportResource extends JsonResource
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function toArray($request): array
    {
        return array_merge(
            [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'first_name' => $this->resource->first_name,
                'last_name' => $this->resource->last_name,
                'email' => $this->resource->email,
                'password' => '',
                'is_active' => $this->resource->is_active,
                'created_at' => $this->resource->created_at,
                'email_verified' => $this->resource->email_verified,
                'path_avatar' => $this->resource->path_avatar,
                'roles' => $this->resource->roles->pluck('name'),
                'groups' => $this->resource->groups->pluck('name'),
            ],
            ModelFields::getExtraAttributesValues($this->resource, MetaFieldVisibilityEnum::ADMIN),
            ModelFields::getExtraAttributesValues($this->resource, MetaFieldVisibilityEnum::PUBLIC),
        );
    }
}
