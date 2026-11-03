<?php

namespace Ulams\LearnerInsights\Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Scoring\CohortStats;
use Ulams\LearnerInsights\Scoring\CourseStructure;
use Ulams\LearnerInsights\Scoring\LearnerCourseContext;
use Ulams\LearnerInsights\Scoring\SignalWindow;
use Ulams\LearnerInsights\Scoring\Thresholds;
use Ulams\LearnerInsights\Signals\SignalType;

/** Builds scorer contexts from plain arrays, without a database. */
final class Ctx
{
    public const NOW = '2026-11-20 12:00:00';

    /** @var Signal[] */
    private array $signals = [];

    private bool $finished = false;

    private bool $enrolled = true;

    private array $cohort = [];

    private array $overrides = [];

    private ?array $rules = null;

    public static function learner(): self
    {
        return new self();
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::NOW);
    }

    public function signal(string $type, string $element, int $daysAgo, array $data = [], ?float $value = null): self
    {
        $s = new Signal(['user_id' => 1, 'course_id' => 1, 'topic_id' => 1, 'element_key' => $element, 'type' => $type, 'value' => $value, 'data' => $data ?: null, 'source' => 'progress', 'source_ref' => (string) count($this->signals)]);
        $s->occurred_at = $this->now()->subDays($daysAgo);
        $this->signals[] = $s;

        return $this;
    }

    public function wrongAnswer(string $element, int $daysAgo, int $question = 3): self
    {
        return $this->signal(SignalType::QUIZ_ANSWER, $element, $daysAgo, ['question_id' => $question, 'correct' => false], 0.0);
    }

    public function rightAnswer(string $element, int $daysAgo, int $question = 3): self
    {
        return $this->signal(SignalType::QUIZ_ANSWER, $element, $daysAgo, ['question_id' => $question, 'correct' => true], 1.0);
    }

    public function finished(): self
    {
        $this->finished = true;

        return $this;
    }

    public function unenrolled(): self
    {
        $this->enrolled = false;

        return $this;
    }

    public function cohort(string $element, float $median, int $learners): self
    {
        $this->cohort[$element] = ['median_seconds' => $median, 'learners' => $learners];

        return $this;
    }

    public function override(string $rule, string $param, mixed $value): self
    {
        $this->overrides[$rule][$param] = $value;

        return $this;
    }

    public function build(): LearnerCourseContext
    {
        $labels = [];
        foreach ($this->signals as $s) {
            $labels[$s->element_key] = 'Rebasing onto main';
        }
        $signals = Collection::make($this->signals)->sortBy('occurred_at')->values();
        $config = require __DIR__ . '/../../config/learner_insights.php';

        return new LearnerCourseContext(
            1, 1, $this->now(), new SignalWindow($signals),
            new CourseStructure($this->enrolled, $this->finished, $this->now()->subDays(30), $labels),
            new CohortStats($this->cohort),
            Thresholds::make($config['rules'], $this->overrides),
        );
    }
}
