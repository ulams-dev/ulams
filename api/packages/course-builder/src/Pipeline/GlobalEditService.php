<?php

namespace Ulams\CourseBuilder\Pipeline;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Services\CostCalculator;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\ContentTypes\LiaScriptRenderer;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Ingestion\Fragmenter;
use Ulams\CourseBuilder\Jobs\GlobalStepJob;
use Ulams\CourseBuilder\Jobs\RunJob;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Ui\Surfaces;

/**
 * Whole-course edits through the queued pipeline (L2-15): translate, change the level, change the
 * tone, or a free instruction. The author sees an estimate and confirms; one step per lesson (plus the
 * course details and the final test) runs in a sliding window; each result is validated (ids, markup,
 * code blocks, quiz support) and merged onto the original so ids, citations, question types and the
 * correct answers never change. All results become ONE proposed version with the whole diff; approving
 * it re-applies the course like any chat edit.
 */
final class GlobalEditService
{
    public const HANDLER = 'course-builder.global';
    public const KINDS = ['translate', 'change_level', 'change_tone', 'custom'];
    public const LEVELS = ['beginner', 'intermediate', 'advanced'];
    public const TONES = ['friendly', 'professional', 'playful', 'academic'];

    private const LANGUAGES = ['en' => 'English', 'pl' => 'Polish', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'it' => 'Italian', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'cs' => 'Czech', 'sk' => 'Slovak', 'uk' => 'Ukrainian', 'sv' => 'Swedish', 'da' => 'Danish', 'fi' => 'Finnish', 'no' => 'Norwegian', 'ro' => 'Romanian', 'hu' => 'Hungarian', 'tr' => 'Turkish', 'ja' => 'Japanese', 'zh' => 'Chinese', 'ko' => 'Korean'];

    public function __construct(
        private readonly Llm $llm,
        private readonly PromptContext $context,
        private readonly VersionService $versions,
        private readonly Surfaces $surfaces,
        private readonly EventLog $events,
        private readonly \Ulams\Ai\Prompts\PromptRegistry $prompts,
    ) {
    }

    /**
     * @param array<string,mixed> $in {kind, value?, text?}
     * @return array{kind:string,value:string,text:string,label:string}
     */
    public function normalise(array $in): array
    {
        $kind = (string) ($in['kind'] ?? '');
        $value = trim((string) ($in['value'] ?? ''));
        $text = trim((string) ($in['text'] ?? ''));
        switch ($kind) {
            case 'translate':
                $value = strtolower($value);
                if (!preg_match('/^[a-z]{2}$/', $value)) {
                    throw new InvalidArgumentException('Choose the language to translate to (a two-letter code, e.g. pl).');
                }

                return ['kind' => $kind, 'value' => $value, 'text' => '', 'label' => 'Translate the whole course to ' . (self::LANGUAGES[$value] ?? strtoupper($value))];
            case 'change_level':
                if (!in_array($value, self::LEVELS, true)) {
                    throw new InvalidArgumentException('Choose a level: ' . implode(', ', self::LEVELS) . '.');
                }

                return ['kind' => $kind, 'value' => $value, 'text' => '', 'label' => "Rewrite the whole course for the {$value} level"];
            case 'change_tone':
                if (!in_array($value, self::TONES, true)) {
                    throw new InvalidArgumentException('Choose a tone: ' . implode(', ', self::TONES) . '.');
                }

                return ['kind' => $kind, 'value' => $value, 'text' => '', 'label' => "Change the tone of the whole course to {$value}"];
            case 'custom':
                if (mb_strlen($text) < 3 || mb_strlen($text) > 1000 || Checks::markup($text, 'text') !== []) {
                    throw new InvalidArgumentException('Describe the change in 3–1000 characters of plain text.');
                }

                return ['kind' => $kind, 'value' => '', 'text' => $text, 'label' => 'Whole course: ' . mb_substr($text, 0, 120)];
        }
        throw new InvalidArgumentException('Unknown whole-course change.');
    }

    /** @return array<int,array{key:string,type:string,id:?string}> */
    private function plan(array $doc): array
    {
        $steps = [['key' => 'course', 'type' => 'course', 'id' => null]];
        foreach (Blueprint::lessons($doc) as $item) {
            if (($item['lesson']['blocks'] ?? []) !== []) {
                $steps[] = ['key' => 'lesson:' . $item['lesson']['id'], 'type' => 'lesson', 'id' => $item['lesson']['id']];
            }
        }
        if (!empty($doc['finalTest']['questions'])) {
            $steps[] = ['key' => 'final_test', 'type' => 'final_test', 'id' => null];
        }

        return $steps;
    }

    private function base(Session $session): Version
    {
        $current = $session->currentVersion;
        if ($current === null || !in_array($current->kind, VersionService::CONTENT_KINDS, true) || $current->status === Version::PROPOSED) {
            throw new InvalidArgumentException('Whole-course edits are available once the lessons are generated and approved.');
        }

        return $current;
    }

    /** @return array<string,mixed> the model input of one step */
    private function input(array $doc, array $step): array
    {
        $question = fn (array $q) => ['id' => $q['id'], 'type' => $q['type'], 'stem' => $q['stem'], 'options' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text'], 'correct' => $o['correct']], $q['options']), 'explanation' => $q['explanation']];
        if ($step['type'] === 'course') {
            $c = $doc['course'];

            return ['title' => $c['title'], 'subtitle' => $c['subtitle'] ?? '', 'description' => $c['description'] ?? '',
                'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $c['objectives'] ?? []),
                'modules' => array_map(fn ($m) => ['id' => $m['id'], 'title' => $m['title'], 'summary' => $m['summary'] ?? ''], $doc['modules']),
                'faq' => array_map(fn ($f) => ['question' => $f['question'], 'answer' => $f['answer']], $c['faq'] ?? [])];
        }
        if ($step['type'] === 'final_test') {
            return ['questions' => array_map($question, $doc['finalTest']['questions'])];
        }
        $lesson = Blueprint::find($doc, (string) $step['id'])['node'];

        return ['title' => $lesson['title'], 'summary' => $lesson['summary'] ?? '',
            'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $lesson['objectives']),
            'blocks' => array_map(fn ($b) => ['id' => $b['id'], 'kind' => $b['kind'], 'markdown' => $b['markdown']], $lesson['blocks']),
            'questions' => array_map($question, $lesson['quiz']['questions'] ?? []),
            'selfChecks' => array_map($question, $lesson['selfChecks'] ?? [])];
    }

    private function model(): string
    {
        return (string) config('ai.profiles.' . config('ai.tasks.global.profile', 'default') . '.model', '');
    }

    /**
     * What the edit would cost before it starts: one call per step, input = the text of the part plus
     * the instruction, output about as long as the input.
     *
     * @return array{steps:int,lessons:int,estimateMicroUsd:int,label:string}
     */
    public function estimate(Session $session, array $instruction): array
    {
        $instruction = $this->normalise($instruction);
        $doc = $this->base($session)->document;
        $costs = CostCalculator::fromConfig();
        $system = Fragmenter::tokens($this->prompts->get(Llm::PROMPTS, 'global')->text);
        $factor = (float) config('course_builder.global_edit.output_factor', 1.2);
        $total = 0;
        $steps = $this->plan($doc);
        foreach ($steps as $step) {
            $tokens = Fragmenter::tokens((string) json_encode($this->input($doc, $step), JSON_UNESCAPED_UNICODE));
            $total += $costs->estimate($this->model(), $system + $tokens + 150, (int) ceil($tokens * $factor));
        }

        return ['steps' => count($steps), 'lessons' => count(array_filter($steps, fn ($s) => $s['type'] === 'lesson')), 'estimateMicroUsd' => $total, 'label' => $instruction['label']];
    }

    /**
     * @return array{state:string,run:?Run,steps:int,estimateMicroUsd:int,message:?string} state: started | needs_confirmation | blocked
     */
    public function begin(Session $session, array $instruction, bool $confirmed, ?int $userId): array
    {
        $instruction = $this->normalise($instruction);
        $estimate = $this->estimate($session, $instruction);
        $cap = (int) round((float) config('course_builder.global_edit.max_usd', 3) * 1e6);
        if ($estimate['estimateMicroUsd'] > $cap) {
            return ['state' => 'blocked', 'run' => null, 'steps' => $estimate['steps'], 'estimateMicroUsd' => $estimate['estimateMicroUsd'],
                'message' => sprintf('This change would cost about $%.2f, above the $%.2f limit for one whole-course edit. Ask an admin to raise COURSE_BUILDER_GLOBAL_EDIT_MAX_USD, or edit lesson by lesson.', $estimate['estimateMicroUsd'] / 1e6, $cap / 1e6)];
        }
        if (!$confirmed) {
            return ['state' => 'needs_confirmation', 'run' => null, 'steps' => $estimate['steps'], 'estimateMicroUsd' => $estimate['estimateMicroUsd'],
                'message' => sprintf('%s: %d model calls, about $%.2f. Confirm to start; nothing changes until you approve the result.', $estimate['label'], $estimate['steps'], $estimate['estimateMicroUsd'] / 1e6)];
        }
        $run = $this->start($session, $instruction, $userId);

        return ['state' => 'started', 'run' => $run, 'steps' => $estimate['steps'], 'estimateMicroUsd' => $estimate['estimateMicroUsd'], 'message' => null];
    }

    private function start(Session $session, array $instruction, ?int $userId): Run
    {
        $active = Run::query()->whereIn('kind', \Ulams\CourseBuilder\Services\RunService::LLM_KINDS)->whereIn('status', ['queued', 'running'])
            ->whereIn('session_id', Session::query()->where('author_id', $session->author_id)->select('id'))->count();
        if ($active >= (int) config('course_builder.limits.concurrent_runs_per_author', 2)) {
            throw new \Ulams\CourseBuilder\Exceptions\BuilderException('You already have AI work running. Wait for it to finish, then try again.', 429);
        }
        $run = Run::query()->create(['session_id' => $session->id, 'kind' => 'global', 'status' => 'queued', 'user_id' => $userId,
            'input' => ['handler' => self::HANDLER, 'instruction' => $instruction, 'baseVersionId' => $session->current_version_id]]);
        RunJob::dispatchFor($run->id);

        return $run;
    }

    /** Run handler: one step per part, then the first window. Returns false: the run stays open until the steps are done. */
    public function handleRun(Run $run, Session $session): bool
    {
        $base = Version::query()->findOrFail($run->input['baseVersionId']);
        foreach ($this->plan($base->document) as $step) {
            Step::query()->firstOrCreate(['run_id' => $run->id, 'key' => $step['key']], ['stage' => 'global', 'status' => 'pending']);
        }
        $run->forceFill(['stage' => 'global'])->save();
        $this->events->text($session, $run, $run->input['instruction']['label'] . ': rewriting the course part by part. You will review the whole diff before anything changes.');
        $this->progress($run);
        $this->dispatchWindow($run);

        return false;
    }

    private function dispatchWindow(Run $run): void
    {
        $limit = max(1, (int) config('course_builder.limits.lesson_concurrency', 4));
        $claimed = DB::transaction(function () use ($run, $limit) {
            Run::query()->lockForUpdate()->find($run->id);
            $running = Step::query()->where('run_id', $run->id)->whereIn('status', ['queued', 'running'])->count();
            $ids = [];
            foreach (Step::query()->where('run_id', $run->id)->where('status', 'pending')->orderBy('created_at')->orderBy('key')->limit(max(0, $limit - $running))->get() as $step) {
                if (Step::query()->whereKey($step->id)->where('status', 'pending')->update(['status' => 'queued'])) {
                    $ids[] = $step->id;
                }
            }

            return $ids;
        });
        foreach ($claimed as $id) {
            GlobalStepJob::dispatchFor($id);
        }
    }

    public function runStep(Step $step): void
    {
        $step->refresh();
        $run = $step->run;
        if ($step->status === 'done' || in_array($run->status, ['cancelled', 'failed', 'finished'], true)) {
            return;
        }
        $session = $run->session;
        $step->forceFill(['status' => 'running', 'attempts' => $step->attempts + 1])->save();
        $this->progress($run);
        try {
            $out = $this->execute($session, $run, $step);
            $step->forceFill(['status' => 'done', 'output' => $out['data'], 'error' => null, 'cost_micro_usd' => $step->cost_micro_usd + $out['cost']])->save();
        } catch (LlmException $e) {
            $step->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            $run->forceFill(['status' => 'needs_attention'])->save();
            $this->events->text($session, $run, $this->label($run, $step) . ' failed: ' . $e->getMessage() . ' You can retry it; the other parts are kept.');
        }
        $this->progress($run);
        $this->dispatchWindow($run);
        $this->advance($run);
    }

    public function retry(Step $step): void
    {
        $run = $step->run;
        $step->forceFill(['status' => 'pending', 'error' => null])->save();
        if ($run->status === 'needs_attention' && !Step::query()->where('run_id', $run->id)->where('status', 'failed')->exists()) {
            $run->forceFill(['status' => 'running'])->save();
        }
        $this->progress($run);
        $this->dispatchWindow($run);
    }

    /** @return array{data:array,cost:int} */
    private function execute(Session $session, Run $run, Step $step): array
    {
        $base = Version::query()->findOrFail($run->input['baseVersionId']);
        $doc = $base->document;
        $instruction = $run->input['instruction'];
        $type = $step->key === 'course' ? 'course' : ($step->key === 'final_test' ? 'final_test' : 'lesson');
        $def = ['key' => $step->key, 'type' => $type, 'id' => $step->elementId()];
        $input = $this->input($doc, $def);
        $fragmentIds = $type === 'lesson' ? (Blueprint::find($doc, (string) $def['id'])['node']['citations'] ?? []) : [];
        $map = $this->context->fragmentMap($session);
        $mini = "<source_document untrusted=\"true\">\n";
        foreach ($fragmentIds as $id) {
            if (isset($map[$id])) {
                $mini .= '<fragment id="' . $id . '" section="' . PromptContext::esc($map[$id]->label()) . "\">\n" . PromptContext::esc($map[$id]->text) . "\n</fragment>\n";
            }
        }
        $mini .= '</source_document>';
        $header = '<global_instruction>' . json_encode(['kind' => $instruction['kind'], 'value' => $instruction['value']], JSON_UNESCAPED_UNICODE) . '</global_instruction>'
            . ($instruction['text'] !== '' ? "\n<author_request>" . PromptContext::esc($instruction['text']) . '</author_request>' : '');
        $texts = array_values(array_filter(array_map(fn ($id) => isset($map[$id]) ? $map[$id]->text : '', $fragmentIds)));
        $original = $type === 'lesson' ? Blueprint::find($doc, (string) $def['id'])['node'] : null;

        $result = $this->llm->generate($session, $run, 'global', [
            ContentBlock::text($mini),
            $this->context->instruction("Revise the part in task_input.\n{$header}", $input),
        ], fn (array $data) => $this->validate($type, $input, $data, $instruction['kind'], $texts, $original), GlobalSchemas::for($type));

        return ['data' => $result->data, 'cost' => $result->costMicroUsd];
    }

    /**
     * @param string[] $citedTexts
     * @return string[]
     */
    private function validate(string $type, array $input, array $data, string $kind, array $citedTexts, ?array $lesson): array
    {
        $errors = [];
        $same = function (array $ids, array $got, string $where) use (&$errors) {
            if (array_values($ids) !== array_values($got)) {
                $errors[] = "{$where}: return every item once, with the same ids in the same order";
            }
        };
        $text = function (string $value, string $where) use (&$errors) {
            $errors = [...$errors, ...Checks::markup($value, $where)];
        };
        $questions = function (array $originals, array $returned, string $where) use ($same, $text, $kind, $citedTexts, &$errors) {
            $same(array_column($originals, 'id'), array_column($returned, 'id'), $where);
            foreach ($returned as $q => $r) {
                $orig = $originals[$q] ?? null;
                foreach (['stem', 'explanation'] as $f) {
                    $text((string) $r[$f], "{$where}/{$q}/{$f}");
                }
                if ($orig === null) {
                    continue;
                }
                $same(array_column($orig['options'], 'id'), array_column($r['options'], 'id'), "{$where}/{$q}/options");
                foreach ($r['options'] as $o) {
                    $text((string) $o['text'], "{$where}/{$q}/options");
                }
                if ($kind !== 'translate' && $citedTexts !== []) {
                    $merged = $orig;
                    $merged['stem'] = $r['stem'];
                    foreach ($merged['options'] as $i => $o) {
                        $merged['options'][$i]['text'] = $r['options'][$i]['text'] ?? $o['text'];
                    }
                    $errors = [...$errors, ...Checks::quizSupport($merged, $citedTexts, "{$where}/{$q}", (int) config('course_builder.quiz_support_min_overlap', 2))];
                }
            }
        };
        if ($type === 'lesson') {
            $text((string) $data['title'], 'title');
            $text((string) $data['summary'], 'summary');
            $same(array_column($input['objectives'], 'id'), array_column($data['objectives'], 'id'), 'objectives');
            $same(array_column($input['blocks'], 'id'), array_column($data['blocks'], 'id'), 'blocks');
            foreach ($data['blocks'] as $b => $block) {
                $text((string) $block['markdown'], "blocks/{$b}");
                if (LiaScriptRenderer::unsafe((string) $block['markdown'])) {
                    $errors[] = "blocks/{$b}: contains a macro, script or import";
                }
                $original = $input['blocks'][$b]['markdown'] ?? '';
                if (substr_count((string) $block['markdown'], '```') !== substr_count($original, '```')) {
                    $errors[] = "blocks/{$b}: keep the fenced code blocks exactly as they are";
                }
            }
            $questions($input['questions'], $data['questions'], 'questions');
            $questions($input['selfChecks'], $data['selfChecks'], 'selfChecks');
        } elseif ($type === 'final_test') {
            $questions($input['questions'], $data['questions'], 'questions');
        } else {
            foreach (['title', 'subtitle', 'description'] as $f) {
                $text((string) $data[$f], $f);
            }
            $same(array_column($input['objectives'], 'id'), array_column($data['objectives'], 'id'), 'objectives');
            $same(array_column($input['modules'], 'id'), array_column($data['modules'], 'id'), 'modules');
            if (count($data['faq']) !== count($input['faq'])) {
                $errors[] = 'faq: return every question once';
            }
        }

        return $errors;
    }

    /** Finishes the run when every step is done; with failures the author retries them. */
    public function advance(Run $run): void
    {
        $finished = DB::transaction(function () use ($run) {
            $locked = Run::query()->lockForUpdate()->find($run->id);
            if ($locked === null || in_array($locked->status, ['cancelled', 'failed', 'finished'], true)) {
                return null;
            }
            $steps = Step::query()->where('run_id', $locked->id)->get();
            if ($steps->contains(fn (Step $s) => in_array($s->status, ['pending', 'queued', 'running'], true))) {
                return null;
            }
            if ($steps->contains(fn (Step $s) => $s->status === 'failed')) {
                $locked->forceFill(['status' => 'needs_attention'])->save();

                return null;
            }
            $locked->forceFill(['status' => 'finished', 'stage' => 'done', 'finished_at' => now()])->save();

            return $locked;
        });
        if ($finished instanceof Run) {
            $this->assemble($finished);
        }
    }

    private function assemble(Run $run): void
    {
        $session = $run->session;
        $instruction = $run->input['instruction'];
        $base = Version::query()->findOrFail($run->input['baseVersionId']);
        $steps = Step::query()->where('run_id', $run->id)->get()->keyBy('key');
        $doc = self::merge($base->document, $steps->map(fn (Step $s) => (array) $s->output)->all(), $instruction);
        $changes = BlueprintDiff::compare($base->document, $doc);
        $this->progress($run);
        if ($session->current_version_id !== $base->id) {
            $this->events->text($session, $run, 'The course changed while this edit was running, so its result was not kept. Start the change again.');
            $this->events->runFinished($run);

            return;
        }
        $version = $this->versions->create($session, $doc, 'patch', 'ai', Version::PROPOSED, $base, $instruction['label'], null);
        $this->events->text($session, $run, sprintf('%s is ready: %d changes across the course. Review the diff and approve it to update the course; reject it to keep everything as it is.', $instruction['label'], count($changes)));
        $this->surfaces->patch($session, $run, $version, '', 'Whole course', $instruction['label'], 'proposed');
        $this->events->runFinished($run, ['versionId' => $version->id]);
    }

    /**
     * Merges step outputs onto the original document. Only text is taken from the model; ids,
     * citations, kinds, question types and the correct options stay as they were.
     *
     * @param array<string,array> $outputs by step key
     */
    public static function merge(array $doc, array $outputs, array $instruction): array
    {
        $questions = function (array $originals, array $returned) {
            $byId = [];
            foreach ($returned as $r) {
                $byId[$r['id']] = $r;
            }
            foreach ($originals as $i => $q) {
                $r = $byId[$q['id']] ?? null;
                if ($r === null) {
                    continue;
                }
                $originals[$i]['stem'] = trim($r['stem']);
                $originals[$i]['explanation'] = trim($r['explanation']);
                $options = [];
                foreach ($r['options'] as $o) {
                    $options[$o['id']] = trim($o['text']);
                }
                foreach ($q['options'] as $j => $o) {
                    $originals[$i]['options'][$j]['text'] = $options[$o['id']] ?? $o['text'];
                }
            }

            return $originals;
        };
        if (isset($outputs['course'])) {
            $c = $outputs['course'];
            $doc['course']['title'] = trim($c['title']);
            foreach (['subtitle', 'description'] as $f) {
                if (isset($doc['course'][$f]) && trim((string) $c[$f]) !== '') {
                    $doc['course'][$f] = trim($c[$f]);
                }
            }
            $byId = array_column($c['objectives'], 'text', 'id');
            foreach ($doc['course']['objectives'] as $i => $o) {
                $doc['course']['objectives'][$i]['text'] = trim($byId[$o['id']] ?? $o['text']);
            }
            $mods = array_column($c['modules'], null, 'id');
            foreach ($doc['modules'] as $i => $m) {
                if (isset($mods[$m['id']])) {
                    $doc['modules'][$i]['title'] = trim($mods[$m['id']]['title']);
                    if (isset($m['summary']) && trim((string) $mods[$m['id']]['summary']) !== '') {
                        $doc['modules'][$i]['summary'] = trim($mods[$m['id']]['summary']);
                    }
                }
            }
            foreach ($doc['course']['faq'] ?? [] as $i => $f) {
                if (isset($c['faq'][$i])) {
                    $doc['course']['faq'][$i]['question'] = trim($c['faq'][$i]['question']);
                    $doc['course']['faq'][$i]['answer'] = trim($c['faq'][$i]['answer']);
                }
            }
            if ($instruction['kind'] === 'translate') {
                $doc['course']['language'] = $instruction['value'];
            }
        }
        foreach ($doc['modules'] as $m => $module) {
            foreach ($module['lessons'] as $l => $lesson) {
                $o = $outputs['lesson:' . $lesson['id']] ?? null;
                if ($o === null) {
                    continue;
                }
                $lesson['title'] = trim($o['title']);
                if (isset($lesson['summary']) && trim((string) $o['summary']) !== '') {
                    $lesson['summary'] = trim($o['summary']);
                }
                $objectives = array_column($o['objectives'], 'text', 'id');
                foreach ($lesson['objectives'] as $i => $obj) {
                    $lesson['objectives'][$i]['text'] = trim($objectives[$obj['id']] ?? $obj['text']);
                }
                $blocks = array_column($o['blocks'], 'markdown', 'id');
                foreach ($lesson['blocks'] as $i => $block) {
                    $lesson['blocks'][$i]['markdown'] = trim($blocks[$block['id']] ?? $block['markdown']);
                }
                if (is_array($lesson['quiz'] ?? null)) {
                    $lesson['quiz']['questions'] = $questions($lesson['quiz']['questions'], $o['questions']);
                }
                if (!empty($lesson['selfChecks'])) {
                    $lesson['selfChecks'] = $questions($lesson['selfChecks'], $o['selfChecks']);
                }
                $doc['modules'][$m]['lessons'][$l] = $lesson;
            }
        }
        if (isset($outputs['final_test']) && is_array($doc['finalTest'] ?? null)) {
            $doc['finalTest']['questions'] = $questions($doc['finalTest']['questions'], $outputs['final_test']['questions']);
        }

        return $doc;
    }

    private function label(Run $run, Step $step): string
    {
        if ($step->key === 'course') {
            return 'The course details';
        }
        if ($step->key === 'final_test') {
            return 'The final test';
        }
        $base = Version::query()->find($run->input['baseVersionId']);

        return $base ? (Blueprint::find($base->document, (string) $step->elementId())['label'] ?? 'A lesson') : 'A lesson';
    }

    private function progress(Run $run): void
    {
        $session = $run->session;
        $base = Version::query()->find($run->input['baseVersionId']);
        $steps = Step::query()->where('run_id', $run->id)->get();
        $lessons = [];
        foreach ($steps as $step) {
            $title = $this->label($run, $step);
            if ($step->elementId() !== null && $base !== null) {
                $found = Blueprint::find($base->document, (string) $step->elementId());
                $title = ($found['label'] ?? 'Lesson') . ' ' . ($found['node']['title'] ?? '');
            }
            $lessons[] = array_filter([
                'id' => $step->key === 'course' || $step->key === 'final_test' ? $step->key : (string) $step->elementId(),
                'title' => mb_substr(trim($title), 0, 200),
                'module' => 'Whole-course edit',
                'status' => match ($step->status) { 'done' => 'done', 'failed' => 'failed', 'running', 'queued' => 'running', default => 'pending' },
                'citations' => 0,
                'questions' => 0,
                'costMicroUsd' => (int) $step->cost_micro_usd,
                'stepId' => $step->status === 'failed' ? $step->id : null,
                'error' => $step->error ? mb_substr($step->error, 0, 500) : null,
            ], fn ($v) => $v !== null);
        }
        $done = $steps->isNotEmpty() && $steps->every(fn (Step $s) => $s->status === 'done');
        $failed = $steps->contains(fn (Step $s) => $s->status === 'failed');
        $this->surfaces->progress($session, $run, [
            'runId' => $run->id,
            'status' => in_array($run->status, ['running', 'needs_attention', 'finished', 'failed', 'cancelled'], true) ? $run->status : 'running',
            'stages' => [['key' => 'global', 'label' => 'Rewriting the course', 'status' => $done ? 'done' : ($failed ? 'failed' : 'running')]],
            'lessons' => $lessons,
            'cost' => Surfaces::costProps($session),
        ]);
    }
}
