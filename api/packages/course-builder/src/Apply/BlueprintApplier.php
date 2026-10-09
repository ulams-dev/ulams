<?php

namespace Ulams\CourseBuilder\Apply;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Http\Requests\CreateTopicAPIRequest;
use Ulams\Courses\Repositories\Contracts\CourseRepositoryContract;
use Ulams\Courses\Repositories\Contracts\LessonRepositoryContract;
use Ulams\Courses\Repositories\Contracts\TopicRepositoryContract;
use Ulams\Courses\Services\Contracts\CourseServiceContract;
use Ulams\Pages\Http\Services\Contracts\PageServiceContract;
use Ulams\TopicTypeGift\Dtos\GiftQuestionDto;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/**
 * Applies a blueprint version to the LMS through the domain services only (ADR 0010): course and
 * lesson repositories, `TopicRepositoryContract::createFromRequest/updateFromRequest` with synthetic
 * requests (the pattern of courses-import-export), the GIFT question service with GIFT rendered by
 * our code, `CourseServiceContract::sort` and the pages service for the landing document.
 *
 * Mapping: course → course; module → lesson; blueprint lesson → RichText topic (+ a GIFT quiz topic
 * when it has a quiz); final test → a "Final test" lesson with a GIFT quiz topic; landing → page.
 * The entity map holds a fingerprint of each element as applied, so a re-apply creates new elements,
 * updates changed ones, deletes removed ones and leaves the rest untouched. Everything runs in one
 * transaction as the author; the course is created unpublished.
 */
final class BlueprintApplier
{
    public function __construct(
        private readonly CourseRepositoryContract $courses,
        private readonly LessonRepositoryContract $lessons,
        private readonly TopicRepositoryContract $topics,
        private readonly GiftQuestionServiceContract $questions,
        private readonly CourseServiceContract $courseService,
        private readonly PageServiceContract $pages,
    ) {
    }

    /**
     * The desired LMS entities of a document, keyed by "<entity type>:<element id>".
     *
     * @return array<string,array{type:string,element:string,fingerprint:string,data:array,parent:?string,order:int}>
     */
    public function desired(array $doc, array $brief = []): array
    {
        $out = [];
        $add = function (string $type, string $element, array $data, ?string $parent, int $order) use (&$out) {
            $out["{$type}:{$element}"] = ['type' => $type, 'element' => $element, 'data' => $data, 'parent' => $parent, 'order' => $order,
                'fingerprint' => Blueprint::fingerprint([$data, $parent, $order])];
        };
        $course = $doc['course'];
        $add('course', $course['id'], array_filter([
            'title' => mb_substr($course['title'], 0, 255),
            'subtitle' => isset($course['subtitle']) ? mb_substr($course['subtitle'], 0, 255) : null,
            'summary' => $course['subtitle'] ?? null,
            'description' => $course['description'] ?? null,
            'language' => substr((string) ($course['language'] ?? 'en'), 0, 2),
            'level' => $brief['level'] ?? null,
            'target_group' => isset($course['audience']) ? mb_substr($course['audience'], 0, 255) : null,
            'duration' => Blueprint::stats($doc)['minutes'] . ' min',
        ], fn ($v) => $v !== null), null, 0);

        $labels = $this->labels($doc);
        $sourceTitle = (string) ($doc['sources'][0]['title'] ?? '');
        foreach ($doc['modules'] as $m => $module) {
            $add('lesson', $module['id'], array_filter(['title' => $module['title'], 'summary' => $module['summary'] ?? null]), "course:{$course['id']}", $m + 1);
            foreach ($module['lessons'] as $l => $lesson) {
                $add('topic', $lesson['id'], [
                    'title' => $lesson['title'],
                    'summary' => $lesson['summary'] ?? null,
                    'duration' => $lesson['minutes'] . ' min',
                    'value' => LessonMarkdown::render($lesson, $labels, $sourceTitle),
                ], "lesson:{$module['id']}", $l * 2 + 1);
                if (is_array($lesson['quiz'] ?? null) && $lesson['quiz']['questions'] !== []) {
                    $this->addQuiz($add, $lesson['quiz'], 'Quiz: ' . $lesson['title'], "Check what you learned in “{$lesson['title']}”.", "lesson:{$module['id']}", $l * 2 + 2, null);
                }
            }
        }
        if (is_array($doc['finalTest'] ?? null) && $doc['finalTest']['questions'] !== []) {
            $final = $doc['finalTest'];
            $add('lesson', $final['id'], ['title' => 'Final test', 'summary' => 'Questions across the whole course.'], "course:{$course['id']}", count($doc['modules']) + 1);
            $this->addQuiz($add, $final, 'Final test', 'Answer the questions to finish the course.', "lesson:{$final['id']}", 1, (int) ($final['passScore'] ?? 70));
        }
        if (is_array($doc['pages']['landing'] ?? null)) {
            $add('page', $course['id'], ['title' => mb_substr($course['title'], 0, 255), 'content' => json_encode($doc['pages']['landing'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], "course:{$course['id']}", 0);
        }

        return $out;
    }

    private function addQuiz(callable $add, array $quiz, string $title, string $intro, string $parent, int $order, ?int $passScore): void
    {
        $add('quiz_topic', $quiz['id'], array_filter(['title' => $title, 'value' => $intro, 'min_pass_score' => $passScore], fn ($v) => $v !== null), $parent, $order);
        foreach ($quiz['questions'] as $q => $question) {
            $add('gift_question', $question['id'], ['value' => GiftRenderer::render($question)], "quiz_topic:{$quiz['id']}", $q + 1);
        }
    }

    /** @return array<string,array{type:string,element:string,fingerprint:string,data:array,parent:?string,order:int}> */
    private function map(Session $session): array
    {
        return EntityMapEntry::query()->where('session_id', $session->id)->get()
            ->mapWithKeys(fn (EntityMapEntry $e) => ["{$e->entity_type}:{$e->element_id}" => $e])->all();
    }

    /**
     * What an apply would do (the ApplySummary): counts per entity kind and warnings.
     *
     * @return array<string,mixed>
     */
    public function plan(Session $session, array $doc): array
    {
        $desired = $this->desired($doc, (array) $session->brief);
        $map = $this->map($session);
        $zero = ['courses' => 0, 'lessons' => 0, 'topics' => 0, 'questions' => 0, 'pages' => 0];
        $create = $update = $delete = $zero;
        $bucket = fn (string $type) => match ($type) {
            'course' => 'courses',
            'lesson' => 'lessons',
            'topic', 'quiz_topic' => 'topics',
            'gift_question' => 'questions',
            'page' => 'pages',
        };
        foreach ($desired as $key => $item) {
            if (!isset($map[$key])) {
                $create[$bucket($item['type'])]++;
            } elseif ($map[$key]->fingerprint !== $item['fingerprint']) {
                $update[$bucket($item['type'])]++;
            }
        }
        foreach ($map as $key => $entry) {
            if (!isset($desired[$key])) {
                $delete[$bucket($entry->entity_type)]++;
            }
        }
        $known = array_fill_keys($session->fragmentIds(), true);
        $warnings = array_map(fn ($e) => str_starts_with($e, 'warning: ') ? substr($e, 9) : $e, Checks::blueprint($doc, $known));

        return [
            'firstApply' => $session->course_id === null,
            'create' => $create,
            'update' => $update,
            'delete' => $delete,
            'warnings' => array_slice(array_map(fn ($w) => mb_substr($w, 0, 500), $warnings), 0, 40),
        ] + ($session->course_id ? ['courseId' => (int) $session->course_id] : []);
    }

    /** Applies a version as the author. Returns the course id. */
    public function apply(Session $session, Version $version, Authenticatable $author): int
    {
        $previous = Auth::user();
        Auth::setUser($author);
        try {
            return DB::transaction(fn () => $this->applyAs($session, $version, $author));
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            } else {
                Auth::forgetUser();
            }
        }
    }

    private function applyAs(Session $session, Version $version, Authenticatable $author): int
    {
        $doc = $version->document;
        $desired = $this->desired($doc, (array) $session->brief);
        $map = $this->map($session);
        $ids = [];
        foreach ($map as $key => $entry) {
            $ids[$key] = $entry->entity_id;
        }
        $remember = function (array $item, int $entityId) use ($session, $version, &$ids) {
            EntityMapEntry::query()->updateOrCreate(
                ['session_id' => $session->id, 'element_id' => $item['element'], 'entity_type' => $item['type']],
                ['entity_id' => $entityId, 'fingerprint' => $item['fingerprint'], 'applied_version_id' => $version->id],
            );
            $ids["{$item['type']}:{$item['element']}"] = $entityId;
        };
        $changed = fn (string $key, array $item) => !isset($map[$key]) || $map[$key]->fingerprint !== $item['fingerprint'];

        // deletions first (questions, topics, lessons), so removed elements never linger
        foreach (['gift_question', 'quiz_topic', 'topic', 'lesson', 'page'] as $type) {
            foreach ($map as $key => $entry) {
                if ($entry->entity_type !== $type || isset($desired[$key])) {
                    continue;
                }
                match ($type) {
                    'gift_question' => $this->questions->delete($entry->entity_id),
                    'quiz_topic', 'topic' => $this->topics->delete($entry->entity_id),
                    'lesson' => $this->lessons->delete($entry->entity_id),
                    'page' => $this->pages->deleteById($entry->entity_id),
                };
                $entry->delete();
                unset($ids[$key]);
            }
        }

        foreach (['course', 'lesson', 'topic', 'quiz_topic', 'gift_question', 'page'] as $type) {
            foreach ($desired as $key => $item) {
                if ($item['type'] !== $type || !$changed($key, $item)) {
                    continue;
                }
                $parentId = $item['parent'] !== null ? ($ids[$item['parent']] ?? null) : null;
                $existing = $ids[$key] ?? null;
                $entityId = match ($type) {
                    'course' => $this->course($item, $existing, (int) $author->getAuthIdentifier()),
                    'lesson' => $this->lesson($item, $existing, (int) $parentId),
                    'topic' => $this->topic($item, $existing, (int) $parentId, RichText::class),
                    'quiz_topic' => $this->topic($item, $existing, (int) $parentId, GiftQuiz::class),
                    'gift_question' => $this->question($item, $existing, (int) $parentId),
                    'page' => $this->page($item, $existing, (int) $ids["course:{$doc['course']['id']}"], (int) $author->getAuthIdentifier()),
                };
                $remember($item, $entityId);
            }
        }

        $courseId = (int) $ids["course:{$doc['course']['id']}"];
        $this->sort($desired, $ids);

        return $courseId;
    }

    private function course(array $item, ?int $existing, int $authorId): int
    {
        if ($existing !== null) {
            return $this->courses->update($item['data'], $existing)->getKey();
        }
        $input = Validator::make($item['data'] + ['status' => CourseStatusEnum::DRAFT, 'authors' => [$authorId]], \Ulams\Courses\Models\Course::rules())->validate();

        return $this->courses->create($input + ['authors' => [$authorId]])->getKey();
    }

    private function lesson(array $item, ?int $existing, int $courseId): int
    {
        $data = $item['data'] + ['order' => $item['order'], 'course_id' => $courseId, 'active' => true];
        if ($existing !== null) {
            return $this->lessons->update(array_intersect_key($data, array_flip(['title', 'summary', 'order'])), $existing)->getKey();
        }

        return $this->lessons->create(Validator::make($data, \Ulams\Courses\Models\Lesson::$rules)->validate())->getKey();
    }

    private function topic(array $item, ?int $existing, int $lessonId, string $class): int
    {
        $data = array_filter($item['data'], fn ($v) => $v !== null) + [
            'lesson_id' => $lessonId,
            'order' => $item['order'],
            'active' => true,
            'topicable_type' => $class,
        ];
        if ($existing !== null) {
            $request = new SyntheticUpdateTopicRequest($data);
            $request->topicModel = \Ulams\Courses\Models\Topic::query()->find($existing);
            $request->setValidator(Validator::make($data, $request->rules()));

            return $this->topics->updateFromRequest($request)->getKey();
        }
        $request = new CreateTopicAPIRequest($data);
        $request->setValidator(Validator::make($data, $request->rules()));

        return $this->topics->createFromRequest($request)->getKey();
    }

    private function question(array $item, ?int $existing, int $quizTopicId): int
    {
        $quizId = (int) \Ulams\Courses\Models\Topic::query()->findOrFail($quizTopicId)->topicable_id;
        $dto = new GiftQuestionDto($quizId, $item['data']['value'], 1, $item['order'], null);
        if ($existing !== null) {
            return $this->questions->update($dto, $existing)->getKey();
        }

        return $this->questions->create($dto)->getKey();
    }

    private function page(array $item, ?int $existing, int $courseId, int $authorId): int
    {
        if ($existing !== null) {
            return $this->pages->update($existing, $item['data'])->getKey();
        }

        return $this->pages->insert("course-{$courseId}", $item['data']['title'], $item['data']['content'], $authorId, false)->getKey();
    }

    /** Orders lessons per course and topics per lesson through the courses service. */
    private function sort(array $desired, array $ids): void
    {
        $lessons = [];
        $topics = [];
        foreach ($desired as $key => $item) {
            if (!isset($ids[$key])) {
                continue;
            }
            if ($item['type'] === 'lesson') {
                $lessons[] = [$ids[$key], $item['order']];
            } elseif (in_array($item['type'], ['topic', 'quiz_topic'], true)) {
                $topics[$item['parent']][] = [$ids[$key], $item['order']];
            }
        }
        if ($lessons !== []) {
            $this->courseService->sort('Lesson', $lessons);
        }
        foreach ($topics as $orders) {
            $this->courseService->sort('Topic', $orders);
        }
    }

    public function publish(Session $session, Authenticatable $author): void
    {
        $previous = Auth::user();
        Auth::setUser($author);
        try {
            $this->courses->update(['status' => CourseStatusEnum::PUBLISHED], (int) $session->course_id);
        } finally {
            $previous !== null ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }

    /** @return array<string,string> */
    private function labels(array $doc): array
    {
        $ids = Blueprint::citations($doc);

        return $ids === [] ? [] : Fragment::query()->whereIn('id', $ids)->get()->mapWithKeys(fn (Fragment $f) => [$f->id => $f->label()])->all();
    }
}
