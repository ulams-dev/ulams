<?php

namespace Ulams\CourseBuilder\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Events\SourceIngested;
use Ulams\CourseBuilder\Exceptions\BuilderException;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Jobs\RunJob;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Pipeline\GenerationService;
use Ulams\CourseBuilder\Pipeline\InterviewService;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\CourseBuilder\Pipeline\OutlineService;
use Ulams\CourseBuilder\Pipeline\PatchService;
use Ulams\CourseBuilder\Pipeline\VariantOutlineService;
use Ulams\CourseBuilder\Ui\Surfaces;

/**
 * Every builder interaction is an AG-UI run. Long work (LLM calls, ingestion, apply) runs in a
 * queued job; quick UI actions (an interview answer) finish inside the request. UI actions are
 * checked against the surface the server issued: an unknown or closed surface gets a text reply.
 */
final class RunService
{
    /** `variants` is "give me options" for one element; `variant_plan` drafts the outlines of the course variants by level and role (ADR 0192). */
    public const LLM_KINDS = ['interview', 'outline', 'generate', 'patch', 'variants', 'variant_plan'];

    /**
     * Run handlers contributed by other packages (Living Course). A run whose `input.handler`
     * (or, failing that, `kind`) names a registered handler is executed by it; a handler that
     * returns false keeps the run open (it finishes the run itself, e.g. after queued steps).
     *
     * @var array<string,\Closure(Run,Session):mixed>
     */
    private static array $handlers = [];

    /** @param \Closure(Run,Session):mixed $handler */
    public static function extend(string $name, \Closure $handler): void
    {
        self::$handlers[$name] = $handler;
    }

    /** @var array<string,\Closure(Step):void> */
    private static array $retries = [];

    /** @param \Closure(Step):void $retry how a failed step of runs of this handler is retried */
    public static function extendRetry(string $name, \Closure $retry): void
    {
        self::$retries[$name] = $retry;
    }

    /** Retries one failed step of a run (generation steps, or the steps of a registered handler). */
    public function retryStep(Step $step): void
    {
        $handler = $step->run->input['handler'] ?? null;
        if ($handler !== null && isset(self::$retries[$handler])) {
            (self::$retries[$handler])($step);

            return;
        }
        $this->generation->retry($step);
    }

    public function finish(Run $run): void
    {
        $run->forceFill(['status' => 'finished', 'finished_at' => now()])->save();
        $this->events->runFinished($run);
    }

    public function __construct(
        private readonly EventLog $events,
        private readonly Surfaces $surfaces,
        private readonly SourceIngestor $ingestor,
        private readonly InterviewService $interview,
        private readonly OutlineService $outline,
        private readonly GenerationService $generation,
        private readonly PatchService $patches,
        private readonly VersionService $versions,
        private readonly BlueprintApplier $applier,
        private readonly Llm $llm,
        private readonly VariantOutlineService $variantOutlines,
        private readonly VariantService $variants,
    ) {
    }

    /** Creates and queues a run. */
    public function start(Session $session, string $kind, array $input, ?int $userId): Run
    {
        if (in_array($kind, self::LLM_KINDS, true) && !$this->llm->enabled()) {
            throw new BuilderException('AI features are disabled on this installation.', 503);
        }
        if (in_array($kind, self::LLM_KINDS, true) && !isset($input['variantId'])) {
            // the generation of a variant is bounded by the variant cap, the lesson window and the session budget instead
            $active = Run::query()->whereIn('kind', self::LLM_KINDS)->whereIn('status', ['queued', 'running'])
                ->whereIn('session_id', Session::query()->where('author_id', $session->author_id)->select('id'))->get()
                ->filter(fn (Run $r) => !isset($r->input['variantId']))->count();
            if ($active >= (int) config('course_builder.limits.concurrent_runs_per_author', 2)) {
                throw new BuilderException('You already have AI work running. Wait for it to finish, then try again.', 429);
            }
        }
        $run = Run::query()->create(['session_id' => $session->id, 'kind' => $kind, 'status' => 'queued', 'input' => $input, 'user_id' => $userId]);
        RunJob::dispatchFor($run->id);

        return $run;
    }

    public function execute(Run $run): void
    {
        $run->refresh();
        if (!in_array($run->status, ['queued'], true)) {
            return;
        }
        $session = $run->session;
        if ($run->kind === 'generate') {
            $this->guard($run, fn () => $this->generation->start($session, $run));

            return;
        }
        $run->forceFill(['status' => 'running', 'started_at' => now()])->save();
        $this->events->runStarted($run);
        $open = false;
        $ok = $this->guard($run, function () use ($run, $session, &$open) {
            $handler = self::$handlers[(string) ($run->input['handler'] ?? $run->kind)] ?? null;
            if ($handler !== null) {
                $open = $handler($run, $session) === false;

                return;
            }
            match ($run->kind) {
                'ingest' => $this->ingest($session, $run),
                'interview' => $this->interview->start($session, $run),
                'outline' => $this->outline->generate(
                    $session,
                    $run,
                    $run->input['comment'] ?? null,
                    isset($run->input['previousVersionId']) ? Version::query()->find($run->input['previousVersionId']) : null,
                ),
                'patch' => $this->patches->propose($session, $run, (string) $run->input['elementId'], (string) $run->input['message']),
                'variants' => $this->patches->variants($session, $run, (string) $run->input['elementId'], (string) $run->input['message'], (int) ($run->input['count'] ?? 2)),
                'variant_plan' => $this->variantPlan($session, $run),
                'apply' => $this->apply($session, $run),
                default => throw new InvalidArgumentException("Unknown run kind {$run->kind}"),
            };
        });
        if ($ok && !$open) {
            $this->finish($run);
        }
    }

    /** Runs the work; turns failures into RUN_ERROR with a readable message. */
    private function guard(Run $run, callable $work): bool
    {
        try {
            $work();

            return true;
        } catch (LlmException $e) {
            $this->fail($run, $e->getMessage(), null, $e->reason);
        } catch (InvalidArgumentException|BuilderException|RuntimeException $e) {
            $this->fail($run, $e->getMessage(), $e);
        }

        return false;
    }

    public function fail(Run $run, string $message, ?Throwable $e = null, string $code = 'error'): void
    {
        if ($e !== null && !$e instanceof InvalidArgumentException && !$e instanceof BuilderException) {
            report($e);
        }
        $run->forceFill(['status' => 'failed', 'error' => mb_substr($message, 0, 2000), 'finished_at' => now()])->save();
        $session = $run->session;
        if (in_array($run->kind, ['interview', 'outline'], true) && $session->status !== Session::OUTLINE_REVIEW) {
            $session->putState('retry', ['kind' => $run->kind, 'input' => $run->input]);
            $session->save();
        }
        $this->events->text($session, $run, $message);
        $this->events->runError($run, $message, $code);
    }

    private function ingest(Session $session, Run $run): void
    {
        $source = Source::query()->where('session_id', $session->id)->findOrFail($run->input['sourceId']);
        $source->forceFill(['status' => 'processing'])->save();
        $session->forceFill(['status' => Session::INGESTING])->save();
        $this->events->stepStarted($run, 'ingest');
        $this->surfaces->source($session, $run, $source);
        try {
            $this->ingestor->ingest($source);
        } catch (RuntimeException $e) {
            $source->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            $this->surfaces->source($session, $run, $source);
            $session->forceFill(['status' => Session::DRAFT])->save();

            throw $e;
        }
        $source->refresh();
        $total = (int) $session->sources()->where('status', 'ready')->sum('token_estimate');
        $limit = (int) config('course_builder.limits.source_tokens', 400000);
        if ($total > $limit) {
            $source->forceFill(['status' => 'failed', 'error' => sprintf('The sources add up to about %d tokens; the limit is %d. Upload a shorter document or split it.', $total, $limit)])->save();
            $this->surfaces->source($session, $run, $source);
            $session->forceFill(['status' => Session::DRAFT])->save();

            throw new BuilderException((string) $source->error, 422);
        }
        $this->surfaces->source($session, $run, $source);
        $this->events->stepFinished($run, 'ingest');
        event(new SourceIngested($source));
        if ($session->title === null) {
            $session->title = mb_substr((string) ($source->metadata['title'] ?? $source->original_name), 0, 255);
        }
        $session->status = Session::DRAFT;
        $session->save();
        $this->events->stateDelta($session, $run, [['op' => 'replace', 'path' => '/sources', 'value' => SessionState::sources($session)]]);

        // the interview starts by itself after the first source
        if ($session->stateValue('interview') === null && $this->llm->enabled()) {
            $this->start($session, 'interview', [], $run->user_id);
        }
    }

    private function apply(Session $session, Run $run): void
    {
        $version = Version::query()->where('session_id', $session->id)->findOrFail($run->input['versionId']);
        if (isset($run->input['variantId'])) {
            $this->applyVariant($session, $run, $version);

            return;
        }
        $author = $session->author;
        if (!$author instanceof Authenticatable) {
            throw new BuilderException('The session author no longer exists.', 409);
        }
        $session->forceFill(['status' => Session::APPLYING])->save();
        $this->events->stepStarted($run, 'apply');
        if ($version->status === Version::PROPOSED) {
            $this->versions->approve($version, $run->user_id);
            $this->versions->setCurrent($session, $version);
        }
        $first = $session->course_id === null;
        $plan = $this->applier->plan($session, $version->document);
        $courseId = $this->applier->apply($session, $version, $author, (bool) ($run->input['overwrite'] ?? false));
        $session->forceFill(['course_id' => $courseId, 'applied_version_id' => $version->id, 'status' => Session::APPLIED])->save();
        $this->surfaces->apply($session, $run, $version, ['courseId' => $courseId] + $plan, 'applied');
        $this->events->stepFinished($run, 'apply');
        $this->events->custom($session, $run, 'applied', ['courseId' => $courseId, 'versionId' => $version->id] + SessionState::links($session));
        $this->events->text($session, $run, $first
            ? 'The course is in your academy as an unpublished draft. Open it in the admin, preview it as a learner, or keep refining here; publish when you are ready.'
            : 'The change is applied to the course.');
        $this->events->stateDelta($session, $run, [['op' => 'replace', 'path' => '/session', 'value' => SessionState::summary($session)]]);
        $this->planVariantsAfterApply($session, $run);
    }

    /**
     * Applies an approved variant as its own LMS course and groups it with the base course as a family (ADR 0192).
     * The base course must be applied first: the family hangs on it.
     */
    private function applyVariant(Session $session, Run $run, Version $version): void
    {
        $variant = Variant::query()->where('session_id', $session->id)->findOrFail($run->input['variantId']);
        $author = $session->author;
        if (!$author instanceof Authenticatable) {
            throw new BuilderException('The session author no longer exists.', 409);
        }
        if ($session->course_id === null) {
            throw new BuilderException('Apply the base course first: the variants are grouped under it.', 409);
        }
        if ($version->variant_id !== $variant->id) {
            throw new BuilderException('That version does not belong to this variant.', 409);
        }
        $this->events->stepStarted($run, 'apply');
        if ($version->status === Version::PROPOSED) {
            $this->versions->approve($version, $run->user_id);
        }
        $variant->forceFill(['current_version_id' => $version->id])->save();
        $first = $variant->course_id === null;
        $courseId = $this->applier->apply($session, $version, $author, (bool) ($run->input['overwrite'] ?? false), null, $variant);
        $variant->forceFill(['course_id' => $courseId, 'applied_version_id' => $version->id, 'status' => Variant::APPROVED])->save();
        $this->applier->registerFamily($session, $variant, $author);
        $this->surfaces->variantReview($session, $run, $variant, $version, Version::query()->find($variant->based_on_version_id), 'approved');
        $this->events->stepFinished($run, 'apply');
        $this->events->custom($session, $run, 'applied', ['courseId' => $courseId, 'versionId' => $version->id, 'variantId' => $variant->id, 'variantKey' => $variant->key]);
        $this->events->text($session, $run, $first
            ? sprintf('%s is in your academy as its own unpublished course, grouped with the base course. Publishing the base course publishes the family.', $variant->label)
            : sprintf('The change is applied to the course of %s.', $variant->label));
    }

    /** After the base course is applied for the first time, drafts the outlines of the audiences the interview asked for. */
    private function planVariantsAfterApply(Session $session, Run $run): void
    {
        if (empty($session->brief['audiences']) || Variant::query()->where('session_id', $session->id)->exists() || !$this->llm->enabled()) {
            return;
        }
        try {
            $made = $this->variants->fromBrief($session);
            $this->events->text($session, $run, sprintf('You asked for %d more versions of this course. I am drafting an outline for each from the same sources; you review each one as a list of changes against this course before any lesson is written.', count($made)));
            $this->start($session, 'variant_plan', ['variants' => []], $run->user_id);
        } catch (BuilderException $e) {
            $this->events->text($session, $run, 'I could not start the variants yet: ' . $e->getMessage());
        }
    }

    /**
     * Drafts the outline of every variant that has none yet (or of the ones named in `input.variants`, with the
     * author's comment) as a proposal the author reviews against the base outline. One failing variant does not stop
     * the others; when none succeeds the run fails with the first error and can be retried.
     */
    private function variantPlan(Session $session, Run $run): void
    {
        $this->variantOutlines->base($session);
        $keys = array_values(array_filter((array) ($run->input['variants'] ?? []), 'is_string'));
        $query = Variant::query()->where('session_id', $session->id)->where('status', '!=', Variant::RETIRED)->orderBy('position');
        // naming a variant asks for a new plan, but never for one that already has its own course
        $keys !== [] ? $query->whereIn('key', $keys)->whereNull('course_id') : $query->where('status', Variant::DRAFT)->whereNull('current_version_id');
        $previous = isset($run->input['previousVersionId']) ? Version::query()->where('session_id', $session->id)->find($run->input['previousVersionId']) : null;
        $comment = isset($run->input['comment']) ? (string) $run->input['comment'] : null;
        $done = 0;
        $failure = null;
        foreach ($query->get() as $variant) {
            $this->events->stepStarted($run, "variant:{$variant->key}");
            try {
                $this->variantOutlines->propose($session, $run, $variant, $comment, $previous);
                $done++;
            } catch (LlmException $e) {
                $failure ??= $e;
                $this->events->text($session, $run, sprintf('The outline for %s failed: %s Retry it from the variants list; the others are not affected.', $variant->label, $e->getMessage()));
            }
            $this->events->stepFinished($run, "variant:{$variant->key}");
        }
        if ($done === 0 && $failure !== null) {
            throw $failure;
        }
    }

    /** Re-applies the current version after an approved patch, undo, redo or restore. */
    public function reapply(Session $session, ?int $userId): ?Run
    {
        if ($session->course_id === null || $session->current_version_id === null) {
            return null;
        }

        return $this->start($session, 'apply', ['versionId' => $session->current_version_id, 'reapply' => true], $userId);
    }

    /**
     * Handles a RunAgentInput: an A2UI action in `forwardedProps.action` or typed text in
     * `messages`, optionally scoped to `forwardedProps.selection.elementId`.
     *
     * @return array{run:?Run,accepted:bool,message?:string}
     */
    public function handle(Session $session, array $input, int $userId): array
    {
        $action = $input['forwardedProps']['action'] ?? null;
        if (is_array($action)) {
            return $this->action($session, $action, $userId);
        }
        if (($input['forwardedProps']['stage'] ?? null) === 'variants') {
            return $this->variantRun($session, (array) ($input['forwardedProps']['variants'] ?? []), $userId);
        }
        $text = '';
        foreach (array_reverse($input['messages'] ?? []) as $message) {
            if (($message['role'] ?? '') === 'user') {
                $text = trim(is_string($message['content'] ?? null) ? $message['content'] : '');
                break;
            }
        }
        if ($text === '') {
            throw new BuilderException('Send a message or an action.', 422);
        }
        $text = mb_substr($text, 0, 4000);
        $elementId = $input['forwardedProps']['selection']['elementId'] ?? null;
        $this->events->userText($session, null, $text);

        if (is_string($elementId) && $elementId !== '' && preg_match('/\b(options|variants|alternatives|alternatively|a few versions)\b/i', $text)) {
            return ['run' => $this->start($session, 'variants', ['elementId' => $elementId, 'message' => $text, 'count' => 2], $userId), 'accepted' => true];
        }
        if (is_string($elementId) && $elementId !== '') {
            return ['run' => $this->start($session, 'patch', ['elementId' => $elementId, 'message' => $text], $userId), 'accepted' => true];
        }
        if ($session->status === Session::INTERVIEWING) {
            $open = collect($session->stateValue('interview.questions', []))->first(fn ($q) => !$q['answered']);
            if ($open !== null && in_array($open['key'], ['audience'], true)) {
                return $this->action($session, ['name' => 'answer', 'surfaceId' => 'interview', 'context' => ['key' => $open['key'], 'value' => $text]], $userId);
            }
            $session->brief = array_merge((array) $session->brief, ['notes' => mb_substr(trim(($session->brief['notes'] ?? '') . "\n" . $text), 0, 2000)]);
            $session->save();

            return $this->reply($session, 'Noted. I will take that into account. Pick an option on the card to continue.');
        }
        if ($session->status === Session::OUTLINE_REVIEW) {
            $version = $session->currentVersion;
            if ($version !== null && $version->status === Version::PROPOSED) {
                return $this->action($session, ['name' => 'reject_outline', 'surfaceId' => "outline-{$version->id}", 'context' => ['versionId' => $version->id, 'comment' => $text]], $userId);
            }
        }

        return $this->reply($session, 'Select a lesson, block or question in the course tree, then tell me what to change.');
    }

    /**
     * The `variants` stage of a run (`forwardedProps.stage`): creates the variants named in `variants` (audiences with a
     * level, a role or both; none means the ones of the brief) and drafts their outlines. Also the way to retry one.
     *
     * @param array<int,array<string,mixed>> $audiences
     *
     * @return array{run:?Run,accepted:bool,message?:string}
     */
    public function variantRun(Session $session, array $audiences, int $userId): array
    {
        $this->variantOutlines->base($session);
        $this->variants->create($session, $audiences !== [] ? $audiences : (array) ($session->brief['audiences'] ?? []));
        $keys = array_values(array_filter(array_map(fn ($a) => is_array($a) ? VariantService::keyOf($a) : '', $audiences)));

        return ['run' => $this->start($session, 'variant_plan', ['variants' => $keys], $userId), 'accepted' => true];
    }

    /** @return array{run:?Run,accepted:bool,message?:string} */
    private function reply(Session $session, string $text, bool $accepted = true): array
    {
        $this->events->text($session, null, $text);

        return ['run' => null, 'accepted' => $accepted, 'message' => $text];
    }

    /** @return array{run:?Run,accepted:bool,message?:string} */
    public function action(Session $session, array $action, int $userId): array
    {
        $name = (string) ($action['name'] ?? '');
        $surfaceId = (string) ($action['surfaceId'] ?? '');
        $context = (array) ($action['context'] ?? []);
        $expected = match ($name) {
            'answer', 'decide_for_me' => 'interview',
            'approve_outline', 'reject_outline' => 'outline',
            'approve_apply' => 'apply',
            'approve_patch', 'reject_patch' => 'patch',
            'approve_variant', 'reject_variant' => 'variant',
            'choose_variant', 'reject_variants' => 'variants',
            'retry_step' => 'progress',
            'retry' => null,
            default => throw new BuilderException("Unknown action {$name}.", 422),
        };
        if ($expected !== null) {
            $surface = $this->surfaces->openSurface($session, $surfaceId);
            // the outline and the progress of a variant are the same cards under their own kinds (ADR 0192)
            $accepted = in_array($expected, ['outline', 'progress'], true) ? [$expected, "variant_{$expected}"] : [$expected];
            if ($surface === null || !in_array($surface['kind'] ?? '', $accepted, true)) {
                return $this->reply($session, 'That card is out of date. Use the latest one in the conversation.', false);
            }
            if (isset($surface['versionId']) && isset($context['versionId']) && $context['versionId'] !== $surface['versionId']) {
                return $this->reply($session, 'That card is out of date. Use the latest one in the conversation.', false);
            }
            $context['versionId'] ??= $surface['versionId'] ?? null;
        }

        switch ($name) {
            case 'answer':
            case 'decide_for_me':
                try {
                    if ($name === 'decide_for_me' && empty($context['key'])) {
                        $this->interview->decideRest($session, null);
                        $done = true;
                    } else {
                        $done = $this->interview->answer($session, null, (string) ($context['key'] ?? ''), $context['value'] ?? null, $name === 'decide_for_me');
                    }
                } catch (InvalidArgumentException $e) {
                    return $this->reply($session, 'That answer does not fit this question. ' . $e->getMessage(), false);
                }
                if ($done) {
                    $this->surfaces->close($session, 'interview');
                    $this->events->text($session, null, 'Thanks. I am drafting the outline with learning objectives now; you will review it before any lesson is written.');
                    $session->forceFill(['status' => Session::OUTLINING])->save();

                    return ['run' => $this->start($session, 'outline', [], $userId), 'accepted' => true];
                }

                return ['run' => null, 'accepted' => true];

            case 'approve_outline':
                $version = $this->version($session, (string) $context['versionId']);
                if ($version->variant_id !== null) {
                    try {
                        $approved = $this->variantOutlines->approve($session, null, $version, array_values((array) ($context['edits'] ?? [])), $userId);
                    } catch (InvalidArgumentException $e) {
                        return $this->reply($session, $e->getMessage(), false);
                    }

                    return ['run' => $this->start($session, 'generate', ['outlineVersionId' => $approved->id, 'variantId' => $version->variant_id], $userId), 'accepted' => true];
                }
                try {
                    $approved = $this->outline->approve($session, null, $version, array_values((array) ($context['edits'] ?? [])), $userId, array_values((array) ($context['formats'] ?? [])));
                } catch (InvalidArgumentException $e) {
                    return $this->reply($session, $e->getMessage(), false);
                }

                return ['run' => $this->start($session, 'generate', ['outlineVersionId' => $approved->id], $userId), 'accepted' => true];

            case 'reject_outline':
                $version = $this->version($session, (string) $context['versionId']);
                if ($version->variant_id !== null) {
                    $this->variantOutlines->reject($session, null, $version, $userId);
                    $comment = trim((string) ($context['comment'] ?? '')) ?: 'Please propose a different plan.';
                    $variant = Variant::query()->where('session_id', $session->id)->findOrFail($version->variant_id);

                    return ['run' => $this->start($session, 'variant_plan', ['variants' => [$variant->key], 'comment' => mb_substr($comment, 0, 2000), 'previousVersionId' => $version->id], $userId), 'accepted' => true];
                }
                $this->outline->reject($session, null, $version, $userId);
                $comment = trim((string) ($context['comment'] ?? '')) ?: 'Please propose a different outline.';
                $session->forceFill(['status' => Session::OUTLINING])->save();

                return ['run' => $this->start($session, 'outline', ['comment' => mb_substr($comment, 0, 2000), 'previousVersionId' => $version->id], $userId), 'accepted' => true];

            case 'approve_apply':
                $version = $this->version($session, (string) $context['versionId']);
                $this->surfaces->apply($session, null, $version, $this->applier->plan($session, $version->document), 'applying');

                return ['run' => $this->start($session, 'apply', ['versionId' => $version->id], $userId), 'accepted' => true];

            case 'approve_variant':
                $version = $this->version($session, (string) $context['versionId']);
                if ($version->variant_id === null || $version->kind !== 'content' || $version->status !== Version::PROPOSED) {
                    return $this->reply($session, 'That variant is no longer waiting for a decision.', false);
                }
                $variant = Variant::query()->where('session_id', $session->id)->findOrFail($version->variant_id);
                if ($session->course_id === null) {
                    return $this->reply($session, 'Apply the base course first: the variants are grouped under it.', false);
                }
                $this->versions->approve($version, $userId);
                $variant->forceFill(['current_version_id' => $version->id])->save();
                $this->surfaces->variantReview($session, null, $variant, $version, Version::query()->find($variant->based_on_version_id), 'applying');

                return ['run' => $this->start($session, 'apply', ['versionId' => $version->id, 'variantId' => $variant->id], $userId), 'accepted' => true];

            case 'reject_variant':
                $version = $this->version($session, (string) $context['versionId']);
                if ($version->variant_id === null || $version->kind !== 'content' || $version->status !== Version::PROPOSED) {
                    return $this->reply($session, 'That variant is no longer waiting for a decision.', false);
                }
                $this->versions->reject($version, $userId);
                $variant = Variant::query()->where('session_id', $session->id)->findOrFail($version->variant_id);
                $this->surfaces->variantReview($session, null, $variant, $version, Version::query()->find($variant->based_on_version_id), 'rejected');
                $this->events->text($session, null, sprintf('%s was rejected; nothing was created. Ask for it again from the variants list when you are ready.', $variant->label));

                return ['run' => null, 'accepted' => true];

            case 'approve_patch':
                $version = $this->version($session, (string) $context['versionId']);
                $this->patches->approve($session, null, $version, $userId);

                return ['run' => $this->reapply($session, $userId) ?? $this->refreshApply($session), 'accepted' => true];

            case 'choose_variant':
                $version = $this->version($session, (string) ($context['versionId'] ?? ''));
                if ($version->variant_group === null || $version->status !== Version::PROPOSED || "variants-{$version->variant_group}" !== $surfaceId) {
                    return $this->reply($session, 'That option is no longer available.', false);
                }
                $this->patches->chooseVariant($session, null, $version, $userId);

                return ['run' => $this->reapply($session, $userId) ?? $this->refreshApply($session), 'accepted' => true];

            case 'reject_variants':
                $this->patches->rejectVariants($session, null, (string) ($context['group'] ?? ''), $userId);

                return ['run' => null, 'accepted' => true];

            case 'reject_patch':
                $this->patches->reject($session, null, $this->version($session, (string) $context['versionId']), $userId);

                return ['run' => null, 'accepted' => true];

            case 'retry_step':
                $step = Step::query()->whereIn('run_id', Run::query()->where('session_id', $session->id)->select('id'))->findOrFail((string) ($context['stepId'] ?? ''));
                $this->retryStep($step);

                return ['run' => $step->run, 'accepted' => true];

            case 'retry':
                $retry = $session->stateValue('retry');
                if (!is_array($retry)) {
                    return $this->reply($session, 'There is nothing to retry.', false);
                }
                $session->putState('retry', null);
                $session->save();

                return ['run' => $this->start($session, $retry['kind'], (array) $retry['input'], $userId), 'accepted' => true];
        }

        return ['run' => null, 'accepted' => false];
    }

    /** After a patch approved before the first apply: refresh the apply summary. */
    private function refreshApply(Session $session): ?Run
    {
        $version = $session->currentVersion;
        if ($version !== null && $session->status === Session::APPLY_REVIEW) {
            $this->surfaces->apply($session, null, $version, $this->applier->plan($session, $version->document));
        }

        return null;
    }

    private function version(Session $session, string $id): Version
    {
        return Version::query()->where('session_id', $session->id)->findOrFail($id);
    }
}
