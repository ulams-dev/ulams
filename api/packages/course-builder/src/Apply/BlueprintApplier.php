<?php

namespace Ulams\CourseBuilder\Apply;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry;
use Ulams\CourseBuilder\Contracts\FragmentArchive;
use Ulams\CourseBuilder\Contracts\RemovalPolicy;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Exceptions\BuilderException;
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
 * Mapping: course → course; module → lesson; blueprint lesson → the topics of its content type
 * (RichText; a LiaScript topic; RichText plus an H5P or Interactive topic: ADR 0050) and a GIFT quiz
 * topic when it has a quiz; final test → a "Final test" lesson with a GIFT quiz topic; landing → page.
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
        private readonly RemovalPolicy $removal,
        private readonly FragmentArchive $archive,
        private readonly SiteTheme $theme,
        private readonly CourseCommerce $commerce,
        private readonly ContentTypeRegistry $types,
        private readonly TopicWriter $writer,
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
            $base = 0;
            foreach ($module['lessons'] as $l => $lesson) {
                $topics = $this->types->forLesson($lesson)->topics($lesson, $labels, $sourceTitle, (string) ($course['language'] ?? 'en'));
                $extra = count($topics) - 1;
                foreach ($topics as $topic) {
                    $primary = $topic['slot'] === 'primary';
                    $data = $topic['class'] === RichText::class ? $topic['data'] : ['class' => $topic['class']] + $topic['data'];
                    $add($primary ? 'topic' : 'interaction_topic', $topic['element'], $data, "lesson:{$module['id']}", $base + ($primary ? 1 : 2));
                }
                if (is_array($lesson['quiz'] ?? null) && $lesson['quiz']['questions'] !== []) {
                    $this->addQuiz($add, $lesson['quiz'], 'Quiz: ' . $lesson['title'], "Check what you learned in “{$lesson['title']}”.", "lesson:{$module['id']}", $base + 2 + $extra, null);
                }
                $base += 2 + $extra;
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
            'topic', 'interaction_topic', 'quiz_topic' => 'topics',
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
            if (!isset($desired[$key]) && $entry->retired_at === null) {
                $delete[$bucket($entry->entity_type)]++;
            }
        }
        $known = array_fill_keys([...$session->fragmentIds(), ...$this->archive->knownIds($session->id)], true);
        $warnings = array_map(fn ($e) => str_starts_with($e, 'warning: ') ? substr($e, 9) : $e, Checks::blueprint($doc, $known));
        if (isset($session->brief['theme']) && !$this->theme->authorMayChange($session->author, (array) $session->brief)) {
            $warnings[] = 'The theme in your brief will not be applied: only site admins can change the site theme.';
        }
        foreach (Blueprint::lessons($doc) as $item) {
            $wanted = (string) ($item['lesson']['contentType'] ?? 'richtext');
            if ($wanted !== 'richtext' && !$this->types->for($wanted)->enabled()) {
                $warnings[] = 'Lesson ' . $item['number'] . ': ' . $this->types->for($wanted)->label() . ' is not available on this installation right now.';
            }
        }
        foreach ($this->drift($session, $doc) as $title) {
            $warnings[] = "Edited in the admin after the last apply: {$title}. The apply stops until you confirm overwriting it.";
        }

        return [
            'firstApply' => $session->course_id === null,
            'create' => $create,
            'update' => $update,
            'delete' => $delete,
            'warnings' => array_slice(array_map(fn ($w) => mb_substr($w, 0, 500), $warnings), 0, 40),
        ] + ($session->course_id ? ['courseId' => (int) $session->course_id] : []);
    }

    /**
     * Elements the apply would change although someone edited the LMS entity (in the admin) after
     * the last apply: an apply over them would destroy that work (ADR 0010 drift check).
     *
     * @return array<string,string> "type:element id" => title or type
     */
    public function drift(Session $session, array $doc): array
    {
        $desired = $this->desired($doc, (array) $session->brief);
        $drift = [];
        foreach ($this->map($session) as $key => $entry) {
            if (!isset($desired[$key]) || $entry->fingerprint === $desired[$key]['fingerprint']) {
                continue;
            }
            $model = match ($entry->entity_type) {
                'course' => \Ulams\Courses\Models\Course::class,
                'lesson' => \Ulams\Courses\Models\Lesson::class,
                'topic', 'interaction_topic', 'quiz_topic' => \Ulams\Courses\Models\Topic::class,
                'gift_question' => \Ulams\TopicTypeGift\Models\GiftQuestion::class,
                'page' => \Ulams\Pages\Models\Page::class,
                default => null,
            };
            $updated = $model !== null ? $model::query()->whereKey($entry->entity_id)->value('updated_at') : null;
            if ($updated !== null && $entry->entity_type === 'gift_question') {
                // admins change scores and categories all the time: only an edit of the question text is drift
                $applied = $entry->applied_version_id ? \Ulams\CourseBuilder\Models\Version::query()->find($entry->applied_version_id) : null;
                $was = $applied !== null ? ($this->desired($applied->document, (array) $session->brief)[$key]['data']['value'] ?? null) : null;
                $now = \Ulams\TopicTypeGift\Models\GiftQuestion::query()->whereKey($entry->entity_id)->value('value');
                if ($was === null || $now === $was) {
                    continue;
                }
            }
            if ($updated !== null && $entry->updated_at !== null && Carbon::parse($updated)->gt($entry->updated_at)) {
                $drift[$key] = (string) ($desired[$key]['data']['title'] ?? $entry->entity_type);
            }
        }

        return $drift;
    }

    /**
     * Applies a version as the author. Returns the course id. Stops (409) when an element to change
     * was edited in the admin after the last apply, unless the author confirmed with `$overwrite`.
     */
    public function apply(Session $session, Version $version, Authenticatable $author, bool $overwrite = false, ?string $proposalId = null): int
    {
        if (!$overwrite && ($drift = $this->drift($session, $version->document)) !== []) {
            throw new BuilderException(sprintf(
                'The course was edited in the admin after the last apply (%s). Applying now would overwrite those edits; confirm to overwrite, or copy the edits into the blueprint first.',
                implode(', ', array_slice(array_values($drift), 0, 5)),
            ), 409);
        }
        $previous = Auth::user();
        Auth::setUser($author);
        try {
            return DB::transaction(fn () => $this->applyAs($session, $version, $author, $proposalId));
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            } else {
                Auth::forgetUser();
            }
        }
    }

    private function applyAs(Session $session, Version $version, Authenticatable $author, ?string $proposalId = null): int
    {
        $doc = $version->document;
        $desired = $this->desired($doc, (array) $session->brief);
        $map = $this->map($session);
        $ids = [];
        foreach ($map as $key => $entry) {
            $ids[$key] = $entry->entity_id;
        }
        $remember = function (array $item, int $entityId) use ($session, $version, &$ids, &$map, $proposalId) {
            $attributes = ['entity_id' => $entityId, 'fingerprint' => $item['fingerprint'], 'applied_version_id' => $version->id, 'retired_at' => null];
            if ($proposalId !== null && !isset($map["{$item['type']}:{$item['element']}"])) {
                // entities created by a source update: the completion guard tells them from the original course
                $attributes['added_by_proposal_id'] = $proposalId;
            }
            EntityMapEntry::query()->updateOrCreate(
                ['session_id' => $session->id, 'element_id' => $item['element'], 'entity_type' => $item['type']],
                $attributes,
            );
            $ids["{$item['type']}:{$item['element']}"] = $entityId;
        };
        $changed = fn (string $key, array $item) => !isset($map[$key]) || $map[$key]->fingerprint !== $item['fingerprint'];

        // deletions first (questions, topics, lessons), so removed elements never linger; the removal
        // policy may keep an entity that learners have data on (deactivated or archived instead)
        foreach (['gift_question', 'quiz_topic', 'interaction_topic', 'topic', 'lesson', 'page'] as $type) {
            foreach ($map as $key => $entry) {
                if ($entry->entity_type !== $type || isset($desired[$key]) || $entry->retired_at !== null) {
                    continue;
                }
                if ($type !== 'page' && !$this->removal->shouldDelete($type === 'interaction_topic' ? 'topic' : $type, $entry->entity_id)) {
                    match ($type) {
                        'gift_question' => $this->questions->archive($entry->entity_id),
                        'quiz_topic', 'interaction_topic', 'topic' => $this->topics->update(['active' => false], $entry->entity_id),
                        'lesson' => $this->lessons->update(['active' => false], $entry->entity_id),
                    };
                    if ($type === 'gift_question') {
                        $entry->delete();
                        unset($ids[$key]);
                    } else {
                        // kept: a later re-add of the same element reactivates it
                        $entry->forceFill(['retired_at' => now(), 'fingerprint' => null])->save();
                    }
                    continue;
                }
                $external = in_array($type, ['topic', 'interaction_topic'], true) ? $this->writer->external($entry->entity_id) : [];
                match ($type) {
                    'gift_question' => $this->questions->delete($entry->entity_id),
                    'quiz_topic', 'interaction_topic', 'topic' => $this->topics->delete($entry->entity_id),
                    'lesson' => $this->lessons->delete($entry->entity_id),
                    'page' => $this->pages->deleteById($entry->entity_id),
                };
                $this->writer->purge($external);
                $entry->delete();
                unset($ids[$key]);
            }
        }

        foreach (['course', 'lesson', 'topic', 'interaction_topic', 'quiz_topic', 'gift_question', 'page'] as $type) {
            foreach ($desired as $key => $item) {
                if ($item['type'] !== $type || !$changed($key, $item)) {
                    continue;
                }
                $parentId = $item['parent'] !== null ? ($ids[$item['parent']] ?? null) : null;
                $existing = $ids[$key] ?? null;
                $entityId = match ($type) {
                    'course' => $this->course($item, $existing, (int) $author->getAuthIdentifier()),
                    'lesson' => $this->lesson($item, $existing, (int) $parentId),
                    'topic', 'interaction_topic' => $this->topic($item, $existing, (int) $parentId, (string) ($item['data']['class'] ?? RichText::class), (int) $author->getAuthIdentifier()),
                    'quiz_topic' => $this->topic($item, $existing, (int) $parentId, GiftQuiz::class, (int) $author->getAuthIdentifier()),
                    'gift_question' => $this->question($item, $existing, (int) $parentId),
                    'page' => $this->page($item, $existing, (int) $ids["course:{$doc['course']['id']}"], (int) $author->getAuthIdentifier()),
                };
                $remember($item, $entityId);
            }
        }

        $courseId = (int) $ids["course:{$doc['course']['id']}"];
        $this->sort($desired, $ids);
        $themeResult = $this->theme->apply($session, $author);
        $commerceResult = $this->commerce->apply($session, $courseId);
        $session->putState('applyNotes', array_values(array_filter([$themeResult['note'], $commerceResult['note']])));
        $session->save();
        // everything above was written by this apply: later edits in the admin are newer than this mark
        EntityMapEntry::query()->where('session_id', $session->id)->update(['updated_at' => now()]);

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
            return $this->lessons->update(array_intersect_key($data, array_flip(['title', 'summary', 'order', 'active'])), $existing)->getKey();
        }

        return $this->lessons->create(Validator::make($data, \Ulams\Courses\Models\Lesson::$rules)->validate())->getKey();
    }

    private function topic(array $item, ?int $existing, int $lessonId, string $class, int $authorId): int
    {
        if ($existing !== null && ($current = $this->writer->classOf($existing)) !== null && $current !== $class) {
            // the lesson changed format (rich text ⇄ LiaScript): a topic cannot change its content class
            $external = $this->writer->external($existing);
            $this->topics->delete($existing);
            $this->writer->purge($external);
            $existing = null;
        }
        $fields = $this->writer->prepare($class, $item['data'], $existing, $authorId);
        $data = array_filter($fields, fn ($v) => $v !== null) + [
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
        if ($existing !== null) {
            // an update keeps the score (and category) the author or an admin gave the question
            $current = \Ulams\TopicTypeGift\Models\GiftQuestion::query()->find($existing);

            return $this->questions->update(new GiftQuestionDto($quizId, $item['data']['value'], $current?->score ?? 1, $item['order'], $current?->category_id), $existing)->getKey();
        }
        $dto = new GiftQuestionDto($quizId, $item['data']['value'], 1, $item['order'], null);

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
            } elseif (in_array($item['type'], ['topic', 'interaction_topic', 'quiz_topic'], true)) {
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
            $this->commerce->activate($session);
            $landing = EntityMapEntry::query()->where(['session_id' => $session->id, 'entity_type' => 'page'])->first();
            if ($landing !== null) {
                $this->pages->update((int) $landing->entity_id, ['active' => true]);
            }
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
