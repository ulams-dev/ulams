<?php

namespace Ulams\Auth\Http\Resources;

use Ulams\Auth\Models\UserSetting;
use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Auth\Traits\ResourceExtandable;

class UserSettingResource extends JsonResource
{
    use ResourceExtandable;

    public function __construct(UserSetting $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request)
    {
        /** @var UserSetting $resource */
        $resource = $this->resource;
        $fields = [
            $resource->key => $resource->value
        ];

        return self::apply($fields, $this);
    }
}
