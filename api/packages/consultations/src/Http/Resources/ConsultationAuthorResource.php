<?php

namespace Ulams\Consultations\Http\Resources;

use Ulams\Auth\Traits\ResourceExtandable;
use Ulams\ModelFields\Enum\MetaFieldVisibilityEnum;
use Ulams\ModelFields\Facades\ModelFields;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationAuthorResource extends JsonResource
{
    use ResourceExtandable;

    /**
     * Public listings (`/api/consultations`) are open to anonymous visitors: only the public
     * profile of a tutor. Admin routes keep the full user record.
     */
    public function toArray($request)
    {
        $user = $this->resource;
        $base = $request && $request->is('api/admin/*')
            ? $user->toArray()
            : [
                'id' => $user->getKey(),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'path_avatar' => $user->path_avatar,
                'url_avatar' => $user->avatar_url,
                'avatar_url' => $user->avatar_url,
            ];
        $fields = array_merge(
            $base,
            ['categories' => $user->categories],
            ModelFields::getExtraAttributesValues($user, MetaFieldVisibilityEnum::PUBLIC)
        );

        return self::apply($fields, $this);
    }
}
