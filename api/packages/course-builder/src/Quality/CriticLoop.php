<?php

namespace Ulams\CourseBuilder\Quality;

use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\CourseBuilder\Pipeline\PatchSchemas;
use Ulams\CourseBuilder\Pipeline\PatchService;
use Ulams\CourseBuilder\Pipeline\PromptContext;

/**
 * Generate → critique → fix for one lesson (ADR 0051). Critics: mechanics and accessibility
 * (deterministic), pedagogy including the four design pillars and UX (light model on the structured
 * lesson), and grounding (the existing check, recorded here). Failures that concern the lesson text go to
 * a `refine` call; the loop stops after `refine_max_iterations` fixes. What still fails is flagged to the
 * author. The model critics share a per-session budget; once it is spent they are `skipped` and shown so.
 */
final class CriticLoop
{
    public const MODEL_TASKS = ['critic_pedagogy', 'critic_ux', 'refine'];

    public function __construct(
        private readonly Llm $llm,
        private readonly PromptContext $context,
        private readonly MechanicsCritic $mechanics,
        private readonly AccessibilityCritic $accessibility,
    ) {
    }

    /** USD the model critics and fixes of a session may still use, in micro-USD (negative: spent). */
    public function remainingMicroUsd(Session $session): int
    {
        $spent = (int) AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->whereIn('task', self::MODEL_TASKS)->sum('cost_micro_usd');

        return (int) round((float) config('course_builder.quality.critic_usd', 1) * 1_000_000) - $spent;
    }

    /**
     * @param array<string,mixed> $lesson the lesson with blocks, selfChecks, interaction and quiz
     * @param string[] $groundingFlags what the grounding check left unsupported
     * @param array<string,bool> $known fragment ids of the session
     * @return array{blocks:array,flags:string[],rounds:array<int,array<string,mixed>>,cost:int}
     */
    public function run(Session $session, Run $run, array $outline, array $lesson, array $groundingFlags, array $known): array
    {
        $max = max(0, (int) config('course_builder.quality.refine_max_iterations', 2));
        $rounds = [];
        $cost = 0;
        $final = [];
        for ($iteration = 0; $iteration <= $max; $iteration++) {
            $results = [];
            $results[] = $this->record('grounding', $iteration === 0 ? array_map(fn ($f) => ['elementId' => $lesson['id'], 'problem' => $f], $groundingFlags) : [], ['iteration' => $iteration]);
            $results[] = $this->record('mechanics', $this->mechanics->check($lesson));
            $results[] = $this->record('accessibility', $this->accessibility->check($lesson));
            foreach (['pedagogy' => fn () => $this->pedagogy($session, $run, $lesson), 'ux' => fn () => $this->ux($session, $run, $lesson)] as $critic => $call) {
                if (!config('course_builder.quality.llm_critics', true)) {
                    continue;
                }
                if ($this->remainingMicroUsd($session) <= 0) {
                    $results[] = ['critic' => $critic, 'verdict' => 'skipped', 'issues' => [['elementId' => $lesson['id'], 'problem' => 'The critic budget of this course is used up.']], 'callId' => null];
                    continue;
                }
                try {
                    $out = $call();
                    $cost += $out['cost'];
                    $results[] = ['critic' => $critic, 'verdict' => $out['issues'] === [] ? 'pass' : 'fail', 'issues' => $out['issues'], 'callId' => $out['callId']];
                } catch (LlmException $e) {
                    $results[] = ['critic' => $critic, 'verdict' => 'skipped', 'issues' => [['elementId' => $lesson['id'], 'problem' => 'The critic could not run: ' . mb_substr($e->getMessage(), 0, 200)]], 'callId' => null];
                }
            }
            $rounds[] = ['iteration' => $iteration, 'critics' => $results];
            $final = array_values(array_filter($results, fn ($r) => $r['verdict'] === 'fail'));
            if ($final === [] || $iteration === $max) {
                break;
            }
            // only text problems can be fixed by rewriting the blocks; the grounding result is not re-checked after a fix
            $blockIds = array_column($lesson['blocks'], 'id');
            $fixable = [];
            foreach ($final as $r) {
                foreach ($r['issues'] as $issue) {
                    if ($r['critic'] !== 'grounding' && in_array($issue['elementId'], [...$blockIds, $lesson['id']], true)) {
                        $fixable[] = ['critic' => $r['critic']] + $issue;
                    }
                }
            }
            if ($fixable === [] || $this->remainingMicroUsd($session) <= 0) {
                break;
            }
            try {
                $fixed = $this->refine($session, $run, $lesson, $fixable, $known);
            } catch (LlmException) {
                break;
            }
            $cost += $fixed['cost'];
            $lesson['blocks'] = $fixed['blocks'];
        }

        $flags = [];
        foreach ($final as $r) {
            if ($r['critic'] === 'grounding') {
                continue; // already flagged by the grounding step
            }
            foreach ($r['issues'] as $issue) {
                $flags[] = mb_substr(ucfirst($r['critic']) . ' review: ' . $issue['problem'], 0, 500);
            }
        }

        return ['blocks' => $lesson['blocks'], 'flags' => array_slice(array_values(array_unique($flags)), 0, 20), 'rounds' => $rounds, 'cost' => $cost];
    }

    /** @param array<int,array{elementId:string,problem:string}> $issues */
    private function record(string $critic, array $issues, array $meta = []): array
    {
        return ['critic' => $critic, 'verdict' => $issues === [] ? 'pass' : 'fail', 'issues' => array_slice($issues, 0, 20), 'callId' => null];
    }

    private function lessonInput(array $lesson): array
    {
        $question = fn (array $q) => ['id' => $q['id'], 'type' => $q['type'], 'stem' => $q['stem'], 'options' => array_column($q['options'], 'text'), 'explanation' => $q['explanation']];
        $activity = $lesson['interaction'] ?? null;

        return [
            'lesson' => ['id' => $lesson['id'], 'title' => $lesson['title'], 'format' => $lesson['contentType'] ?? 'richtext', 'minutes' => $lesson['minutes']],
            'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $lesson['objectives']),
            'blocks' => array_map(fn ($b) => ['id' => $b['id'], 'kind' => $b['kind'], 'markdown' => $b['markdown']], $lesson['blocks']),
            'selfChecks' => array_map($question, $lesson['selfChecks'] ?? []),
            'activity' => is_array($activity) ? ['id' => $activity['id'], 'kind' => $activity['kind'], 'title' => $activity['title'], 'library' => $activity['library'] ?? null] : null,
            'quiz' => array_map($question, $lesson['quiz']['questions'] ?? []),
        ];
    }

    /** @return array{issues:array,callId:?string,cost:int} */
    private function pedagogy(Session $session, Run $run, array $lesson): array
    {
        $input = $this->lessonInput($lesson);
        $ids = array_column($lesson['objectives'], 'id');
        $hasActivity = $input['selfChecks'] !== [] || $input['quiz'] !== [] || $input['activity'] !== null;
        $result = $this->llm->generate($session, $run, 'critic_pedagogy', [
            $this->context->instruction('Review the lesson in task_input.', $input),
        ], function (array $data) use ($ids) {
            $got = array_column($data['objectives'] ?? [], 'id');
            sort($got);
            $want = $ids;
            sort($want);

            return $got === $want ? [] : ['objectives: report every objective id of the lesson exactly once'];
        });
        $d = $result->data;
        $issues = array_map(fn ($i) => ['elementId' => (string) $i['elementId'], 'problem' => (string) $i['problem']], $d['issues']);
        foreach ($d['objectives'] as $o) {
            if (!$o['covered']) {
                $issues[] = ['elementId' => $lesson['id'], 'problem' => 'An objective is not taught: ' . $o['reason']];
            }
        }
        if (!$d['difficultyIncreases']['ok']) {
            $issues[] = ['elementId' => $lesson['id'], 'problem' => 'Difficulty does not build up: ' . $d['difficultyIncreases']['reason']];
        }
        if (!$d['noAnswerLeak']['ok']) {
            $issues[] = ['elementId' => $lesson['id'], 'problem' => 'A hint or explanation gives the answer away: ' . $d['noAnswerLeak']['reason']];
        }
        foreach ($d['pillars'] as $pillar => $check) {
            if (!$check['ok'] && ($hasActivity || $pillar === 'alignment')) {
                $issues[] = ['elementId' => $lesson['id'], 'problem' => "Design pillar “{$pillar}”: " . $check['reason']];
            }
        }

        return ['issues' => array_slice($issues, 0, 12), 'callId' => $result->callIds[0] ?? null, 'cost' => $result->costMicroUsd];
    }

    /** @return array{issues:array,callId:?string,cost:int} */
    private function ux(Session $session, Run $run, array $lesson): array
    {
        $result = $this->llm->generate($session, $run, 'critic_ux', [$this->context->instruction('Review the lesson in task_input.', $this->lessonInput($lesson))]);
        $issues = array_map(fn ($i) => ['elementId' => (string) $i['elementId'], 'problem' => (string) $i['problem']], $result->data['issues']);

        return ['issues' => $result->data['verdict'] === 'fail' ? $issues : [], 'callId' => $result->callIds[0] ?? null, 'cost' => $result->costMicroUsd];
    }

    /**
     * Rewrites the blocks to fix the issues (the lesson patch schema and validators).
     *
     * @param array<int,array{critic:string,elementId:string,problem:string}> $issues
     * @return array{blocks:array,cost:int}
     */
    private function refine(Session $session, Run $run, array $lesson, array $issues, array $known): array
    {
        $map = $this->context->fragmentMap($session);
        $objectiveIds = array_column($lesson['objectives'], 'id');
        $mini = "<source_document untrusted=\"true\">\n";
        foreach ($lesson['citations'] ?? [] as $id) {
            if (isset($map[$id])) {
                $mini .= '<fragment id="' . $id . '" section="' . PromptContext::esc($map[$id]->label()) . "\">\n" . PromptContext::esc($map[$id]->text) . "\n</fragment>\n";
            }
        }
        $mini .= '</source_document>';
        $element = PatchService::editable('lesson', $lesson);
        $result = $this->llm->generate($session, $run, 'refine', [
            \Ulams\Ai\Dto\ContentBlock::text($mini),
            $this->context->instruction('Fix the issues in the lesson in task_input.', ['lesson' => $element, 'issues' => array_map(fn ($i) => ['elementId' => $i['elementId'], 'problem' => $i['problem']], array_slice($issues, 0, 12))]),
        ], fn (array $data) => PatchService::validate('lesson', $data['replacement'] ?? [], $known, $objectiveIds), PatchSchemas::for('lesson'));
        $merged = PatchService::merge('lesson', $lesson, $result->data['replacement']);

        return ['blocks' => $merged['blocks'], 'cost' => $result->costMicroUsd];
    }
}
