<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Http\Requests\Abstracts\TopicResourceAPIRequest;

class DeleteTopicResourceAPIRequest extends TopicResourceAPIRequest
{
    public function getTopicResourceId(): int
    {
        return $this->route('resource_id');
    }

    public function rules(): array
    {
        return [];
    }
}
