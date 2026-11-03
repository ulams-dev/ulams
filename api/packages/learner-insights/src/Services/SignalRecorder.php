<?php

namespace Ulams\LearnerInsights\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ulams\LearnerInsights\Jobs\EvaluateLearnerJob;
use Ulams\LearnerInsights\Jobs\RecordSignalJob;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;

/**
 * Writes learner signals (ADR 0057). `queue()` is what listeners call: it checks the module switch
 * cheaply and hands the work to RecordSignalJob after the surrounding transaction commits.
 * `record()` runs in the job: it checks the learner's choices, resolves the element and inserts
 * idempotently on (source, source_ref, type).
 */
class SignalRecorder
{
    public function __construct(private readonly Eligibility $eligibility, private readonly ElementResolver $resolver, private readonly OutcomeTracker $outcomes)
    {
    }

    public function queue(SignalData $signal): void
    {
        if (!$this->eligibility->moduleEnabled()) {
            return;
        }

        RecordSignalJob::dispatch($signal->toArray());
    }

    /** @return bool true when a new row was written */
    public function record(SignalData $signal): bool
    {
        if (!SignalType::isKnown($signal->type)) {
            throw new InvalidArgumentException('Unknown learner signal type ' . $signal->type);
        }
        if (!in_array($signal->source, SignalType::SOURCES, true)) {
            throw new InvalidArgumentException('Unknown learner signal source ' . $signal->source);
        }
        if (!$this->eligibility->allowed($signal->userId)) {
            return false;
        }

        $element = $this->resolver->resolve($signal->courseId, $signal->topicId, $signal->questionId);
        $data = SignalType::cleanData($signal->type, $signal->data);

        $written = DB::table('learner_signals')->insertOrIgnore([
            'user_id' => $signal->userId,
            'course_id' => $element['course_id'],
            'topic_id' => $element['topic_id'],
            'element_key' => $element['element_key'],
            'element_id' => $element['element_id'],
            'session_id' => $element['session_id'],
            'type' => $signal->type,
            'value' => $signal->value,
            'data' => $data === [] ? null : json_encode($data),
            'source' => $signal->source,
            'source_ref' => mb_substr($signal->sourceRef, 0, 80),
            'occurred_at' => $signal->occurredAt->format('Y-m-d H:i:s.v'),
            'recorded_at' => now()->format('Y-m-d H:i:s.v'),
        ]) > 0;

        if ($written && $element['course_id'] !== null && !in_array($signal->source, ['backfill', 'nudge', 'remediation'], true)) {
            // progress that closes an open nudge or remediation
            if ($signal->type === SignalType::TOPIC_COMPLETED || ($signal->type === SignalType::QUIZ_ATTEMPT_FINISHED && ($data['passed'] ?? false))) {
                $this->outcomes->progressed($signal->userId, (int) $element['course_id'], $element['element_key']);
            }
            // a new signal may change the learner's status; a backfill leaves that to the nightly sweep
            EvaluateLearnerJob::dispatchDebounced($signal->userId, (int) $element['course_id']);
        }

        return $written;
    }
}
