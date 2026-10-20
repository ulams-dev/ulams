<?php

namespace Ulams\LearnerInsights\Remediation;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LearnerInsights\Jobs\GenerateRemediationJob;
use Ulams\LearnerInsights\Models\Intervention;
use Ulams\LearnerInsights\Models\Remediation;
use Ulams\LearnerInsights\Models\RemediationAssignment;
use Ulams\LearnerInsights\Services\Eligibility;
use Ulams\LearnerInsights\Services\Privacy;

/**
 * Turns a LearnerStruggling event into personal help (plan 7.2): finds or creates the cached
 * remediation of the struggle pattern, queues its generation on a miss and assigns it to the
 * learner with the reasons. The pattern key holds the element version and the kind of help, never
 * anything about the learner.
 */
class RemediationService
{
    /** @var array<string,string[]> rule => kinds of help */
    public const KINDS = [
        'failed_attempts' => ['explanation', 'practice'],
        'slow_element' => ['explanation', 'worked_example'],
        'abandoned_element' => ['explanation', 'worked_example'],
        'repetition' => ['worked_example'],
    ];

    public function __construct(private readonly Eligibility $eligibility, private readonly Privacy $privacy, private readonly LlmClient $llm)
    {
    }

    /** @param array<int,array> $reasons reason arrays of the status @return RemediationAssignment[] */
    public function onStruggling(int $userId, int $courseId, string $elementKey, array $reasons): array
    {
        if (!config('learner_insights.remediation.enabled') || !$this->eligibility->allowed($userId) || !$this->llm->enabled()) {
            return [];
        }
        if (!$this->privacy->settings($userId)['help'] || $this->hiddenForCourse($userId, $courseId)) {
            return [];
        }
        $rule = (string) ($reasons[0]['rule'] ?? '');
        $kinds = self::KINDS[$rule] ?? [];
        $element = ctype_alnum($elementKey) && strlen($elementKey) === 26 ? ElementContext::forElement($elementKey) : null;
        if ($kinds === [] || $element === null) {
            return [];
        }

        if ($this->inHoldout($userId)) {
            // the control group: no help, but the outcome is measured the same way (detail `holdout`)
            if (!Intervention::query()->where('user_id', $userId)->where('course_id', $courseId)->where('element_key', $elementKey)->where('detail', 'holdout')->where('outcome', Intervention::PENDING)->exists()) {
                Intervention::query()->create(['user_id' => $userId, 'course_id' => $courseId, 'element_key' => $elementKey, 'kind' => Intervention::REMEDIATION, 'outcome' => Intervention::PENDING, 'detail' => 'holdout', 'window_days' => (int) config('learner_insights.interventions.window_days'), 'created_at' => CarbonImmutable::now()]);
            }

            return [];
        }

        $assignments = [];
        foreach ($kinds as $kind) {
            $key = $this->patternKey($element, $rule, $kind);
            $remediation = Remediation::query()->where('pattern_key', $key)->where('kind', $kind)->first();
            $generate = false;
            if ($remediation === null) {
                if ($this->generationsToday($userId) >= (int) config('learner_insights.remediation.per_learner_day')) {
                    continue;
                }
                $remediation = Remediation::query()->create([
                    'course_id' => $courseId, 'topic_id' => $element->topicId, 'session_id' => $element->sessionId, 'element_id' => $element->elementId,
                    'applied_version_id' => $element->appliedVersionId, 'pattern_key' => $key, 'kind' => $kind, 'language' => $element->language,
                    'status' => Remediation::GENERATING, 'created_at' => CarbonImmutable::now(),
                ]);
                $generate = true;
            } elseif ($remediation->status === Remediation::FAILED && $this->mayRetry($remediation)) {
                $remediation->forceFill(['status' => Remediation::GENERATING, 'flags' => null])->save();
                $generate = true;
            }
            if (in_array($remediation->status, [Remediation::FAILED, Remediation::RETIRED], true)) {
                continue;
            }

            $assignment = RemediationAssignment::query()->firstOrCreate(
                ['user_id' => $userId, 'remediation_id' => $remediation->id],
                ['element_key' => $elementKey, 'reasons' => $reasons, 'new_generation' => $generate, 'created_at' => CarbonImmutable::now()],
            );
            if ($assignment->wasRecentlyCreated) {
                Intervention::query()->create([
                    'user_id' => $userId, 'course_id' => $courseId, 'element_key' => $elementKey, 'kind' => Intervention::REMEDIATION,
                    'ref_id' => $assignment->id, 'outcome' => Intervention::PENDING, 'window_days' => (int) config('learner_insights.interventions.window_days'), 'created_at' => CarbonImmutable::now(),
                ]);
            }
            $assignments[] = $assignment;
            if ($generate) {
                GenerateRemediationJob::dispatch($remediation->id);
            }
        }

        return $assignments;
    }

    /** A stable share of learners (by a hash of their id, never random per event) is kept out of personal help to measure its effect. */
    public function inHoldout(int $userId): bool
    {
        $percent = (int) config('learner_insights.remediation.holdout_percent');

        return $percent > 0 && (crc32('holdout:' . $userId) % 100) < $percent;
    }

    /** sha256 of element, version, rule, kind, language and the pattern detail (never learner data). */
    public function patternKey(ElementContext $element, string $rule, string $kind, string $detail = ''): string
    {
        return hash('sha256', implode('|', [$element->elementId, $element->appliedVersionId, $rule, $kind, $element->language, $detail]));
    }

    /** Remediations whose element version is no longer the applied one stop being served. */
    public function retireOutdated(): int
    {
        $n = 0;
        foreach (Remediation::query()->whereIn('status', [Remediation::READY, Remediation::GENERATING])->get()->groupBy('session_id') as $sessionId => $rows) {
            $applied = Session::query()->whereKey($sessionId)->value('applied_version_id');
            foreach ($rows as $row) {
                if ($row->applied_version_id !== $applied) {
                    $row->forceFill(['status' => Remediation::RETIRED])->save();
                    $n++;
                }
            }
        }

        return $n;
    }

    private function generationsToday(int $userId): int
    {
        return RemediationAssignment::query()->where('user_id', $userId)->where('new_generation', true)->where('created_at', '>=', CarbonImmutable::now()->startOfDay())->count();
    }

    private function mayRetry(Remediation $r): bool
    {
        return $r->created_at->addHours((int) config('learner_insights.remediation.retry_after_hours'))->isPast();
    }

    private function hiddenForCourse(int $userId, int $courseId): bool
    {
        return DB::table('user_settings')->where('user_id', $userId)->where('key', "insights.help.$courseId")->whereIn('value', ['false', '0', 'off'])->exists();
    }
}
