<?php

namespace Ulams\CourseBuilder\CourseAsCode;

use Illuminate\Support\Facades\DB;
use League\HTMLToMarkdown\HtmlConverter;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Apply\LessonMarkdown;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\ReleaseRecorder;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\TopicTypes\Models\TopicContent\OEmbed;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/**
 * Adopts a course that was made by hand (ADR 0160, plan 7.3): reads it through the models and writes a first Blueprint v3 document, an
 * approved `author` version, a builder session and an entity map, so the course can be exported, pushed and pulled like a built one.
 *
 * - Rich text topics become lessons whose Markdown is split at top-level headings (every block `origin: author`, no citations).
 * - Quiz topics become the quiz of the lesson before them; only questions that GIFT can say in the blueprint's four shapes are taken.
 * - H5P, LiaScript and oEmbed topics become lessons that **stand for** the existing topic (`assetRef`): the apply follows their title and
 *   position and never rewrites their content.
 * - Everything else (video, audio, PDF, image, SCORM, cmi5, anything unknown, inactive topics, a quiz with no lesson before it,
 *   questions in shapes the blueprint cannot hold) is left out and listed. It is not mapped, so an apply never touches it.
 *
 * The entity map holds the fingerprint the applier would compute for each element, so the first apply after adoption writes nothing.
 */
final class CourseAdopter
{
    private const MAX_BLOCK = 20000;

    public function __construct(
        private readonly BlueprintApplier $applier,
        private readonly VersionService $versions,
        private readonly ReleaseRecorder $releases,
        private readonly SchemaRegistry $schemas,
    ) {
    }

    /**
     * @return array{session:Session,version:Version,skipped:array<int,array{topicId:int,title:string,type:string,reason:string}>,created:bool}
     */
    public function adopt(Course $course, int $userId): array
    {
        $existing = Session::query()->where('course_id', $course->getKey())->latest('created_at')->first();
        if ($existing !== null && $existing->currentVersion !== null) {
            return ['session' => $existing, 'version' => $existing->currentVersion, 'skipped' => [], 'created' => false];
        }

        $skipped = [];
        $entities = [];
        $doc = $this->document($course, $skipped, $entities);
        if (array_sum(array_map(fn ($m) => count($m['lessons']), $doc['modules'])) === 0) {
            throw new CourseAsCodeException('This course has no lesson the blueprint can hold (rich text, quiz, H5P, LiaScript or embedded pages), so there is nothing to adopt.', 422, 'NOTHING_TO_ADOPT', ['skipped' => $skipped]);
        }
        $errors = $this->schemas->validateBlueprint($doc);
        if ($errors === []) {
            $errors = array_values(array_filter(Checks::blueprint($doc, []), fn ($e) => !str_starts_with($e, 'warning: ')));
        }
        if ($errors !== []) {
            throw new CourseAsCodeException('The course cannot be written as a blueprint yet.', 422, 'ADOPTION_INVALID', ['errors' => array_slice($errors, 0, 30)]);
        }
        $desired = $this->applier->desired($doc, []);
        $missing = array_diff(array_keys($desired), array_keys($entities));
        if ($missing !== []) {
            throw new CourseAsCodeException('Adoption would leave elements without an LMS entity (' . implode(', ', array_slice($missing, 0, 5)) . ').', 500, 'ADOPTION_INCOMPLETE');
        }

        return DB::transaction(function () use ($course, $userId, $doc, $entities, $desired, $skipped) {
            $session = Session::query()->create(['author_id' => $userId, 'title' => mb_substr((string) $course->title, 0, 255), 'status' => Session::APPLIED, 'course_id' => $course->getKey()]);
            $version = $this->versions->create($session, $doc, 'author', 'author', Version::APPROVED, null, 'Adopted from the course in the LMS', null, [], $userId);
            $this->versions->setCurrent($session, $version);
            foreach ($entities as $key => $entityId) {
                [$type, $element] = explode(':', $key, 2);
                EntityMapEntry::query()->create(['session_id' => $session->id, 'variant_id' => '', 'element_id' => $element, 'entity_type' => $type, 'entity_id' => $entityId, 'fingerprint' => $desired[$key]['fingerprint'], 'applied_version_id' => $version->id]);
            }
            $this->releases->applied($session, $version, (int) $course->getKey(), ['status' => Session::APPLIED]);
            // the entities were last edited before this moment: later edits in the admin are drift
            EntityMapEntry::branch($session->id, null)->update(['updated_at' => now()]);

            return ['session' => $session->refresh(), 'version' => $version, 'skipped' => $skipped, 'created' => true];
        });
    }

    /**
     * @param array<int,array{topicId:int,title:string,type:string,reason:string}> $skipped
     * @param array<string,int> $entities "type:element id" => LMS entity id
     * @return array<string,mixed>
     */
    private function document(Course $course, array &$skipped, array &$entities): array
    {
        $language = strtolower(substr((string) ($course->language ?: 'en'), 0, 2));
        $title = $this->text((string) $course->title, 3, 200, 'Course ' . $course->getKey());
        $doc = Blueprint::empty($title, preg_match('/^[a-z]{2}$/', $language) ? $language : 'en');
        $doc['course'] += array_filter([
            'subtitle' => $this->clip((string) ($course->subtitle ?: $course->summary), 300),
            'description' => $this->clip((string) $course->description, 4000),
            'audience' => $this->clip((string) $course->target_group, 300),
        ]);
        $entities["course:{$doc['course']['id']}"] = (int) $course->getKey();

        $lessons = Lesson::query()->where('course_id', $course->getKey())->orderBy('order')->orderBy('id')->get();
        $groups = [];
        foreach ($lessons->whereNull('parent_lesson_id') as $parent) {
            $groups[] = [$parent, (string) $parent->title];
            foreach ($lessons->where('parent_lesson_id', $parent->getKey()) as $child) {
                $groups[] = [$child, $parent->title . ' › ' . $child->title];
            }
        }
        // sub-lessons whose parent is gone are still lessons of the course
        foreach ($lessons->whereNotNull('parent_lesson_id') as $orphan) {
            if (!$lessons->contains('id', $orphan->parent_lesson_id)) {
                $groups[] = [$orphan, (string) $orphan->title];
            }
        }

        foreach ($groups as [$lms, $name]) {
            $adopted = [];
            $lastLesson = null;
            foreach (Topic::query()->where('lesson_id', $lms->getKey())->orderBy('order')->orderBy('id')->get() as $topic) {
                $skip = function (string $reason) use (&$skipped, $topic): void {
                    $skipped[] = ['topicId' => (int) $topic->getKey(), 'title' => (string) $topic->title, 'type' => class_basename((string) $topic->topicable_type), 'reason' => $reason];
                };
                if (!$topic->active) {
                    $skip('The topic is inactive.');
                    continue;
                }
                $content = $topic->topicable;
                if ($content instanceof GiftQuiz) {
                    if ($lastLesson === null || $adopted[$lastLesson]['quiz'] !== null) {
                        $skip('A quiz is adopted as the quiz of the lesson before it, and there is none that has no quiz yet.');
                        continue;
                    }
                    $quiz = $this->quiz($content, $topic, $entities, $skip);
                    if ($quiz !== null) {
                        $adopted[$lastLesson]['quiz'] = $quiz;
                    }
                    continue;
                }
                if (count($adopted) >= 30 && !$content instanceof GiftQuiz) {
                    $skip('A module of the blueprint holds at most 30 lessons; this topic stays as it is and is never changed by an apply.');
                    continue;
                }
                $lesson = match (true) {
                    $content instanceof RichText => $this->richText($topic, (string) $content->value),
                    $content instanceof H5P => $this->referenced($topic, 'h5p'),
                    $content instanceof OEmbed => $this->referenced($topic, 'embed', ['embed' => ['url' => (string) $content->value, 'title' => $this->text((string) $topic->title, 1, 200, 'Embedded page')]]),
                    $topic->topicable_type === \Ulams\LiaScript\Models\LiaScriptTopic::class => $this->referenced($topic, 'liascript'),
                    default => null,
                };
                if ($lesson === null) {
                    $skip('This kind of topic needs its file to travel with the course (asset download), which is not built yet; the topic stays as it is and is never changed by an apply.');
                    continue;
                }
                $adopted[] = $lesson;
                $lastLesson = array_key_last($adopted);
                $entities["topic:{$lesson['id']}"] = (int) $topic->getKey();
            }
            if ($adopted === []) {
                continue;
            }
            $moduleId = Blueprint::newId();
            $doc['modules'][] = array_filter([
                'id' => $moduleId,
                'title' => $this->text($name, 2, 200, 'Module'),
                'summary' => $this->clip((string) $lms->summary, 600),
                'lessons' => $adopted,
            ], fn ($v) => $v !== null && $v !== '');
            $entities["lesson:{$moduleId}"] = (int) $lms->getKey();
        }
        // every quiz element stays attached to a lesson that was adopted; questions are registered in `quiz()`
        $doc['modules'] = array_values($doc['modules']);

        return $doc;
    }

    /** @return array<string,mixed> */
    private function lesson(Topic $topic, string $contentType): array
    {
        $title = $this->text((string) $topic->title, 2, 200, 'Lesson');
        $objective = Blueprint::newId();

        return array_filter([
            'id' => Blueprint::newId(),
            'title' => $title,
            'summary' => $this->clip((string) ($topic->summary ?? ''), 600),
            'minutes' => $this->minutes((string) ($topic->duration ?? '')),
            'objectives' => [['id' => $objective, 'text' => $this->text("Work through “{$title}”", 3, 400, 'Work through the lesson'), 'citations' => []]],
            'citations' => [],
            'contentType' => $contentType,
            'status' => 'generated',
            'blocks' => [],
            'quiz' => null,
            'flags' => [],
            'origin' => 'author',
        ], fn ($v, $k) => $v !== null && $v !== '' || in_array($k, ['quiz'], true), ARRAY_FILTER_USE_BOTH);
    }

    /** @return array<string,mixed> */
    private function richText(Topic $topic, string $value): array
    {
        $lesson = $this->lesson($topic, 'richtext');
        $objective = $lesson['objectives'][0]['id'];
        $markdown = $this->markdown($value);
        $lesson['blocks'] = array_map(fn (string $chunk) => [
            'id' => Blueprint::newId(), 'kind' => 'paragraph', 'markdown' => $chunk, 'citations' => [], 'objectiveIds' => [$objective], 'origin' => 'author',
        ], $this->chunks($markdown));
        if ($lesson['blocks'] === []) {
            $lesson['blocks'][] = ['id' => Blueprint::newId(), 'kind' => 'paragraph', 'markdown' => '*This lesson has no text yet.*', 'citations' => [], 'objectiveIds' => [$objective], 'origin' => 'author'];
        }

        return $lesson;
    }

    /** @return array<string,mixed> */
    private function referenced(Topic $topic, string $contentType, array $extra = []): array
    {
        return $this->lesson($topic, $contentType) + $extra + ['assetRef' => ['entityType' => 'topic', 'entityId' => (int) $topic->getKey()]];
    }

    /**
     * @param array<string,int> $entities
     * @return array<string,mixed>|null
     */
    private function quiz(GiftQuiz $content, Topic $topic, array &$entities, \Closure $skip): ?array
    {
        $questions = [];
        $mapped = [];
        foreach ($content->questions()->orderBy('order')->orderBy('id')->get() as $row) {
            $question = $this->question((string) $row->value);
            if ($question === null) {
                $skip('A question in a shape the blueprint cannot hold (matching, numeric, fill in the blanks or GIFT it cannot read) stays as it is: ' . mb_substr(trim(strip_tags((string) $row->value)), 0, 60));
                continue;
            }
            $questions[] = $question;
            $mapped["gift_question:{$question['id']}"] = (int) $row->getKey();
        }
        if ($questions === []) {
            $skip('None of the questions of this quiz can be held by the blueprint.');

            return null;
        }
        $quizId = Blueprint::newId();
        $entities["quiz_topic:{$quizId}"] = (int) $topic->getKey();
        $entities += $mapped;

        return array_filter(['id' => $quizId, 'passScore' => $content->min_pass_score !== null ? max(0, min(100, (int) $content->min_pass_score)) : null, 'questions' => $questions], fn ($v) => $v !== null);
    }

    /** @return array<string,mixed>|null */
    private function question(string $gift): ?array
    {
        $read = GiftReader::read($gift);
        if ($read === null || mb_strlen($read['stem']) < 3 || mb_strlen($read['stem']) > 1000) {
            return null;
        }
        $option = fn (string $text, bool $correct) => ['id' => Blueprint::newId(), 'text' => mb_substr($text, 0, 500), 'correct' => $correct];
        if ($read['truth'] !== null) {
            $type = 'truefalse';
            $options = [$option('True', $read['truth']), $option('False', !$read['truth'])];
            $explanation = '';
        } else {
            $answers = array_values(array_filter($read['answers'], fn ($a) => $a['text'] !== ''));
            // `=left -> right` is a matching question: the blueprint has no such shape
            if (array_filter($answers, fn ($a) => preg_match('/\s->\s/', $a['text'])) !== []) {
                return null;
            }
            $right = array_values(array_filter($answers, fn ($a) => $a['correct']));
            if ($answers === [] || $right === [] || count($answers) > 8) {
                return null;
            }
            $type = count($right) === count($answers) ? 'short' : (count($right) === 1 ? 'single' : 'multiple');
            $options = array_map(fn ($a) => $option($a['text'], $a['correct']), $answers);
            $explanation = (string) ($right[0]['feedback'] ?? '');
        }

        return [
            'id' => Blueprint::newId(), 'type' => $type, 'stem' => $read['stem'], 'options' => $options,
            'explanation' => mb_strlen($explanation) >= 3 ? mb_substr($explanation, 0, 2000) : 'No explanation was written for this question.',
            'citations' => [], 'objectiveIds' => [], 'origin' => 'author',
        ];
    }

    /** HTML from the visual editor becomes Markdown; text that is Markdown already is kept. Raw HTML never stays in a block. */
    private function markdown(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if (preg_match('/<\/?[a-z][a-z0-9]*[\s>\/]/i', $value)) {
            $converter = new HtmlConverter(['strip_tags' => true, 'header_style' => 'atx', 'remove_nodes' => 'script style', 'hard_break' => false]);
            $value = trim($converter->convert($value));
        }

        return Checks::markup($value, 'x') === [] ? $value : trim(LessonMarkdown::sanitise($value));
    }

    /**
     * Splits at top-level headings (outside code fences); a chunk over the block size is split at blank lines. At most 60 blocks.
     *
     * @return string[]
     */
    private function chunks(string $markdown): array
    {
        $chunks = [];
        $current = [];
        $fence = null;
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s{0,3}(```+|~~~+)/', $line, $m)) {
                $fence = $fence === null ? $m[1][0] : ($fence === $m[1][0] ? null : $fence);
            }
            if ($fence === null && preg_match('/^#{1,2}\s+\S/', $line) && trim(implode("\n", $current)) !== '') {
                $chunks[] = trim(implode("\n", $current));
                $current = [];
            }
            $current[] = $line;
        }
        if (trim(implode("\n", $current)) !== '') {
            $chunks[] = trim(implode("\n", $current));
        }
        $out = [];
        foreach ($chunks as $chunk) {
            while (mb_strlen($chunk) > self::MAX_BLOCK) {
                $cut = mb_strrpos(mb_substr($chunk, 0, self::MAX_BLOCK), "\n\n") ?: self::MAX_BLOCK;
                $out[] = trim(mb_substr($chunk, 0, $cut));
                $chunk = trim(mb_substr($chunk, $cut));
            }
            if ($chunk !== '') {
                $out[] = $chunk;
            }
        }
        if (count($out) > 60) {
            $tail = implode("\n\n", array_slice($out, 59));
            $out = [...array_slice($out, 0, 59), mb_substr($tail, 0, self::MAX_BLOCK)];
        }

        return $out;
    }

    private function minutes(string $duration): int
    {
        return preg_match('/(\d{1,3})/', $duration, $m) ? max(1, min(600, (int) $m[1])) : 5;
    }

    private function text(string $text, int $min, int $max, string $fallback): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));

        $text = trim(mb_substr($text, 0, $max));

        return mb_strlen($text) >= $min ? $text : $fallback;
    }

    private function clip(string $text, int $max): ?string
    {
        $text = trim(strip_tags($text));

        $text = trim(mb_substr($text, 0, $max));

        return $text === '' ? null : $text;
    }
}
