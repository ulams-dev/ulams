<?php

namespace Ulams\Courses\Tests\Http\Resources\TopicType\Admin;

use Ulams\Courses\Http\Resources\TopicType\Contracts\TopicTypeResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;

class ExampleTopicTypeResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
