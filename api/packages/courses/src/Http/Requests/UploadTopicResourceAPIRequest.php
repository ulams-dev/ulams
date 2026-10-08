<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Http\Requests\Abstracts\TopicResourceAPIRequest;
use Ulams\Courses\Rules\TopicResourceRule;

class UploadTopicResourceAPIRequest extends TopicResourceAPIRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'resource' => [
                'required',
                new TopicResourceRule([$this->getMimesRule()], $this->route('topic_id'))
            ],
        ]);
    }

    public function getMimesRule(): ?string
    {
        $mimes = config('ulams_courses.topic_resource_mimes');
        return $mimes ? 'mimes:' . $mimes : null;
    }

    public function getUploadedResource()
    {
        if ($this->hasfile('resource')) {
            return $this->file('resource');
        }

        return $this->get('resource');
    }
}
