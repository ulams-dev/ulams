<?php

namespace Ulams\CourseBuilder\Pipeline;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Apply\LandingDocument;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\ContentTypes\ContentType;
use Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry;
use Ulams\CourseBuilder\ContentTypes\H5pLibraries;
use Ulams\CourseBuilder\ContentTypes\InteractiveType;
use Ulams\CourseBuilder\ContentTypes\LiaScriptRenderer;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Jobs\StepJob;
use Ulams\CourseBuilder\Models\Critique;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Quality\CriticLoop;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Ui\Surfaces;

/**
 * Stages 3–7: lessons (in parallel, with a concurrency window), grounding check with one
 * regeneration, the format-specific parts (self-checks of a LiaScript lesson, an H5P activity or an
 * interactive from the library; a stage without steps for rich text courses), quizzes and the final
 * test, metadata; then the content version is assembled and
 * the apply proposal shown. Every unit is a step keyed by element id: a `done` step is skipped on
 * resume, a failed step can be retried alone, and the rest of the course stays usable.
 */
final class GenerationService
{
    public const STAGES = ['lessons' => 'Lessons', 'grounding' => 'Grounding check', 'interactions' => 'Interactive elements', 'quizzes' => 'Quizzes', 'critique' => 'Quality review', 'metadata' => 'Metadata & landing'];

    public function __construct(
        private readonly Llm $llm,
        private readonly PromptContext $context,
        private readonly VersionService $versions,
        private readonly Surfaces $surfaces,
        private readonly EventLog $events,
        private readonly BlueprintApplier $applier,
        private readonly PriceService $prices,
        private readonly ContentTypeRegistry $types,
        private readonly H5pLibraries $h5p,
        private readonly CriticLoop $critics,
    ) {
    }

    public function start(Session $session, Run $run): void
    {
        $outline = $this->outline($run);
        $session->status = Session::GENERATING;
        $session->save();
        $run->forceFill(['status' => 'running', 'stage' => 'lessons', 'started_at' => now()])->save();
        $this->events->runStarted($run);
        $this->events->text($session, $run, sprintf('Writing %d lessons from your source. Every block will cite the section it comes from.', Blueprint::stats($outline)['lessons']));
        $this->openStage($run, 'lessons', $outline, $session->brief);
    }

    public function outline(Run $run): array
    {
        return Version::query()->findOrFail($run->input['outlineVersionId'])->document;
    }

    /** The step keys of a stage, in dispatch order. */
    private function stageKeys(string $stage, array $outline, array $brief): array
    {
        return match ($stage) {
            'lessons' => array_map(fn ($i) => 'lesson:' . $i['lesson']['id'], iterator_to_array(Blueprint::lessons($outline), false)),
            'grounding' => array_map(fn ($i) => 'grounding:' . $i['lesson']['id'], iterator_to_array(Blueprint::lessons($outline), false)),
            'interactions' => array_values(array_filter(array_map(function ($i) {
                $follow = $this->types->forLesson($i['lesson'], true)->followUp();

                return $follow === ContentType::FOLLOW_SELF_CHECKS ? 'selfcheck:' . $i['lesson']['id'] : ($follow === ContentType::FOLLOW_INTERACTION ? 'interaction:' . $i['lesson']['id'] : null);
            }, iterator_to_array(Blueprint::lessons($outline), false)))),
            'quizzes' => [
                ...(!empty($brief['assessments']['perLessonQuiz']) ? array_map(fn ($i) => 'quiz:' . $i['lesson']['id'], iterator_to_array(Blueprint::lessons($outline), false)) : []),
                ...(!empty($brief['assessments']['finalTest']) ? ['final_test'] : []),
            ],
            'critique' => config('course_builder.quality.enabled', true) ? array_map(fn ($i) => 'critique:' . $i['lesson']['id'], iterator_to_array(Blueprint::lessons($outline), false)) : [],
            'metadata' => ['metadata'],
        };
    }

    /** @param array<int,string> $keys */
    private function createSteps(Run $run, string $stage, array $keys): void
    {
        foreach ($keys as $key) {
            Step::query()->firstOrCreate(['run_id' => $run->id, 'key' => $key], ['stage' => $stage, 'status' => 'pending']);
        }
    }

    /** Opens the first stage of a new run: creates its steps and dispatches the first window. */
    private function openStage(Run $run, string $stage, array $outline, array $brief): void
    {
        $keys = $this->stageKeys($stage, $outline, $brief);
        $run->forceFill(['stage' => $stage])->save();
        $this->events->stepStarted($run, $stage);
        $this->createSteps($run, $stage, $keys);
        $this->publishProgress($run);
        if ($keys === []) {
            $this->advance($run);

            return;
        }
        $this->dispatchWindow($run, $stage);
    }

    /**
     * Queues pending steps of a stage up to the concurrency window. The count and the claim happen
     * under the run lock, so workers finishing steps at the same moment cannot overfill the window;
     * the jobs are dispatched after the commit.
     */
    private function dispatchWindow(Run $run, string $stage): void
    {
        $limit = max(1, (int) config('course_builder.limits.lesson_concurrency', 4));
        $claimed = DB::transaction(function () use ($run, $stage, $limit) {
            Run::query()->lockForUpdate()->find($run->id);
            $running = Step::query()->where('run_id', $run->id)->where('stage', $stage)->whereIn('status', ['queued', 'running'])->count();
            $ids = [];
            foreach (Step::query()->where('run_id', $run->id)->where('stage', $stage)->where('status', 'pending')->orderBy('created_at')->orderBy('key')->limit(max(0, $limit - $running))->get() as $step) {
                if (Step::query()->whereKey($step->id)->where('status', 'pending')->update(['status' => 'queued'])) {
                    $ids[] = $step->id;
                }
            }

            return $ids;
        });
        foreach ($claimed as $id) {
            StepJob::dispatchFor($id);
        }
    }

    public function runStep(Step $step): void
    {
        $step->refresh();
        if ($step->status === 'done') {
            return;
        }
        $run = $step->run;
        if (in_array($run->status, ['cancelled', 'failed', 'finished'], true)) {
            return;
        }
        $session = $run->session;
        $step->forceFill(['status' => 'running', 'attempts' => $step->attempts + 1])->save();
        $this->publishProgress($run);

        try {
            $output = $this->execute($session, $run, $step);
            $step->forceFill(['status' => 'done', 'output' => $output['data'], 'error' => null, 'cost_micro_usd' => $step->cost_micro_usd + $output['cost']])->save();
        } catch (LlmException $e) {
            $step->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            $run->forceFill(['status' => 'needs_attention'])->save();
            $this->events->text($session, $run, $this->stepLabel($run, $step) . ' failed: ' . $e->getMessage() . ' You can retry this step; the rest of the course is not affected.');
        }
        $this->publishProgress($run);
        $this->dispatchWindow($run, $step->stage);
        $this->advance($run);
    }

    public function retry(Step $step): void
    {
        $run = $step->run;
        $step->forceFill(['status' => 'pending', 'error' => null])->save();
        if ($run->status === 'needs_attention' && !Step::query()->where('run_id', $run->id)->where('status', 'failed')->exists()) {
            $run->forceFill(['status' => 'running'])->save();
        }
        $this->publishProgress($run);
        $this->dispatchWindow($run, $step->stage);
    }

    /**
     * Moves to the next stage once every step of the current one is done. The whole transition
     * (closing the stage, creating the next stage's steps, writing `stage`) is one transaction under
     * the run lock, so workers that finish the last steps of a stage at the same moment cannot both
     * advance, and a worker that arrives late sees the next stage already open with its steps. Events
     * and job dispatches follow the commit and belong to the one worker that made the transition.
     */
    public function advance(Run $run): void
    {
        $transition = DB::transaction(function () use ($run) {
            $locked = Run::query()->lockForUpdate()->find($run->id);
            if ($locked === null || in_array($locked->status, ['cancelled', 'failed', 'finished'], true)) {
                return null;
            }
            $steps = Step::query()->where('run_id', $locked->id)->where('stage', $locked->stage)->get();
            if ($steps->contains(fn (Step $s) => $s->status !== 'done')) {
                if (!$steps->contains(fn (Step $s) => in_array($s->status, ['pending', 'queued', 'running'], true))) {
                    $locked->forceFill(['status' => 'needs_attention'])->save();
                } elseif ($locked->status === 'needs_attention' && !$steps->contains(fn (Step $s) => $s->status === 'failed')) {
                    $locked->forceFill(['status' => 'running'])->save();
                }

                return null;
            }
            $order = array_keys(self::STAGES);
            $outline = $this->outline($locked);
            $brief = (array) $locked->session->brief;
            $events = [];
            $stage = $locked->stage;
            while (true) {
                $events[] = ['finished', $stage];
                $next = $order[array_search($stage, $order, true) + 1] ?? null;
                if ($next === null) {
                    $locked->forceFill(['stage' => 'assembling', 'status' => 'running'])->save();

                    return ['events' => $events, 'open' => null];
                }
                $keys = $this->stageKeys($next, $outline, $brief);
                $locked->forceFill(['stage' => $next, 'status' => 'running'])->save();
                $events[] = ['started', $next];
                $this->createSteps($locked, $next, $keys);
                if ($keys !== []) {
                    return ['events' => $events, 'open' => $next];
                }
                $stage = $next; // a stage without steps (no quizzes asked for) closes at once
            }
        });
        if ($transition === null) {
            return;
        }
        $run->refresh();
        foreach ($transition['events'] as [$kind, $name]) {
            $kind === 'finished' ? $this->events->stepFinished($run, $name) : $this->events->stepStarted($run, $name);
        }
        if ($transition['open'] === null) {
            $this->assemble($run);

            return;
        }
        $this->publishProgress($run);
        $this->dispatchWindow($run, $transition['open']);
    }

    /** @return array{data:array,cost:int} */
    private function execute(Session $session, Run $run, Step $step): array
    {
        $outline = $this->outline($run);
        $known = $this->context->knownFragments($session);
        $elementId = $step->elementId();
        $found = $elementId !== null ? Blueprint::find($outline, $elementId) : null;

        return match (true) {
            str_starts_with($step->key, 'lesson:') => $this->lesson($session, $run, $outline, $found, $known),
            str_starts_with($step->key, 'grounding:') => $this->grounding($session, $run, $outline, $found, $known),
            str_starts_with($step->key, 'critique:') => $this->critique($session, $run, $outline, $found, $known),
            str_starts_with($step->key, 'selfcheck:') => $this->selfChecks($session, $run, $outline, $found, $known),
            str_starts_with($step->key, 'interaction:') => $this->interaction($session, $run, $outline, $found, $known),
            str_starts_with($step->key, 'quiz:') => $this->quiz($session, $run, $outline, $found, $known),
            $step->key === 'final_test' => $this->finalTest($session, $run, $outline, $known),
            $step->key === 'metadata' => $this->metadata($session, $run, $outline, $known),
        };
    }

    /** @return array{data:array,cost:int} */
    private function lesson(Session $session, Run $run, array $outline, array $found, array $known, array $feedback = []): array
    {
        $lesson = $found['node'];
        $objectiveIds = array_column($lesson['objectives'], 'id');
        $input = [
            'lesson' => ['id' => $lesson['id'], 'number' => preg_replace('/^Lesson /', '', $found['label']), 'title' => $lesson['title'], 'summary' => $lesson['summary'] ?? '',
                'minutes' => $lesson['minutes'], 'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $lesson['objectives']), 'fragments' => $lesson['citations']],
        ];
        if ($feedback !== []) {
            $input['grounding_feedback'] = $feedback;
        }
        $result = $this->llm->generate($session, $run, 'lesson', [
            $this->context->sourceBlock($session),
            $this->context->contextBlock($session, $outline),
            $this->context->instruction('Write the lesson described in task_input.', $input),
        ], function (array $data) use ($known, $objectiveIds) {
            $errors = [];
            $covered = [];
            foreach ($data['blocks'] ?? [] as $b => $block) {
                $errors = [...$errors, ...Checks::citations($block['citations'] ?? [], $known, "blocks/{$b}"), ...Checks::markup((string) ($block['markdown'] ?? ''), "blocks/{$b}")];
                foreach ($block['objectiveIds'] ?? [] as $oid) {
                    if (!in_array($oid, $objectiveIds, true)) {
                        $errors[] = "blocks/{$b}: objective {$oid} is not an objective of this lesson";
                    }
                    $covered[$oid] = true;
                }
            }
            foreach ($objectiveIds as $oid) {
                if (!isset($covered[$oid])) {
                    $errors[] = "objective {$oid} is not covered by any block";
                }
            }

            return $errors;
        });

        $blocks = array_map(fn ($b) => [
            'id' => Blueprint::newId(),
            'kind' => $b['kind'],
            'markdown' => trim($b['markdown']),
            'citations' => array_values(array_unique($b['citations'])),
            'objectiveIds' => array_values(array_unique($b['objectiveIds'])),
        ], $result->data['blocks']);

        return ['data' => ['blocks' => $blocks], 'cost' => $result->costMicroUsd];
    }

    /** One grounding pass; unsupported claims trigger one regeneration and a second check. */
    private function grounding(Session $session, Run $run, array $outline, array $found, array $known): array
    {
        $lessonStep = Step::query()->where('run_id', $run->id)->where('key', 'lesson:' . $found['node']['id'])->firstOrFail();
        $blocks = $lessonStep->output['blocks'] ?? [];
        $check = $this->checkGrounding($session, $run, $blocks);
        $cost = $check['cost'];
        $regenerated = false;
        if ($check['unsupported'] !== []) {
            $redo = $this->lesson($session, $run, $outline, $found, $known, array_map(fn ($u) => ['block' => $u['blockIndex'], 'claim' => $u['claim'], 'reason' => $u['reason']], $check['unsupported']));
            $blocks = $redo['data']['blocks'];
            $cost += $redo['cost'];
            $regenerated = true;
            $second = $this->checkGrounding($session, $run, $blocks);
            $cost += $second['cost'];
            $check = $second;
        }
        $flags = array_map(fn ($u) => mb_substr('Possibly unsupported: ' . $u['claim'] . ' (' . $u['reason'] . ')', 0, 500), array_slice($check['unsupported'], 0, 20));

        return ['data' => ['blocks' => $blocks, 'flags' => $flags, 'regenerated' => $regenerated], 'cost' => $cost];
    }

    /** @return array{unsupported:array,cost:int} */
    private function checkGrounding(Session $session, Run $run, array $blocks): array
    {
        $map = $this->context->fragmentMap($session);
        $cited = [];
        foreach ($blocks as $block) {
            foreach ($block['citations'] as $id) {
                $cited[$id] = $map[$id] ?? null;
            }
        }
        $mini = "<source_document untrusted=\"true\">\n";
        foreach (array_filter($cited) as $id => $f) {
            /** @var Fragment $f */
            $mini .= '<fragment id="' . $id . '" section="' . PromptContext::esc($f->label()) . "\">\n" . PromptContext::esc($f->text) . "\n</fragment>\n";
        }
        $mini .= '</source_document>';
        $result = $this->llm->generate($session, $run, 'grounding', [
            ContentBlock::text($mini),
            $this->context->instruction('Check each block against its cited fragments.', ['blocks' => array_map(fn ($b, $i) => ['index' => $i, 'markdown' => $b['markdown'], 'citations' => $b['citations']], $blocks, array_keys($blocks))]),
        ], fn (array $data) => array_values(array_filter(array_map(fn ($u) => ($u['blockIndex'] ?? -1) >= count($blocks) ? 'unsupported: blockIndex out of range' : null, $data['unsupported'] ?? []))));

        return ['unsupported' => $result->data['unsupported'], 'cost' => $result->costMicroUsd];
    }

    private function quiz(Session $session, Run $run, array $outline, array $found, array $known): array
    {
        $lesson = $found['node'];
        $blocks = $this->lessonBlocks($run, $lesson['id']);
        $count = max(2, min(5, (int) round($lesson['minutes'] / 4)));
        $input = [
            'count' => $count,
            'lesson' => ['title' => $lesson['title'], 'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $lesson['objectives'])],
            'blocks' => array_map(fn ($b) => ['markdown' => $b['markdown'], 'citations' => $b['citations']], $blocks),
        ];

        return $this->questions($session, $run, $outline, $known, array_column($lesson['objectives'], 'id'), $input, "Write {$count} quiz questions for the lesson in task_input.");
    }

    /** The quality loop over one finished lesson: critics, fixes, flags (ADR 0051). */
    private function critique(Session $session, Run $run, array $outline, array $found, array $known): array
    {
        $lesson = $found['node'];
        $steps = Step::query()->where('run_id', $run->id)->get()->keyBy('key');
        $lesson['blocks'] = $this->lessonBlocks($run, $lesson['id']);
        $lesson['selfChecks'] = $steps["selfcheck:{$lesson['id']}"]->output['questions'] ?? [];
        $lesson['interaction'] = $steps["interaction:{$lesson['id']}"]->output['interaction'] ?? null;
        $quiz = $steps["quiz:{$lesson['id']}"] ?? null;
        $lesson['quiz'] = $quiz?->output ? ['id' => 'q', 'questions' => $quiz->output['questions']] : null;
        $out = $this->critics->run($session, $run, $outline, $lesson, $steps["grounding:{$lesson['id']}"]->output['flags'] ?? [], $known);

        return ['data' => ['blocks' => $out['blocks'], 'flags' => $out['flags'], 'rounds' => $out['rounds']], 'cost' => $out['cost']];
    }

    /** The inline questions of a LiaScript lesson (rendered into the lesson text by our code). */
    private function selfChecks(Session $session, Run $run, array $outline, array $found, array $known): array
    {
        $lesson = $found['node'];
        $count = max(2, min(3, (int) round($lesson['minutes'] / 5)));
        $input = [
            'count' => $count,
            'lesson' => ['title' => $lesson['title'], 'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $lesson['objectives'])],
            'blocks' => array_map(fn ($b) => ['markdown' => $b['markdown'], 'citations' => $b['citations']], $this->lessonBlocks($run, $lesson['id'])),
        ];

        return $this->questions($session, $run, $outline, $known, array_column($lesson['objectives'], 'id'), $input, "Write {$count} self-check questions for the lesson in task_input.", 'selfcheck');
    }

    /** One H5P activity or one interactive from the library, after the lesson text. */
    private function interaction(Session $session, Run $run, array $outline, array $found, array $known): array
    {
        $lesson = $found['node'];
        $objectiveIds = array_column($lesson['objectives'], 'id');
        $blocks = $this->lessonBlocks($run, $lesson['id']);
        $map = $this->context->fragmentMap($session);
        $base = ['lesson' => ['title' => $lesson['title'], 'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $lesson['objectives'])],
            'blocks' => array_map(fn ($b) => ['markdown' => $b['markdown'], 'citations' => $b['citations']], $blocks)];
        $check = function (array $i, string $where) use ($known, $objectiveIds) {
            $errors = Checks::citations($i['citations'] ?? [], $known, $where);
            foreach ($i['objectiveIds'] ?? [] as $oid) {
                if (!in_array($oid, $objectiveIds, true)) {
                    $errors[] = "{$where}: objective {$oid} is not an objective of this lesson";
                }
            }

            return [...$errors, ...Checks::markup((string) ($i['title'] ?? ''), "{$where}/title")];
        };

        if ($lesson['contentType'] === 'interactive') {
            $library = InteractiveType::library();
            $ids = array_column($library, 'id');
            $stepIds = array_column($library, 'steps', 'id');
            $result = $this->llm->generate($session, $run, 'interaction_interactive', [
                $this->context->sourceBlock($session),
                $this->context->contextBlock($session, $outline),
                $this->context->instruction('Attach one interactive from the library to the lesson in task_input.', $base + ['library' => $library]),
            ], function (array $data) use ($check, $ids, $stepIds) {
                $i = (array) ($data['interaction'] ?? []);
                $errors = $check($i, 'interaction');
                if (!in_array($i['packageId'] ?? null, $ids, true)) {
                    $errors[] = 'interaction/packageId: choose one of the ids in task_input.library';
                } else {
                    $steps = array_column($stepIds[$i['packageId']], 'id');
                    foreach (['startStep', 'endStep'] as $f) {
                        if (($i[$f] ?? '') !== '' && !in_array($i[$f], $steps, true)) {
                            $errors[] = "interaction/{$f}: \"{$i[$f]}\" is not a step of that package";
                        }
                    }
                    if (($i['startStep'] ?? '') !== '' && ($i['endStep'] ?? '') !== '' && array_search($i['startStep'], $steps, true) > array_search($i['endStep'], $steps, true)) {
                        $errors[] = 'interaction/endStep: comes before startStep';
                    }
                }

                return [...$errors, ...Checks::markup((string) ($i['caption'] ?? ''), 'interaction/caption')];
            });
            $i = $result->data['interaction'];

            return ['data' => ['interaction' => array_filter([
                'id' => Blueprint::newId(), 'kind' => 'interactive', 'packageId' => (int) $i['packageId'], 'title' => trim($i['title']),
                'startStep' => ($i['startStep'] ?? '') !== '' ? $i['startStep'] : null, 'endStep' => ($i['endStep'] ?? '') !== '' ? $i['endStep'] : null,
                'caption' => trim($i['caption']), 'citations' => array_values(array_unique($i['citations'])), 'objectiveIds' => array_values(array_unique($i['objectiveIds'])),
            ], fn ($v) => $v !== null)], 'cost' => $result->costMicroUsd];
        }

        $libraries = array_keys($this->h5p->installed());
        if ($libraries === []) {
            throw new LlmException(LlmException::INVALID_OUTPUT, 'No H5P library is installed on this platform.');
        }
        $minOverlap = (int) config('course_builder.quiz_support_min_overlap', 2);
        $result = $this->llm->generate($session, $run, 'interaction_h5p', [
            $this->context->sourceBlock($session),
            $this->context->contextBlock($session, $outline),
            $this->context->instruction('Design one H5P activity for the lesson in task_input.', $base + ['libraries' => $libraries]),
        ], function (array $data) use ($check, $libraries, $map, $minOverlap) {
            $i = (array) ($data['interaction'] ?? []);
            if (!in_array($i['library'] ?? null, $libraries, true)) {
                return ['interaction/library: not an installed library'];
            }
            $texts = array_map(fn ($id) => isset($map[$id]) ? $map[$id]->text : '', $i['citations'] ?? []);

            return [...$check($i, 'interaction'), ...H5pLibraries::errors($i['library'], (array) $i['data'], 'interaction'), ...H5pLibraries::unsupported($i['library'], (array) $i['data'], array_filter($texts), 'interaction', $minOverlap)];
        }, H5pLibraries::outputSchema($libraries));
        $i = $result->data['interaction'];

        return ['data' => ['interaction' => [
            'id' => Blueprint::newId(), 'kind' => 'h5p', 'library' => $i['library'], 'title' => trim($i['title']), 'data' => $i['data'],
            'citations' => array_values(array_unique($i['citations'])), 'objectiveIds' => array_values(array_unique($i['objectiveIds'])),
        ]], 'cost' => $result->costMicroUsd];
    }

    private function finalTest(Session $session, Run $run, array $outline, array $known): array
    {
        $lessons = iterator_to_array(Blueprint::lessons($outline), false);
        $count = max(5, min(10, count($lessons)));
        $objectives = [];
        foreach ($lessons as $item) {
            foreach ($item['lesson']['objectives'] as $o) {
                $objectives[] = ['id' => $o['id'], 'text' => $o['text'], 'lesson' => $item['lesson']['title'], 'fragments' => $o['citations']];
            }
        }

        return $this->questions($session, $run, $outline, $known, array_column($objectives, 'id'), ['count' => $count, 'finalTest' => true, 'objectives' => $objectives],
            "Write a final test of {$count} questions covering the course objectives in task_input, at most one question per objective.");
    }

    private function questions(Session $session, Run $run, array $outline, array $known, array $objectiveIds, array $input, string $instruction, string $task = 'quiz'): array
    {
        $map = $this->context->fragmentMap($session);
        $minOverlap = (int) config('course_builder.quiz_support_min_overlap', 2);
        $result = $this->llm->generate($session, $run, $task, [
            $this->context->sourceBlock($session),
            $this->context->contextBlock($session, $outline),
            $this->context->instruction($instruction, $input),
        ], function (array $data) use ($known, $objectiveIds, $map, $minOverlap, $task) {
            $errors = [];
            foreach ($data['questions'] ?? [] as $q => $question) {
                $w = "questions/{$q}";
                $errors = [...$errors, ...Checks::citations($question['citations'] ?? [], $known, $w), ...Checks::questionShape($question, $w)];
                foreach (['stem', 'explanation'] as $field) {
                    $errors = [...$errors, ...Checks::markup((string) ($question[$field] ?? ''), "{$w}/{$field}")];
                }
                foreach ($question['objectiveIds'] ?? [] as $oid) {
                    if (!in_array($oid, $objectiveIds, true)) {
                        $errors[] = "{$w}: objective {$oid} is not in task_input";
                    }
                }
                if ($task === 'selfcheck' && LiaScriptRenderer::unsafe(($question['stem'] ?? '') . ' ' . ($question['explanation'] ?? '') . ' ' . implode(' ', array_column($question['options'] ?? [], 'text')))) {
                    $errors[] = "{$w}: contains a macro, script or import; write plain text";
                }
                $texts = array_map(fn ($id) => isset($map[$id]) ? $map[$id]->text : '', $question['citations'] ?? []);
                $errors = [...$errors, ...Checks::quizSupport($question, array_filter($texts), $w, $minOverlap)];
            }

            return $errors;
        });

        $questions = array_map(fn ($q) => [
            'id' => Blueprint::newId(),
            'type' => $q['type'],
            'stem' => trim($q['stem']),
            'options' => array_map(fn ($o) => ['id' => Blueprint::newId(), 'text' => trim($o['text']), 'correct' => (bool) $o['correct']], $q['options']),
            'explanation' => trim($q['explanation']),
            'citations' => array_values(array_unique($q['citations'])),
            'objectiveIds' => array_values(array_unique($q['objectiveIds'])),
        ], $result->data['questions']);

        return ['data' => ['questions' => $questions], 'cost' => $result->costMicroUsd];
    }

    private function metadata(Session $session, Run $run, array $outline, array $known): array
    {
        $result = $this->llm->generate($session, $run, 'metadata', [
            $this->context->sourceBlock($session),
            $this->context->contextBlock($session, $outline),
            $this->context->instruction('Write the course metadata and FAQ.'),
        ], function (array $data) use ($known) {
            $errors = [];
            foreach (['title', 'subtitle', 'description', 'seoTitle', 'seoDescription'] as $field) {
                $errors = [...$errors, ...Checks::markup((string) ($data[$field] ?? ''), $field)];
            }
            foreach ($data['faq'] ?? [] as $i => $f) {
                $errors = [...$errors, ...Checks::citations($f['citations'] ?? [], $known, "faq/{$i}", 0), ...Checks::markup($f['question'] . ' ' . $f['answer'], "faq/{$i}")];
            }

            return $errors;
        });

        return ['data' => $result->data, 'cost' => $result->costMicroUsd];
    }

    /** Lesson blocks after grounding (the regenerated ones when grounding rewrote them). */
    private function lessonBlocks(Run $run, string $lessonId): array
    {
        $grounding = Step::query()->where('run_id', $run->id)->where('key', "grounding:{$lessonId}")->first();
        if ($grounding !== null && $grounding->status === 'done' && isset($grounding->output['blocks'])) {
            return $grounding->output['blocks'];
        }

        return Step::query()->where('run_id', $run->id)->where('key', "lesson:{$lessonId}")->first()?->output['blocks'] ?? [];
    }

    /** Builds the content version from the outline and the step outputs and proposes the apply. */
    public function assemble(Run $run): Version
    {
        $session = $run->session;
        $outlineVersion = Version::query()->findOrFail($run->input['outlineVersionId']);
        $doc = $outlineVersion->document;
        $steps = Step::query()->where('run_id', $run->id)->get()->keyBy('key');
        foreach ($doc['modules'] as $m => $module) {
            foreach ($module['lessons'] as $l => $lesson) {
                $blocks = $this->lessonBlocks($run, $lesson['id']);
                $doc['modules'][$m]['lessons'][$l]['blocks'] = $blocks;
                $doc['modules'][$m]['lessons'][$l]['status'] = $blocks === [] ? 'failed' : 'generated';
                $doc['modules'][$m]['lessons'][$l]['flags'] = $steps["grounding:{$lesson['id']}"]->output['flags'] ?? [];
                $review = $steps["critique:{$lesson['id']}"]->output ?? null;
                if ($review !== null) {
                    $doc['modules'][$m]['lessons'][$l]['blocks'] = $review['blocks'];
                    $doc['modules'][$m]['lessons'][$l]['flags'] = array_slice([...$doc['modules'][$m]['lessons'][$l]['flags'], ...$review['flags']], 0, 20);
                }
                $checks = $steps["selfcheck:{$lesson['id']}"]->output['questions'] ?? null;
                if ($checks !== null) {
                    $doc['modules'][$m]['lessons'][$l]['selfChecks'] = $checks;
                }
                $interaction = $steps["interaction:{$lesson['id']}"]->output['interaction'] ?? null;
                if ($interaction !== null) {
                    $doc['modules'][$m]['lessons'][$l]['interaction'] = $interaction;
                }
                $quiz = $steps["quiz:{$lesson['id']}"] ?? null;
                $doc['modules'][$m]['lessons'][$l]['quiz'] = $quiz?->output ? ['id' => Blueprint::newId(), 'questions' => $quiz->output['questions']] : null;
            }
        }
        $final = $steps['final_test'] ?? null;
        $doc['finalTest'] = $final?->output ? ['id' => Blueprint::newId(), 'passScore' => (int) ($session->brief['assessments']['passScore'] ?? 70), 'questions' => $final->output['questions']] : null;
        $meta = $steps['metadata']->output ?? null;
        if ($meta) {
            $doc['course']['title'] = $meta['title'];
            $doc['course']['subtitle'] = $meta['subtitle'];
            $doc['course']['description'] = $meta['description'];
            $doc['course']['seo'] = ['title' => mb_substr($meta['seoTitle'], 0, 70), 'description' => mb_substr($meta['seoDescription'], 0, 160)];
            $doc['course']['faq'] = array_map(fn ($f) => ['question' => $f['question'], 'answer' => $f['answer'], 'citations' => array_values(array_unique($f['citations']))], $meta['faq']);
        }
        $suggestion = $this->prices->suggest($session, $run, $outlineVersion->document);
        $doc['pages'] = ['landing' => LandingDocument::landing($doc, $session->brief), 'header' => LandingDocument::header($doc, $session->brief)];

        $version = $this->versions->create($session, $doc, 'content', 'ai', Version::PROPOSED, $outlineVersion, 'Generated lessons, quizzes and metadata');
        $this->recordCritiques($session, $version, $steps);
        $session->current_version_id = $version->id;
        $session->status = Session::APPLY_REVIEW;
        $session->title = $doc['course']['title'];
        $session->save();
        $run->forceFill(['status' => 'finished', 'stage' => 'done', 'finished_at' => now()])->save();
        $this->publishProgress($run);
        $this->events->stepFinished($run, 'assemble');
        $stats = Blueprint::stats($doc);
        $this->events->text($session, $run, sprintf(
            'Your course is drafted: %d modules, %d lessons, %d quiz questions, %d minutes. Review what will be created in your academy and approve to apply. Nothing is written to the LMS before that.',
            $stats['modules'], $stats['lessons'], $stats['questions'], $stats['minutes'],
        ));
        if ($suggestion !== null) {
            $this->events->text($session, $run, sprintf(
                'Suggested price: %s %s. %s Confirm or change it in the Course brief before you publish; nothing is sold until you do.',
                number_format($suggestion['amountMinor'] / 100, 2, '.', ''), $suggestion['currency'], $suggestion['rationale'],
            ));
        }
        $this->surfaces->apply($session, $run, $version, $this->applier->plan($session, $doc));
        $this->events->runFinished($run, ['versionId' => $version->id]);

        return $version;
    }

    /** Stores what the critics said, per lesson, critic and iteration, against the version they produced. */
    private function recordCritiques(Session $session, Version $version, $steps): void
    {
        foreach ($steps as $key => $step) {
            if (!str_starts_with($key, 'critique:') || empty($step->output['rounds'])) {
                continue;
            }
            foreach ($step->output['rounds'] as $round) {
                foreach ($round['critics'] as $c) {
                    Critique::query()->create([
                        'session_id' => $session->id, 'version_id' => $version->id, 'element_id' => substr($key, 9), 'critic' => $c['critic'],
                        'iteration' => (int) $round['iteration'], 'verdict' => $c['verdict'], 'issues' => $c['issues'] ?: null, 'ai_call_id' => $c['callId'] ?? null,
                    ]);
                }
            }
        }
    }

    public function publishProgress(Run $run): void
    {
        $this->surfaces->progress($run->session, $run, $this->progressProps($run));
    }

    public function progressProps(Run $run): array
    {
        $session = $run->session;
        $outline = $this->outline($run);
        $steps = Step::query()->where('run_id', $run->id)->get()->keyBy('key');
        $stageIndex = array_search(explode(':', (string) $run->stage)[0], array_keys(self::STAGES), true);
        $stages = [];
        foreach (array_keys(self::STAGES) as $i => $key) {
            $stageSteps = $steps->filter(fn (Step $s) => $s->stage === $key);
            $status = match (true) {
                $run->stage === 'done' || $run->stage === 'assembling' => 'done',
                $stageSteps->contains(fn (Step $s) => $s->status === 'failed') => 'failed',
                $stageIndex !== false && $i < $stageIndex => 'done',
                $stageIndex !== false && $i === $stageIndex => 'running',
                default => 'pending',
            };
            $stages[] = ['key' => $key, 'label' => self::STAGES[$key], 'status' => $status];
        }
        $lessons = [];
        foreach (Blueprint::lessons($outline) as $item) {
            $id = $item['lesson']['id'];
            $parts = array_filter([$steps["lesson:{$id}"] ?? null, $steps["grounding:{$id}"] ?? null, $steps["selfcheck:{$id}"] ?? null, $steps["interaction:{$id}"] ?? null, $steps["quiz:{$id}"] ?? null, $steps["critique:{$id}"] ?? null]);
            $failed = collect($parts)->first(fn (Step $s) => $s->status === 'failed');
            $blocks = $this->lessonBlocksFrom($steps, $id);
            $flags = $steps["grounding:{$id}"]->output['flags'] ?? [];
            $lessonDone = ($steps["lesson:{$id}"] ?? null)?->status === 'done';
            $lessons[] = array_filter([
                'id' => $id,
                'title' => $item['number'] . ' ' . $item['lesson']['title'],
                'module' => $item['module']['title'],
                'status' => $failed ? 'failed' : (collect($parts)->contains(fn (Step $s) => $s->status === 'running' || $s->status === 'queued') ? 'running' : ($lessonDone ? ($flags ? 'flagged' : 'done') : 'pending')),
                'citations' => count(array_unique(array_merge(...array_map(fn ($b) => $b['citations'], $blocks ?: [['citations' => []]])))),
                'questions' => count($steps["quiz:{$id}"]->output['questions'] ?? []),
                'costMicroUsd' => (int) collect($parts)->sum('cost_micro_usd'),
                'stepId' => $failed?->id,
                'error' => $failed?->error ? mb_substr($failed->error, 0, 500) : null,
            ], fn ($v) => $v !== null);
        }

        return [
            'runId' => $run->id,
            'status' => in_array($run->status, ['running', 'needs_attention', 'finished', 'failed', 'cancelled'], true) ? $run->status : 'running',
            'stages' => $stages,
            'lessons' => $lessons,
            'cost' => Surfaces::costProps($session),
        ];
    }

    private function lessonBlocksFrom($steps, string $id): array
    {
        $g = $steps["grounding:{$id}"] ?? null;
        if ($g !== null && $g->status === 'done' && isset($g->output['blocks'])) {
            return $g->output['blocks'];
        }

        return $steps["lesson:{$id}"]->output['blocks'] ?? [];
    }

    private function stepLabel(Run $run, Step $step): string
    {
        $id = $step->elementId();
        $found = $id ? Blueprint::find($this->outline($run), $id) : null;

        return match (true) {
            str_starts_with($step->key, 'lesson:') => ($found['label'] ?? 'A lesson'),
            str_starts_with($step->key, 'grounding:') => 'The grounding check of ' . ($found['label'] ?? 'a lesson'),
            str_starts_with($step->key, 'critique:') => 'The quality review of ' . ($found['label'] ?? 'a lesson'),
            str_starts_with($step->key, 'selfcheck:') => 'The self-checks of ' . ($found['label'] ?? 'a lesson'),
            str_starts_with($step->key, 'interaction:') => 'The interactive part of ' . ($found['label'] ?? 'a lesson'),
            str_starts_with($step->key, 'quiz:') => 'The quiz of ' . ($found['label'] ?? 'a lesson'),
            $step->key === 'final_test' => 'The final test',
            default => 'The metadata step',
        };
    }
}
