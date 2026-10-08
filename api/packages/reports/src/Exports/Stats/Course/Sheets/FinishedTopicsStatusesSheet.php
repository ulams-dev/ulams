<?php

namespace EscolaLms\Reports\Exports\Stats\Course\Sheets;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FinishedTopicsStatusesSheet extends FinishedTopicsSheet
{
    public function collection(): Collection
    {
        return $this->stats->map(function ($stat) {
            $email = collect(Arr::get($stat, 'email'));

            $topics = collect(Arr::get($stat, 'topics'))
                ->map(function (array $topic) {
                    return $this->getStatus(Arr::get($topic, 'started_at'), Arr::get($topic, 'finished_at'));
                });

            return $email->concat($topics);
        });
    }

    public function title(): string
    {
        return __('Statuses');
    }

    private function getStatus(?string $startedAt, ?string $finishedAt): int
    {
        if ($startedAt && $finishedAt) {
            return 2;
        }

        return $startedAt ? 1 : 0;
    }
}
