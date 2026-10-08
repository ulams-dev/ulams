<?php

namespace EscolaLms\Reports\Http\Requests\Admin;

use EscolaLms\Courses\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopicStatsRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('viewAny', Report::class) || $this->user()->can('update', $this->getTopic());
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'topic_id' => $this->route('topic_id')
        ]);
    }

    public function rules()
    {
        return [
            'topic_id' => ['required', 'integer', Rule::exists((new Topic())->getTable(), 'id')],
            'stats' => ['sometimes', 'array'],
            'stats.*' => ['string', Rule::in(config('reports.stats')[Topic::class] ?? [])],
        ];
    }

    public function getTopicId(): int
    {
        return $this->validated()['topic_id'];
    }

    public function getTopic(): Topic
    {
        return Topic::find($this->getTopicId());
    }

    public function getStats(): array
    {
        return $this->has('stats') ? $this->validated()['stats'] : [];
    }
}
