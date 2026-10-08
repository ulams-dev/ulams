<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTopicAPIRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $user = auth()->user();
        return isset($user) && $user->can('update', $this->getTopic());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return Topic::rules();
    }

    public function getTopic(): ?Topic
    {
        return Topic::find($this->route('topic'));
    }
}
