<?php

namespace Ulams\Compliance\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\Compliance\Models\Competency;
use Ulams\Compliance\Models\CourseCompetency;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;

/**
 * AI proposes, the author approves: one call suggests which competencies of the framework a course teaches. Each
 * suggestion points at the lessons that teach it and stays `suggested` until the author confirms it. Course text
 * is passed as untrusted data; ids the model returns are checked against the framework and the course.
 */
class CompetencySuggester
{
    public function __construct(private LlmClient $llm, private PromptRegistry $prompts)
    {
    }

    /**
     * Replaces the pending suggestions of the course (confirmed tags are never touched).
     *
     * @return list<CourseCompetency>
     */
    public function suggest(Course $course, ?User $by = null): array
    {
        $framework = Competency::query()->with('parent')->orderBy('id')->get();
        $lessons = $course->lessons()->orderBy('order')->get(['id', 'title', 'summary']);
        if ($framework->isEmpty() || $lessons->isEmpty()) {
            return [];
        }
        $competencyIds = $framework->pluck('id')->all();
        $lessonIds = $lessons->pluck('id')->all();

        $result = $this->llm->generate(new LlmRequest(
            task: 'competency_tags',
            prompt: $this->prompts->get('compliance', 'competency_tags'),
            blocks: [
                ContentBlock::text($this->frameworkBlock($framework), cache: true),
                ContentBlock::text($this->courseBlock($course, $lessons)),
            ],
            schema: json_decode((string) file_get_contents(__DIR__ . '/../../resources/schemas/competency_tags.json'), true),
            subject: ['type' => 'compliance_course', 'id' => $course->getKey()],
            userId: $by?->getKey(),
            validator: fn (array $data) => $this->errors($data, $competencyIds, $lessonIds),
        ));

        return DB::transaction(function () use ($course, $result) {
            CourseCompetency::query()->where('course_id', $course->getKey())->where('status', CourseCompetency::SUGGESTED)->delete();
            $confirmed = CourseCompetency::query()->where('course_id', $course->getKey())->pluck('competency_id')->all();
            $created = [];
            foreach ($result->data['suggestions'] ?? [] as $suggestion) {
                if (in_array($suggestion['competencyId'], $confirmed, true)) {
                    continue;
                }
                $created[] = CourseCompetency::query()->create([
                    'course_id' => $course->getKey(),
                    'competency_id' => $suggestion['competencyId'],
                    'status' => CourseCompetency::SUGGESTED,
                    'source' => 'ai',
                    'rationale' => $suggestion['rationale'],
                    'lesson_ids' => array_values(array_unique($suggestion['lessonIds'])),
                    'ai_call_id' => $result->callIds === [] ? null : (string) $result->callIds[array_key_last($result->callIds)],
                ]);
                $confirmed[] = $suggestion['competencyId'];
            }

            return $created;
        });
    }

    /**
     * @param list<int> $competencyIds
     * @param list<int> $lessonIds
     * @return list<string>
     */
    public function errors(array $data, array $competencyIds, array $lessonIds): array
    {
        $errors = [];
        foreach ($data['suggestions'] ?? [] as $index => $suggestion) {
            if (!in_array($suggestion['competencyId'] ?? null, $competencyIds, true)) {
                $errors[] = "suggestions.{$index}.competencyId is not a competency of the framework.";
            }
            foreach ($suggestion['lessonIds'] ?? [] as $lessonId) {
                if (!in_array($lessonId, $lessonIds, true)) {
                    $errors[] = "suggestions.{$index}.lessonIds contains {$lessonId}, which is not a lesson of the course.";
                }
            }
        }

        return $errors;
    }

    private function frameworkBlock($framework): string
    {
        $lines = $framework->map(fn (Competency $c) => sprintf('<competency id="%d"%s>%s</competency>', $c->getKey(), $c->parent ? ' area="' . e($c->parent->name) . '"' : '', e($c->name)));

        return "<framework>\n" . $lines->implode("\n") . "\n</framework>";
    }

    private function courseBlock(Course $course, $lessons): string
    {
        $items = $lessons->map(fn ($l) => sprintf('<lesson id="%d" title="%s">%s</lesson>', $l->getKey(), e((string) $l->title), e(mb_substr(strip_tags((string) $l->summary), 0, 600))));

        return sprintf(
            "<course untrusted=\"true\" title=\"%s\">\n%s\n%s\n</course>",
            e((string) $course->title),
            e(mb_substr(strip_tags((string) $course->description), 0, 2000)),
            $items->implode("\n"),
        );
    }
}
