<?php

namespace Ulams\LearnerInsights\Remediation;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Models\AiCall;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\LearnerInsights\Models\Remediation;

/**
 * Generates one remediation (plan 7.2 step 4 and 5). The request holds the element, the fragments it
 * cites, the lesson objectives and a generic difficulty: never a learner id, name, e-mail, history
 * or free text. The output is checked against the schema, the provided fragments, the markup rules
 * and the grounding task; an unsupported claim triggers one regeneration, then the remediation
 * is marked failed and nothing is shown rather than an ungrounded explanation.
 */
class RemediationGenerator
{
    private const COMPONENTS = ['explanation' => 'Prose', 'worked_example' => 'WorkedExample', 'practice' => 'PracticeQuestion', 'elaboration' => 'ElaborativeQuestion'];

    public function __construct(private readonly LlmClient $llm, private readonly PromptRegistry $prompts, private readonly SchemaRegistry $schemas)
    {
    }

    public function generate(Remediation $remediation): void
    {
        $element = ElementContext::forElement($remediation->element_id);
        if ($element === null || $element->appliedVersionId !== $remediation->applied_version_id) {
            $remediation->forceFill(['status' => Remediation::RETIRED])->save();

            return;
        }
        if ($reason = $this->overBudget($remediation)) {
            $this->fail($remediation, [$reason]);

            return;
        }

        $fragments = Fragment::query()->whereIn('id', $element->fragmentIds)->get()->keyBy('id');
        if ($fragments->isEmpty()) {
            $this->fail($remediation, ['no_fragments']);

            return;
        }

        $calls = [];
        $corrections = [];
        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $result = $this->llm->generate($this->request($remediation, $element, $fragments->all(), $corrections));
                $calls = array_merge($calls, $result->callIds);
                $output = $result->data;
                $check = $this->ground($remediation, $output['blocks'], $fragments->all());
                $calls = array_merge($calls, $check['callIds']);
                if ($check['unsupported'] === []) {
                    $this->store($remediation, $output, $calls);

                    return;
                }
                $corrections = $check['unsupported'];
            }
        } catch (LlmException $e) {
            $this->fail($remediation, [$e->reason ?? 'llm_error'], $calls);

            return;
        }

        $this->fail($remediation, ['ungrounded'], $calls);
    }

    /** @param array<string,Fragment> $fragments */
    private function request(Remediation $r, ElementContext $element, array $fragments, array $corrections): LlmRequest
    {
        $source = "<source_fragments untrusted=\"true\">\n";
        foreach ($fragments as $id => $f) {
            $source .= '<fragment id="' . $id . '" section="' . PromptContext::esc($f->label()) . "\">\n" . PromptContext::esc($f->text) . "\n</fragment>\n";
        }
        $source .= '</source_fragments>';

        $input = [
            'kind' => $r->kind,
            'language' => $r->language,
            'element' => $this->elementInput($element),
            'lesson' => ['title' => $element->lesson['title'] ?? '', 'objectives' => array_map(fn ($o) => $o['text'] ?? '', $element->lesson['objectives'] ?? [])],
            'difficulty' => 'Some learners find this part hard and struggled with it more than once.',
        ];
        if ($corrections !== []) {
            $input['corrections'] = $corrections;
        }
        $known = array_fill_keys(array_keys($fragments), true);

        return new LlmRequest(
            task: 'remediation',
            prompt: $this->prompts->get('learner-insights', 'remediation'),
            blocks: [ContentBlock::text($source, true), ContentBlock::text("Write the extra help described in task_input.\n<task_input>" . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</task_input>')],
            schema: (array) json_decode((string) file_get_contents(__DIR__ . '/../../resources/schemas/remediation.json'), true),
            subject: ['type' => Remediation::SUBJECT_TYPE, 'id' => $r->id],
            userId: null,
            validator: fn (array $data) => $this->validate($r->kind, $data, $known, $element),
        );
    }

    /** @return array<string,mixed> */
    private function elementInput(ElementContext $element): array
    {
        $node = $element->node;
        if ($element->type === 'question') {
            // the quiz question and its answer are given so the help can explain the idea; the output must not give it away
            return ['type' => 'question', 'stem' => $node['stem'] ?? '', 'options' => array_map(fn ($o) => ['text' => $o['text'], 'correct' => (bool) $o['correct']], $node['options'] ?? []), 'explanation' => $node['explanation'] ?? ''];
        }
        if ($element->type === 'lesson') {
            return ['type' => 'lesson', 'title' => $node['title'] ?? '', 'blocks' => array_map(fn ($b) => $b['markdown'], $element->lesson['blocks'] ?? [])];
        }

        return ['type' => 'block', 'markdown' => $node['markdown'] ?? ''];
    }

    /** @param array<string,bool> $known @return string[] */
    private function validate(string $kind, array $data, array $known, ElementContext $element): array
    {
        $errors = [];
        foreach ($data['blocks'] ?? [] as $i => $block) {
            $where = "blocks[$i]";
            if (($block['kind'] ?? null) !== $kind) {
                $errors[] = "$where: kind must be $kind";
            }
            $errors = array_merge($errors, Checks::markup((string) ($block['markdown'] ?? ''), $where), Checks::markup((string) ($block['title'] ?? ''), "$where.title"), Checks::citations($block['citations'] ?? [], $known, $where));
            if ($kind === 'practice') {
                $options = $block['practice']['options'] ?? [];
                if (($block['practice'] ?? null) === null) {
                    $errors[] = "$where: practice needs a practice object";
                } elseif (count(array_filter($options, fn ($o) => $o['correct'])) !== 1) {
                    $errors[] = "$where: exactly one option must be correct";
                } elseif ($this->overlap((string) $block['practice']['stem'], (string) ($element->node['stem'] ?? '')) >= 0.8) {
                    $errors[] = "$where: the practice question repeats the question the learner struggled with";
                }
                foreach ($options as $j => $option) {
                    $errors = array_merge($errors, Checks::markup((string) $option['text'], "$where.options[$j]"));
                }
            } elseif (isset($block['practice'])) {
                $errors[] = "$where: only practice blocks have a practice object";
            }
        }

        return $errors;
    }

    /** Share of the words of $a that also occur in $b (0 to 1). */
    private function overlap(string $a, string $b): float
    {
        $words = fn (string $s) => array_values(array_unique(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        $wa = $words($a);
        $wb = $words($b);

        return $wa === [] ? 0.0 : count(array_intersect($wa, $wb)) / count($wa);
    }

    /**
     * The grounding task of the Course Builder: do the cited fragments support every claim?
     *
     * @param array<int,array> $blocks
     * @param array<string,Fragment> $fragments
     * @return array{unsupported:array,callIds:string[]}
     */
    private function ground(Remediation $r, array $blocks, array $fragments): array
    {
        $mini = "<source_document untrusted=\"true\">\n";
        foreach ($blocks as $block) {
            foreach ($block['citations'] as $id) {
                if (isset($fragments[$id])) {
                    $mini .= '<fragment id="' . $id . '" section="' . PromptContext::esc($fragments[$id]->label()) . "\">\n" . PromptContext::esc($fragments[$id]->text) . "\n</fragment>\n";
                }
            }
        }
        $mini .= '</source_document>';
        $input = ['blocks' => array_map(fn ($b, $i) => ['index' => $i, 'markdown' => $b['markdown'] . (isset($b['practice']) ? "\n" . $b['practice']['explanation'] : ''), 'citations' => $b['citations']], $blocks, array_keys($blocks))];
        $count = count($blocks);
        $result = $this->llm->generate(new LlmRequest(
            task: 'grounding',
            prompt: $this->prompts->get('course-builder', 'grounding'),
            blocks: [ContentBlock::text($mini), ContentBlock::text("Check each block against its cited fragments.\n<task_input>" . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</task_input>')],
            schema: $this->schemas->get('outputs/grounding'),
            subject: ['type' => Remediation::SUBJECT_TYPE, 'id' => $r->id],
            userId: null,
            validator: fn (array $d) => array_values(array_filter(array_map(fn ($u) => ($u['blockIndex'] ?? -1) >= $count ? 'unsupported: blockIndex out of range' : null, $d['unsupported'] ?? []))),
        ));

        return ['unsupported' => array_map(fn ($u) => ['block' => $u['blockIndex'], 'claim' => $u['claim'], 'reason' => $u['reason']], $result->data['unsupported'] ?? []), 'callIds' => $result->callIds];
    }

    /** @param string[] $calls */
    private function store(Remediation $r, array $output, array $calls): void
    {
        $blocks = array_map(fn (array $b) => [
            'component' => self::COMPONENTS[$b['kind']],
            'props' => array_filter(['title' => $b['title'], 'markdown' => $b['markdown'], 'practice' => $b['practice'] ?? null], fn ($v) => $v !== null),
            'citations' => $b['citations'],
        ], $output['blocks']);
        $r->forceFill([
            'document' => ['summary' => $output['summary'], 'blocks' => $blocks],
            'citations' => array_values(array_unique(array_merge(...array_column($output['blocks'], 'citations')))),
            'status' => Remediation::READY, 'ai_call_ids' => $calls, 'generated_at' => CarbonImmutable::now(),
        ])->save();
    }

    /** @param string[] $why @param string[] $calls */
    private function fail(Remediation $r, array $why, array $calls = []): void
    {
        $r->forceFill(['status' => Remediation::FAILED, 'flags' => $why, 'ai_call_ids' => $calls])->save();
    }

    /** The tenant or course spent its monthly remediation budget. */
    private function overBudget(Remediation $r): ?string
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $spent = fn ($q) => (int) $q->where('subject_type', Remediation::SUBJECT_TYPE)->where('created_at', '>=', $month)->sum('cost_micro_usd');
        if ($spent(AiCall::query()) >= (int) round(((float) config('learner_insights.remediation.tenant_monthly_usd')) * 1000000)) {
            return 'tenant_budget';
        }
        $ids = DB::table('learner_remediations')->where('course_id', $r->course_id)->pluck('id');
        if ($spent(AiCall::query()->whereIn('subject_id', $ids)) >= (int) round(((float) config('learner_insights.remediation.course_monthly_usd')) * 1000000)) {
            return 'course_budget';
        }

        return null;
    }
}
