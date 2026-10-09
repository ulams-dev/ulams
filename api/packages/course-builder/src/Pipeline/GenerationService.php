<?php

namespace Ulams\CourseBuilder\Pipeline;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Apply\LandingDocument;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Jobs\StepJob;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Ui\Surfaces;

/**
 * Stages 3–6: lessons (in parallel, with a concurrency window), grounding check with one
 * regeneration, quizzes and the final test, metadata; then the content version is assembled and
 * the apply proposal shown. Every unit is a step keyed by element id: a `done` step is skipped on
 * resume, a failed step can be retried alone, and the rest of the course stays usable.
 */
final class GenerationService
{
    public const STAGES = ['lessons' => 'Lessons', 'grounding' => 'Grounding check', 'quizzes' => 'Quizzes', 'metadata' => 'Metadata & landing'];

    public function __construct(
        private readonly Llm $llm,
        private readonly PromptContext $context,
        private readonly VersionService $versions,
        private readonly Surfaces $surfaces,
        private readonly EventLog $events,
        private readonly BlueprintApplier $applier,
        private readonly PriceService $prices,
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

    /** Creates the steps of a stage and dispatches the first window. */
    private function openStage(Run $run, string $stage, array $outline, array $brief): void
    {
        $keys = match ($stage) {
            'lessons' => array_map(fn ($i) => 'lesson:' . $i['lesson']['id'], iterator_to_array(Blueprint::lessons($outline), false)),
            'grounding' => array_map(fn ($i) => 'grounding:' . $i['lesson']['id'], iterator_to_array(Blueprint::lessons($outline), false)),
            'quizzes' => [
                ...(!empty($brief['assessments']['perLessonQuiz']) ? array_map(fn ($i) => 'quiz:' . $i['lesson']['id'], iterator_to_array(Blueprint::lessons($outline), false)) : []),
                ...(!empty($brief['assessments']['finalTest']) ? ['final_test'] : []),
            ],
            'metadata' => ['metadata'],
        };
        $run->forceFill(['stage' => $stage])->save();
        $this->events->stepStarted($run, $stage);
        foreach ($keys as $key) {
            Step::query()->firstOrCreate(['run_id' => $run->id, 'key' => $key], ['stage' => $stage, 'status' => 'pending']);
        }
        $this->publishProgress($run);
        if ($keys === []) {
            $this->advance($run);

            return;
        }
        $this->dispatchWindow($run, $stage);
    }

    private function dispatchWindow(Run $run, string $stage): void
    {
        $limit = max(1, (int) config('course_builder.limits.lesson_concurrency', 4));
        $running = Step::query()->where('run_id', $run->id)->where('stage', $stage)->whereIn('status', ['queued', 'running'])->count();
        $free = $limit - $running;
        foreach (Step::query()->where('run_id', $run->id)->where('stage', $stage)->where('status', 'pending')->orderBy('created_at')->orderBy('key')->limit(max(0, $free))->get() as $step) {
            $claimed = Step::query()->whereKey($step->id)->where('status', 'pending')->update(['status' => 'queued']);
            if ($claimed) {
                StepJob::dispatchFor($step->id);
            }
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

    /** Moves to the next stage once every step of the current one is done. */
    public function advance(Run $run): void
    {
        $next = DB::transaction(function () use ($run) {
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
            $index = array_search($locked->stage, $order, true);
            $nextStage = $order[$index + 1] ?? 'assemble';
            $locked->forceFill(['stage' => $nextStage === 'assemble' ? 'assembling' : $nextStage . ':opening', 'status' => 'running'])->save();

            return [$locked->stage, $nextStage, $order[$index]];
        });
        if ($next === null) {
            return;
        }
        [, $nextStage, $finished] = $next;
        $run->refresh();
        $this->events->stepFinished($run, $finished);
        if ($nextStage === 'assemble') {
            $this->assemble($run);

            return;
        }
        $this->openStage($run, $nextStage, $this->outline($run), $run->session->brief);
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

    private function questions(Session $session, Run $run, array $outline, array $known, array $objectiveIds, array $input, string $instruction): array
    {
        $map = $this->context->fragmentMap($session);
        $minOverlap = (int) config('course_builder.quiz_support_min_overlap', 2);
        $result = $this->llm->generate($session, $run, 'quiz', [
            $this->context->sourceBlock($session),
            $this->context->contextBlock($session, $outline),
            $this->context->instruction($instruction, $input),
        ], function (array $data) use ($known, $objectiveIds, $map, $minOverlap) {
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
            $parts = array_filter([$steps["lesson:{$id}"] ?? null, $steps["grounding:{$id}"] ?? null, $steps["quiz:{$id}"] ?? null]);
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
            str_starts_with($step->key, 'quiz:') => 'The quiz of ' . ($found['label'] ?? 'a lesson'),
            $step->key === 'final_test' => 'The final test',
            default => 'The metadata step',
        };
    }
}
