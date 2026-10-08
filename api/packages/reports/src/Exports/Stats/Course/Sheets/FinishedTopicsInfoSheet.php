<?php

namespace EscolaLms\Reports\Exports\Stats\Course\Sheets;

use EscolaLms\Courses\Models\Topic;
use Illuminate\Support\Collection;

class FinishedTopicsInfoSheet extends FinishedTopicsSheet
{
    public string $firstHeader = 'Parameter';

    public function collection(): Collection
    {
        $lengthCollection = collect(__('Length'));
        $pageCountCollection = collect(__('Page count'));
        $canSkipCollection = collect(__('Can skip'));

        if ($this->stats->isEmpty()) {
            return collect([$lengthCollection, $pageCountCollection, $canSkipCollection]);
        }

        $ids = collect($this->stats->first())->get('topics')->pluck('id');
        $topics = Topic::whereIn('id', $ids)->with('topicable')->orderBy('id')->get();

        foreach ($topics as $topic) {
            $lengthCollection->push($topic->topicable?->length);
            $pageCountCollection->push($topic->topicable?->page_count);
            $canSkipCollection->push(__($topic->can_skip));
        }

        return collect([$lengthCollection, $pageCountCollection, $canSkipCollection]);
    }

    public function title(): string
    {
        return __('Topic info');
    }
}
