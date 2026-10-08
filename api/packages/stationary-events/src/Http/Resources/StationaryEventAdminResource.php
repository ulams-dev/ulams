<?php

namespace EscolaLms\StationaryEvents\Http\Resources;

class StationaryEventAdminResource extends StationaryEventResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'users' => $this->users ? UserResource::collection($this->users) : [],
        ]);
    }
}
