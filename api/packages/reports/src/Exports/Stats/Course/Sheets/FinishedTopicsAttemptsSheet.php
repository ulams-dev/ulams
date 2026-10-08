<?php

namespace EscolaLms\Reports\Exports\Stats\Course\Sheets;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FinishedTopicsAttemptsSheet extends FinishedTopicsSheet
{
    public function collection(): Collection
    {
        return $this->stats->map(function ($stat) {
            $email = collect(Arr::get($stat, 'email'));

            $topics = collect(Arr::get($stat, 'topics'))
                ->map(function (array $topic) {
                    return Arr::get($topic, 'attempt', 0) + 1;
                });

            return $email->concat($topics);
        });
    }

    public function title(): string
    {
        return __('Attempts');
    }
}
