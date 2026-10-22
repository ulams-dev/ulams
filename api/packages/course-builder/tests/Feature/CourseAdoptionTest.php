<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\User;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypes\Models\TopicContent\OEmbed;
use Ulams\TopicTypes\Models\TopicContent\RichText;
use Ulams\TopicTypes\Models\TopicContent\Video;

/**
 * Adopting a course that was made by hand (ADR 0160, plan 7.3): the blueprint, the version, the session and the entity map come from the
 * live course, the first apply writes nothing, and the course then behaves like a built one. AI is off throughout.
 */
class CourseAdoptionTest extends TestCase
{
    private const P = '/api/admin/course-as-code';

    private const HTML = "<h1>Install</h1><p>Install <strong>kubectl</strong> first.</p><ul><li>Download</li><li>Verify</li></ul><h2>Check</h2><p>Run <code>kubectl version</code>.</p>";

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
    }

    private function topic(Lesson $lesson, string $title, int $order, object $content, array $extra = []): Topic
    {
        return Topic::factory()->create($extra + ['title' => $title, 'order' => $order, 'active' => true, 'lesson_id' => $lesson->getKey(), 'topicable_id' => $content->getKey(), 'topicable_type' => $content::class, 'duration' => '12 minutes']);
    }

    /** @return array{course:Course,topics:array<string,Topic>} a course somebody wrote in the admin */
    private function handMade(): array
    {
        $course = Course::factory()->create(['title' => 'Hand-made Kubernetes', 'language' => 'en', 'description' => 'Learn it.', 'summary' => 'Short summary', 'status' => 'published']);
        $basics = Lesson::factory()->create(['course_id' => $course->getKey(), 'title' => 'Basics', 'summary' => 'First steps', 'order' => 1, 'active' => true]);
        $more = Lesson::factory()->create(['course_id' => $course->getKey(), 'title' => 'More', 'order' => 2, 'active' => true]);
        $topics = [];
        $topics['text'] = $this->topic($basics, 'Install kubectl', 1, RichText::factory()->create(['value' => self::HTML]));
        $quiz = GiftQuiz::query()->create(['value' => 'Check yourself', 'min_pass_score' => 60]);
        foreach ([['Which flag? {=--client ~--server ~--all}', 1], ['kubectl is a CLI. {T}', 2], ['Pick the commands {~%50%get ~%50%describe ~%-100%burn}', 3], ['Match the pairs {=a -> b}', 4]] as [$gift, $order]) {
            GiftQuestion::query()->create(['topic_gift_quiz_id' => $quiz->getKey(), 'value' => $gift, 'type' => 'multiple_choice', 'score' => 1, 'order' => $order]);
        }
        $topics['quiz'] = $this->topic($basics, 'Quiz', 2, $quiz);
        $topics['embed'] = $this->topic($more, 'Talk', 1, OEmbed::factory()->create(['value' => 'https://www.youtube.com/watch?v=abc']));
        $topics['video'] = $this->topic($more, 'Screencast', 2, Video::query()->forceCreate(['value' => 'videos/x.mp4']));
        $topics['inactive'] = $this->topic($more, 'Hidden', 3, RichText::factory()->create(['value' => 'Draft']), ['active' => false]);

        return ['course' => $course, 'topics' => $topics];
    }

    private function adopt(User $as, Course $course)
    {
        return $this->actingAs($as, 'api')->postJson(self::P . "/courses/{$course->getKey()}/adopt");
    }

    /** @return array<string,string> when every entity of the course was last written */
    private function stamps(Course $course): array
    {
        $out = ['course' => (string) Course::query()->whereKey($course->getKey())->value('updated_at')];
        foreach (Lesson::query()->where('course_id', $course->getKey())->get() as $l) {
            $out["lesson {$l->getKey()}"] = (string) $l->updated_at;
            foreach (Topic::query()->where('lesson_id', $l->getKey())->get() as $t) {
                $out["topic {$t->getKey()}"] = (string) $t->updated_at;
            }
        }

        return $out;
    }

    /** @return string[] write statements on LMS content tables made while the callback runs */
    private function lmsWrites(\Closure $run): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        DB::disableQueryLog();

        return array_values(array_filter(array_column(DB::getQueryLog(), 'query'), fn ($q) => preg_match('/^(insert into|update|delete from) "(courses|lessons|topics|topic_[a-z0-9_]+|course_[a-z_]+|pages)"/i', $q) && !preg_match('/course_builder_/', $q)));
    }

    public function testAdoptionWritesABlueprintAnApprovedVersionAndAnEntityMap(): void
    {
        ['course' => $course, 'topics' => $t] = $this->handMade();
        $admin = $this->admin();

        $res = $this->adopt($admin, $course)->assertCreated();

        $res->assertJsonPath('data.created', true)->assertJsonPath('data.courseId', $course->getKey());
        $session = Session::query()->findOrFail($res->json('data.sessionId'));
        $this->assertSame([$course->getKey(), $session->current_version_id, $session->current_version_id], [$session->course_id, $session->applied_version_id, $res->json('data.versionId')]);
        $version = Version::query()->findOrFail($res->json('data.versionId'));
        $this->assertSame(['author', 'author', 'approved'], [$version->kind, $version->origin, $version->status]);
        $this->assertSame(1, Release::query()->where('session_id', $session->id)->count());

        $doc = $version->document;
        $this->assertSame(3, $doc['schemaVersion']);
        $this->assertSame(['Hand-made Kubernetes', 'en', 'Learn it.'], [$doc['course']['title'], $doc['course']['language'], $doc['course']['description']]);
        $this->assertSame(['Basics', 'More'], array_column($doc['modules'], 'title'));
        [$text, $embed] = [$doc['modules'][0]['lessons'][0], $doc['modules'][1]['lessons'][0]];
        $this->assertCount(1, $doc['modules'][0]['lessons']);
        $this->assertSame(['richtext', 12, 'Install kubectl'], [$text['contentType'], $text['minutes'], $text['title']]);
        // split at the top-level headings, HTML turned into Markdown, nothing cited, everything written by the author
        $this->assertCount(2, $text['blocks']);
        $this->assertStringStartsWith('# Install', $text['blocks'][0]['markdown']);
        $this->assertStringContainsString('**kubectl**', $text['blocks'][0]['markdown']);
        $this->assertStringContainsString('## Check', $text['blocks'][1]['markdown']);
        $this->assertStringNotContainsString('<', $text['blocks'][0]['markdown']);
        foreach ($text['blocks'] as $block) {
            $this->assertSame(['author', []], [$block['origin'], $block['citations']]);
        }
        $this->assertSame('author', $text['origin']);
        // the quiz after the lesson is its quiz; the matching question stays out
        $this->assertSame(['single', 'truefalse', 'multiple'], array_column($text['quiz']['questions'], 'type'));
        $this->assertSame(60, $text['quiz']['passScore']);
        // an embedded page stands for its topic
        $this->assertSame(['embed', 'https://www.youtube.com/watch?v=abc', ['entityType' => 'topic', 'entityId' => $t['embed']->getKey()]], [$embed['contentType'], $embed['embed']['url'], $embed['assetRef']]);
        $this->assertSame([], $embed['blocks']);

        $skipped = collect($res->json('data.skipped'));
        $this->assertSame([$t['video']->getKey(), $t['inactive']->getKey()], $skipped->pluck('topicId')->filter(fn ($id) => in_array($id, [$t['video']->getKey(), $t['inactive']->getKey()], true))->unique()->sort()->values()->all());
        $this->assertTrue($skipped->contains(fn ($s) => str_contains($s['reason'], 'matching')), 'the matching question is listed');
        $this->assertTrue($skipped->contains(fn ($s) => $s['topicId'] === $t['video']->getKey() && str_contains($s['reason'], 'asset')));
        $this->assertTrue($skipped->contains(fn ($s) => $s['topicId'] === $t['inactive']->getKey()));

        $map = EntityMapEntry::branch($session->id, null)->get()->mapWithKeys(fn ($e) => ["{$e->entity_type}:{$e->element_id}" => (int) $e->entity_id]);
        $this->assertSame($course->getKey(), $map["course:{$doc['course']['id']}"]);
        $this->assertSame((int) $t['text']->getKey(), $map["topic:{$text['id']}"]);
        $this->assertSame((int) $t['quiz']->getKey(), $map["quiz_topic:{$text['quiz']['id']}"]);
        $this->assertSame((int) $t['embed']->getKey(), $map["topic:{$embed['id']}"]);
        $this->assertCount(3, $map->filter(fn ($id, $k) => str_starts_with($k, 'gift_question:')));
        $this->assertArrayNotHasKey('topic:' . $t['video']->getKey(), $map->all());
    }

    public function testTheFirstApplyAfterAdoptionWritesNothingToTheCourse(): void
    {
        ['course' => $course] = $this->handMade();
        $admin = $this->admin();
        $res = $this->adopt($admin, $course)->assertCreated();
        $session = Session::query()->findOrFail($res->json('data.sessionId'));
        $before = $this->stamps($course);

        $plan = app(\Ulams\CourseBuilder\Apply\BlueprintApplier::class)->plan($session, $session->currentVersion->document);
        $this->assertSame(['create' => 0, 'update' => 0, 'delete' => 0], ['create' => array_sum($plan['create']), 'update' => array_sum($plan['update']), 'delete' => array_sum($plan['delete'])]);
        $this->assertSame([], app(\Ulams\CourseBuilder\Apply\BlueprintApplier::class)->drift($session, $session->currentVersion->document));

        $writes = $this->lmsWrites(fn () => $this->actingAs($admin, 'api')->postJson(self::P . "/sessions/{$session->id}/apply")->assertStatus(202));

        $this->assertSame([], $writes, "an apply right after adoption must not write the course:\n" . implode("\n", $writes));
        $this->assertSame($before, $this->stamps($course));
        $this->assertSame('Check yourself', GiftQuiz::query()->value('value'));
    }

    public function testAdoptingTwiceReturnsTheSameSession(): void
    {
        ['course' => $course] = $this->handMade();
        $admin = $this->admin();
        $first = $this->adopt($admin, $course)->assertCreated();

        $again = $this->adopt($admin, $course)->assertOk();

        $again->assertJsonPath('data.created', false)->assertJsonPath('data.sessionId', $first->json('data.sessionId'))->assertJsonPath('data.versionId', $first->json('data.versionId'));
        $this->assertSame(1, Session::query()->where('course_id', $course->getKey())->count());
        $this->assertSame(1, Version::query()->where('session_id', $first->json('data.sessionId'))->count());
    }

    public function testAnAdoptedCourseExportsAndAnEditFlowsBackWithoutTouchingWhatItDoesNotHold(): void
    {
        ['course' => $course, 'topics' => $t] = $this->handMade();
        $admin = $this->admin();
        $session = Session::query()->findOrFail($this->adopt($admin, $course)->assertCreated()->json('data.sessionId'));

        $export = $this->actingAs($admin, 'api')->getJson(self::P . "/courses/{$course->getKey()}/blueprint")->assertOk()->json('data');
        $this->assertSame($session->current_version_id, $export['versionId']);
        $doc = $export['document'];
        $doc['modules'][0]['lessons'][0]['blocks'][1]['markdown'] = "## Check\n\nRun `kubectl version --client` and read the output.";
        $doc['modules'][1]['lessons'][0]['title'] = 'A talk about kubectl';

        $pushed = $this->actingAs($admin, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $doc, 'baseVersionId' => $export['versionId']])->assertCreated();
        $this->assertEquals(['added' => 0, 'changed' => 2, 'removed' => 0], $pushed->json('data.counts'), json_encode(array_map(fn ($c) => [$c['label'], $c['fields']], $pushed->json('data.changes'))));
        $this->actingAs($admin, 'api')->postJson(self::P . '/versions/' . $pushed->json('data.version.id') . '/approve')->assertOk();
        $this->actingAs($admin, 'api')->postJson(self::P . "/sessions/{$session->id}/apply")->assertStatus(202);

        $text = (string) RichText::query()->whereKey($t['text']->topicable_id)->value('value');
        $this->assertStringContainsString('kubectl version --client', $text);
        $this->assertStringContainsString('Install', $text);
        // the embedded page keeps its address, takes the new title; the screencast and the draft were never touched
        $this->assertSame('A talk about kubectl', Topic::query()->findOrFail($t['embed']->getKey())->title);
        $this->assertSame('https://www.youtube.com/watch?v=abc', OEmbed::query()->whereKey($t['embed']->topicable_id)->value('value'));
        $this->assertSame(['Screencast', 'Hidden'], [Topic::query()->findOrFail($t['video']->getKey())->title, Topic::query()->findOrFail($t['inactive']->getKey())->title]);
        $this->assertFalse((bool) Topic::query()->findOrFail($t['inactive']->getKey())->active);
        $this->assertSame(2, Release::query()->where('session_id', $session->id)->count());
    }

    public function testAnAdoptedCourseCanBeComparedLikeABuiltOne(): void
    {
        ['course' => $course] = $this->handMade();
        $admin = $this->admin();
        $this->adopt($admin, $course)->assertCreated();

        $this->actingAs($admin, 'api')->getJson("/api/admin/course-versions/courses/{$course->getKey()}/releases")->assertOk()->assertJsonPath('data.total', 1);
    }

    public function testOnlyThoseWhoMayChangeTheCourseCanAdoptIt(): void
    {
        ['course' => $course] = $this->handMade();
        $this->actingAs($this->student(), 'api')->postJson(self::P . "/courses/{$course->getKey()}/adopt")->assertStatus(403);
        $this->actingAs($this->tutor(), 'api')->postJson(self::P . "/courses/{$course->getKey()}/adopt")->assertStatus(403);
        $this->actingAs($this->admin(), 'api')->postJson(self::P . '/courses/999999/adopt')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->postJson(self::P . "/courses/{$course->getKey()}/adopt")->assertStatus(401);
        $this->assertSame(0, Session::query()->where('course_id', $course->getKey())->count());
        $this->assertSame(0, EntityMapEntry::query()->count());
    }

    public function testACourseWithNothingTheBlueprintCanHoldIsRefusedAndLeftAlone(): void
    {
        $course = Course::factory()->create(['title' => 'Only video', 'language' => 'en']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey(), 'title' => 'Videos', 'order' => 1, 'active' => true]);
        $this->topic($lesson, 'Screencast', 1, Video::query()->forceCreate(['value' => 'videos/x.mp4']));

        $res = $this->adopt($this->admin(), $course)->assertStatus(422);

        $res->assertJsonPath('code', 'NOTHING_TO_ADOPT');
        $this->assertSame(0, Session::query()->where('course_id', $course->getKey())->count());
        $this->assertSame(1, Topic::query()->where('lesson_id', $lesson->getKey())->count());
    }

    public function testHtmlScriptsAndRemoteImagesNeverReachTheBlueprintAndLongTextIsSplit(): void
    {
        $course = Course::factory()->create(['title' => 'Messy HTML', 'language' => 'en']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey(), 'title' => 'Messy', 'order' => 1, 'active' => true]);
        $html = '<p onclick="x()">Hello <a href="javascript:alert(1)">link</a></p><script>alert(1)</script><img src="https://evil.example/p.png" alt="pic"><p>' . str_repeat('word ', 5000) . '</p>';
        $this->topic($lesson, 'Messy lesson', 1, RichText::factory()->create(['value' => $html]));

        $res = $this->adopt($this->admin(), $course)->assertCreated();

        $doc = Version::query()->findOrFail($res->json('data.versionId'))->document;
        $blocks = $doc['modules'][0]['lessons'][0]['blocks'];
        $this->assertNotEmpty($blocks);
        foreach ($blocks as $block) {
            $this->assertLessThanOrEqual(20000, mb_strlen($block['markdown']));
            $this->assertStringNotContainsString('<script', $block['markdown']);
            $this->assertStringNotContainsString('evil.example', $block['markdown']);
            $this->assertStringNotContainsString('javascript:', $block['markdown']);
            $this->assertSame([], \Ulams\CourseBuilder\Blueprint\Checks::markup($block['markdown'], 'x'));
        }
        $this->assertStringContainsString('Hello', $blocks[0]['markdown']);
    }

    public function testTheBlueprintOfAnAdoptedCourseIsAValidV3Document(): void
    {
        ['course' => $course] = $this->handMade();
        $res = $this->adopt($this->admin(), $course)->assertCreated();
        $doc = Version::query()->findOrFail($res->json('data.versionId'))->document;

        $this->assertSame([], app(\Ulams\CourseBuilder\Blueprint\SchemaRegistry::class)->validateBlueprint($doc));
        $this->assertSame([], array_values(array_filter(\Ulams\CourseBuilder\Blueprint\Checks::blueprint($doc, []), fn ($e) => !str_starts_with($e, 'warning: '))));
    }
}
