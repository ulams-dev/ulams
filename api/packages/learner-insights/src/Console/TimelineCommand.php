<?php

namespace Ulams\LearnerInsights\Console;

use Illuminate\Console\Command;
use Ulams\CourseBuilder\Services\ElementLookup;
use Ulams\LearnerInsights\Models\Signal;

/** Prints a learner's journey through a course with element labels (support and demos). */
class TimelineCommand extends Command
{
    protected $signature = 'learner-insights:timeline {--user= : User id} {--course= : Course id} {--limit=200}';

    protected $description = 'Print the learner signals of one learner in one course, with element labels';

    public function handle(ElementLookup $lookup): int
    {
        if (!$this->option('user') || !$this->option('course')) {
            $this->error('Both --user and --course are required.');

            return self::INVALID;
        }

        $signals = Signal::query()->where('user_id', (int) $this->option('user'))->where('course_id', (int) $this->option('course'))
            ->orderBy('occurred_at')->orderBy('id')->limit((int) $this->option('limit'))->get();

        $this->table(['When', 'Type', 'Element', 'Value', 'Data', 'Source'], $signals->map(function (Signal $s) use ($lookup) {
            $label = $s->element_id && $s->session_id ? $lookup->label($s->session_id, $s->element_id) : null;

            return [
                $s->occurred_at->format('Y-m-d H:i:s'),
                $s->type,
                $label ?? $s->element_key ?? '-',
                $s->value ?? '-',
                $s->data ? json_encode($s->data) : '-',
                $s->source,
            ];
        })->all());
        $this->line($signals->count() . ' signals');

        return self::SUCCESS;
    }
}
