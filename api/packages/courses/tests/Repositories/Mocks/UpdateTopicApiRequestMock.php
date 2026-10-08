<?php

namespace Ulams\Courses\Tests\Repositories\Mocks;

use Ulams\Courses\Http\Requests\UpdateTopicAPIRequest;
use Ulams\Courses\Models\Topic;

class UpdateTopicApiRequestMock extends UpdateTopicAPIRequest
{
    public function authorize()
    {
        return true;
    }

    public function validateResolved()
    {
        // do nothing
    }

    public function manualValidation(): self
    {
        $this->prepareForValidation();

        if (!$this->passesAuthorization()) {
            $this->failedAuthorization();
        }

        $instance = $this->getValidatorInstance();

        if ($instance->fails()) {
            $this->failedValidation($instance);
        }

        $this->passedValidation();

        return $this;
    }

    public function getTopic(): ?Topic
    {
        return Topic::find($this->input('topic'));
    }
}
