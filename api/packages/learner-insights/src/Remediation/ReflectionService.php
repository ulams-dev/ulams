<?php

namespace Ulams\LearnerInsights\Remediation;

use Illuminate\Support\Facades\Cache;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\LearnerInsights\Models\Remediation;
use Ulams\LearnerInsights\Models\RemediationAssignment;

/**
 * Feynman reflection (plan 7.2 step 8): the learner explicitly submits their own explanation and gets
 * feedback grounded in the fragments of the element. The text goes to the model once and is never
 * stored: the call is logged without a user and without the text beyond the provider's own log.
 */
class ReflectionService
{
    public function __construct(private readonly LlmClient $llm, private readonly PromptRegistry $prompts)
    {
    }

    public function remaining(int $userId): int
    {
        return max(0, (int) config('learner_insights.remediation.reflections_per_day') - (int) Cache::get($this->counterKey($userId), 0));
    }

    /**
     * @return array{correct:string,missing:string,followUp:string,citations:array<int,array{fragmentId:string,label:string}>}|null null when the daily limit is reached
     */
    public function feedback(RemediationAssignment $assignment, string $text): ?array
    {
        $userId = (int) $assignment->user_id;
        if ($this->remaining($userId) === 0) {
            return null;
        }
        $remediation = $assignment->remediation;
        $element = ElementContext::forElement($remediation->element_id);
        $fragments = $element ? Fragment::query()->whereIn('id', $element->fragmentIds)->get()->keyBy('id') : collect();
        if ($fragments->isEmpty()) {
            return null;
        }
        Cache::add($this->counterKey($userId), 0, now()->endOfDay());
        Cache::increment($this->counterKey($userId));

        $source = "<source_fragments untrusted=\"true\">\n";
        foreach ($fragments as $id => $f) {
            $source .= '<fragment id="' . $id . '" section="' . PromptContext::esc($f->label()) . "\">\n" . PromptContext::esc($f->text) . "\n</fragment>\n";
        }
        $source .= '</source_fragments>';
        $known = array_fill_keys($fragments->keys()->all(), true);

        $result = $this->llm->generate(new LlmRequest(
            task: 'reflection_feedback',
            prompt: $this->prompts->get('learner-insights', 'reflection_feedback'),
            blocks: [ContentBlock::text($source, true), ContentBlock::text('<learner_text untrusted="true">' . PromptContext::esc($text) . '</learner_text>')],
            schema: (array) json_decode((string) file_get_contents(__DIR__ . '/../../resources/schemas/reflection_feedback.json'), true),
            subject: ['type' => Remediation::SUBJECT_TYPE, 'id' => $remediation->id],
            userId: null,
            validator: fn (array $d) => array_merge(
                Checks::citations($d['citations'] ?? [], $known, 'citations'),
                Checks::markup((string) ($d['correct'] ?? '') . ' ' . ($d['missing'] ?? '') . ' ' . ($d['followUp'] ?? ''), 'feedback'),
            ),
        ));
        $d = $result->data;

        return [
            'correct' => $d['correct'], 'missing' => $d['missing'], 'followUp' => $d['followUp'],
            'citations' => array_map(fn ($id) => ['fragmentId' => $id, 'label' => $fragments[$id]->label()], $d['citations']),
        ];
    }

    private function counterKey(int $userId): string
    {
        return 'learner-insights:reflections:' . $userId . ':' . now()->format('Y-m-d');
    }
}
