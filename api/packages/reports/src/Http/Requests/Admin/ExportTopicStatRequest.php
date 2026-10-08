<?php

namespace EscolaLms\Reports\Http\Requests\Admin;

use EscolaLms\Courses\Models\Topic;
use Illuminate\Validation\Rule;

class ExportTopicStatRequest extends TopicStatsRequest
{
    public function rules(): array
    {
        return [
            'topic_id' => ['required', 'integer', Rule::exists((new Topic())->getTable(), 'id')],
            'stat' => ['required', 'string', Rule::in(config('reports.stats')[Topic::class] ?? [])],
        ];
    }

    public function getStat(): string
    {
        return $this->get('stat');
    }
}
