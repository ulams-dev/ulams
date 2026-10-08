<?php

namespace EscolaLms\StationaryEvents\Http\Resources;

use EscolaLms\Auth\Traits\ResourceExtandable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StationaryEventResource extends JsonResource
{
    use ResourceExtandable;

    public function toArray($request): array
    {
        $fields =  [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'name' => $this->name,
            'description' => $this->description,
            'short_desc' => $this->short_desc,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'max_participants' => $this->max_participants,
            'place' => $this->place,
            'program' => $this->program,
            'categories' => $this->categories,
            'status' => $this->status,
            'authors' => $this->authors ? UserResource::collection($this->authors) : [],
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? Storage::url($this->image_path) : null,
            'in_coming' => $this->in_coming,
            'is_ended' => $this->is_ended,
            'is_started' => $this->is_started,
            'agenda' => $this->agenda,
        ];

        return self::apply($fields, $this);
    }
}
