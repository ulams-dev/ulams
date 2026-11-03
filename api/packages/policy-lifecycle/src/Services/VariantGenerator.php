<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\CourseBuilder\Exceptions\BuilderException;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Services\RunService;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * Drives one Course Builder session per department up to the outline review (ADR 0140). Each department gets its own
 * copy of the policy file as a source, so its lessons and quiz questions cite the same policy text, and its own
 * brief from the policy's duration, tone and language plus the department's seniority and prior experience.
 *
 * Departments run one after the other: a builder author may have two AI runs at a time, and the owner reviews the
 * outlines in the Studio anyway. Everything after the outline (approve, generate, apply, publish) is the Course
 * Builder's own review, unchanged: AI proposes, the owner approves.
 */
final class VariantGenerator
{
    /** Brief level of each seniority (the builder's brief names). */
    private const LEVEL = ['junior' => 'beginner', 'mid' => 'intermediate', 'senior' => 'advanced', 'mixed' => 'intermediate'];

    private const EXPERIENCE_TEXT = [
        'none' => 'no prior experience',
        'some' => 'some prior experience',
        'expert' => 'expert-level experience',
    ];

    public function __construct(
        private readonly RunService $runs,
        private readonly SourceIngestor $ingestor,
        private readonly PolicyService $policies,
        private readonly PolicyAudit $audit,
        private readonly LlmClient $llm,
    ) {
    }

    /**
     * Requests generation for the variants of the policy's first (initial) version that have no session yet, and
     * starts the first one. Safe to call again: variants already generating or generated are left alone.
     *
     * @param array<int,string>|null $variantIds limit to these variants (default: all that wait)
     *
     * @return array<int,PolicyVariant> the variants requested now
     *
     * @throws PolicyException AI is switched off, or there is nothing to generate
     */
    public function request(Policy $policy, ?array $variantIds = null): array
    {
        if (!$this->llm->enabled()) {
            throw new PolicyException('AI features are disabled on this installation, so variants cannot be generated.', 503);
        }
        $version = PolicyVersion::query()->where('policy_id', $policy->id)->where('change_class', PolicyVersion::INITIAL)->orderBy('number')->first();
        if ($version === null) {
            throw new PolicyException('The policy has no uploaded file.');
        }
        $requested = [];
        foreach ($policy->variants as $variant) {
            if ($variant->session_id !== null || $variant->generation_requested_at !== null) {
                continue;
            }
            if ($variantIds !== null && !in_array($variant->id, $variantIds, true)) {
                continue;
            }
            $variant->forceFill(['generation_requested_at' => now()])->save();
            $requested[] = $variant;
        }
        if ($requested === []) {
            throw new PolicyException('Every department is already generating or generated.');
        }
        $this->audit->record('policy.variants.requested', $policy, $version, ['departments' => array_map(fn (PolicyVariant $v) => $v->department_label, $requested)]);
        $this->advance($policy->refresh());

        return $requested;
    }

    /**
     * Starts the next waiting department when none of the policy's sessions has an AI run in flight.
     *
     * @param string|null $exceptRunId a run that is just ending (its status may not be written yet)
     */
    public function advance(Policy $policy, ?string $exceptRunId = null): ?PolicyVariant
    {
        $sessions = PolicyVariant::query()->where('policy_id', $policy->id)->whereNotNull('session_id')->pluck('session_id');
        $busy = Run::query()->whereIn('session_id', $sessions)->whereIn('status', ['queued', 'running'])
            ->when($exceptRunId !== null, fn ($q) => $q->where('id', '!=', $exceptRunId))->exists();
        if ($busy) {
            return null;
        }
        $next = PolicyVariant::query()->where('policy_id', $policy->id)->whereNull('session_id')->whereNotNull('generation_requested_at')
            ->orderBy('position')->orderBy('created_at')->first();
        if ($next === null) {
            return null;
        }
        try {
            $this->start($policy, $next);
        } catch (BuilderException $e) {
            // the author's own builder work holds the AI slots: leave the department waiting; the next run end tries again
            report($e);
        }

        return $next;
    }

    private function start(Policy $policy, PolicyVariant $variant): void
    {
        $version = PolicyVersion::query()->where('policy_id', $policy->id)->where('change_class', PolicyVersion::INITIAL)->orderBy('number')->firstOrFail();
        $session = DB::transaction(function () use ($policy, $variant, $version) {
            $session = Session::query()->create([
                'author_id' => $policy->owner_user_id,
                'title' => mb_substr($policy->title . ' / ' . $variant->department_label, 0, 255),
                'status' => Session::DRAFT,
            ]);
            $source = $this->ingestor->store($session, $this->policies->fileOf($version));
            $variant->forceFill(['session_id' => $session->id, 'source_id' => $source->id])->save();

            return [$session, $source];
        });
        [$builderSession, $source] = $session;
        $this->runs->start($builderSession, 'ingest', ['sourceId' => $source->id], (int) $policy->owner_user_id);
    }

    /**
     * Called when a pipeline run of a session ends. After the interview has been prepared, answers it from the
     * department's brief; after any run, lets the next waiting department start.
     */
    public function onRunEnded(string $sessionId, string $runId, string $kind, bool $finished): void
    {
        $variant = PolicyVariant::query()->where('session_id', $sessionId)->first();
        if ($variant === null) {
            return;
        }
        $policy = Policy::query()->find($variant->policy_id);
        if ($policy === null) {
            return;
        }
        if ($finished && $kind === 'interview') {
            $this->answerInterview($policy, $variant, Session::query()->findOrFail($sessionId));
        }
        $this->advance($policy, $runId);
    }

    /**
     * Answers the builder's interview from the policy's and the department's brief, and lets the builder decide the
     * rest (pricing: free, theme: unchanged). The last answer starts the outline run. The model's own answer
     * options stay the defaults the owner can still change in the brief editor before the outline is approved.
     */
    private function answerInterview(Policy $policy, PolicyVariant $variant, Session $session): void
    {
        if ($session->status !== Session::INTERVIEWING) {
            return;
        }
        $shared = (array) $policy->brief;
        $own = (array) $variant->brief;
        $seniority = (string) ($own['seniority'] ?? 'mixed');
        $experience = (string) ($own['priorExperience'] ?? 'some');
        $answers = [
            // the course's target group is stored in 100 characters
            'audience' => mb_substr(sprintf('%s staff, %s, %s', $variant->department_label, $seniority, self::EXPERIENCE_TEXT[$experience] ?? self::EXPERIENCE_TEXT['some']), 0, 100),
            'level' => self::LEVEL[$seniority] ?? 'intermediate',
            'duration' => ['totalMinutes' => (int) ($own['totalMinutes'] ?? $shared['totalMinutes'] ?? 30), 'lessonMinutes' => (int) ($shared['lessonMinutes'] ?? 10)],
            'tone' => (string) ($shared['tone'] ?? 'professional'),
            'assessments' => ['quiz', 'final'],
            'language' => (string) ($shared['language'] ?? 'en'),
        ];
        $userId = (int) $policy->owner_user_id;
        foreach ($answers as $key => $value) {
            $this->runs->action($session->refresh(), ['name' => 'answer', 'surfaceId' => 'interview', 'context' => ['key' => $key, 'value' => $value]], $userId);
        }
        $session->refresh();
        $session->brief = array_merge((array) $session->brief, ['notes' => mb_substr(sprintf(
            'Compliance training on the policy "%s" for the %s department. Teach what the policy requires of this audience and cite the policy for every claim; do not add rules the policy does not state.',
            $policy->title,
            $variant->department_label,
        ), 0, 2000)]);
        $session->save();
        $this->runs->action($session, ['name' => 'decide_for_me', 'surfaceId' => 'interview', 'context' => []], $userId);
    }

    /**
     * Where a department's generation stands, from the builder session itself (no copy of the status is kept):
     * waiting, ingesting, interview, outline, outline_review, generating, apply_review, applying, applied or failed.
     */
    public function stage(PolicyVariant $variant): string
    {
        if ($variant->session_id === null) {
            return $variant->generation_requested_at !== null ? 'waiting' : 'not_started';
        }
        $session = Session::query()->find($variant->session_id);

        return $session === null ? 'failed' : ($session->status === Session::FAILED ? 'failed' : $session->status);
    }
}
