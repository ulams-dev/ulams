<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/** Upload → interview → outline → generation → apply, on the fake driver's synthetic answers. */
class PipelineTest extends TestCase
{
    public function testUploadIngestsAndStartsTheInterview(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);

        $this->assertSame(Session::INTERVIEWING, $session->status);
        $this->assertCount(6, $session->stateValue('interview.questions'));
        $source = $session->sources()->first();
        $this->assertSame('ready', $source->status);
        $this->assertGreaterThan(5, $source->fragments()->count());
        $types = Event::query()->where('session_id', $session->id)->pluck('type')->all();
        $this->assertContains('RUN_STARTED', $types);
        $this->assertContains('ACTIVITY_SNAPSHOT', $types);
        $interview = Event::query()->where('session_id', $session->id)->where('type', 'ACTIVITY_SNAPSHOT')->get()
            ->first(fn (Event $e) => $e->payload['messageId'] === 'interview');
        $this->assertNotNull($interview);
        $components = $interview->payload['content']['messages'][1]['updateComponents']['components'];
        $this->assertSame('root', $components[0]['id']);
        $byId = collect($components)->keyBy('id');
        $this->assertSame('SingleChoice', $byId['q-level']['component']);
        $this->assertSame('DurationSlider', $byId['q-duration']['component']);
        $this->assertSame('LanguagePicker', $byId['q-language']['component']);
        $this->assertSame('open', $byId['q-audience']['status']);
    }

    public function testInterviewAnswersFillTheBriefAndStartTheOutline(): void
    {
        $author = $this->author();
        $session = $this->toOutline($author);

        $this->assertSame(30, $session->brief['totalMinutes']);
        $this->assertSame('author', $session->brief['decidedBy']['totalMinutes']);
        $this->assertSame('default', $session->brief['decidedBy']['tone']);
        $this->assertSame(Session::OUTLINE_REVIEW, $session->status, (string) json_encode(\Ulams\CourseBuilder\Models\Run::query()->where('session_id', $session->id)->get(['kind', 'status', 'error'])->toArray()) . json_encode(AiCall::query()->latest('created_at')->first()?->error));
        $outline = $session->currentVersion;
        $this->assertSame('outline', $outline->kind);
        $this->assertSame(Version::PROPOSED, $outline->status);
        $this->assertSame([], app(SchemaRegistry::class)->validate('course-blueprint/v1', $outline->document));
        foreach (Blueprint::lessons($outline->document) as $item) {
            $this->assertNotEmpty($item['lesson']['objectives']);
            $this->assertNotEmpty($item['lesson']['citations']);
        }
    }

    public function testApprovedOutlineGeneratesCitedLessonsAndQuizzes(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author, 'coffee-brewing.md', ['quiz', 'final']);

        $this->assertSame(Session::APPLY_REVIEW, $session->status);
        $content = $session->currentVersion;
        $this->assertSame('content', $content->kind);
        $doc = $content->document;
        $this->assertSame([], app(SchemaRegistry::class)->validate('course-blueprint/v1', $doc));
        $known = array_fill_keys($session->fragmentIds(), true);
        $this->assertSame([], array_values(array_filter(Checks::blueprint($doc, $known), fn ($e) => !str_starts_with($e, 'warning'))));
        $this->assertNotNull($doc['finalTest']);
        $this->assertNotNull($doc['pages']['landing']);
        $this->assertSame('Page', $doc['pages']['landing']['component']);
        $this->assertTrue(Step::query()->where('status', 'done')->where('key', 'like', 'lesson:%')->exists());
        // nothing in the LMS before the apply gate
        $this->assertSame(0, EntityMapEntry::query()->where('session_id', $session->id)->count());
        $this->assertGreaterThan(0, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->count());
    }

    public function testApplyCreatesTheCourseThroughDomainServices(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author, 'coffee-brewing.md', ['quiz', 'final']);

        $this->assertSame(Session::APPLIED, $session->status);
        $course = Course::query()->findOrFail($session->course_id);
        $this->assertSame('draft', $course->status);
        $this->assertTrue($course->authors()->where('author_id', $author->getKey())->exists());
        $doc = $session->currentVersion->document;
        $stats = Blueprint::stats($doc);
        $this->assertSame($stats['modules'] + 1, $course->lessons()->count()); // + final test lesson
        $richTexts = Topic::query()->whereIn('lesson_id', $course->lessons()->pluck('id'))->where('topicable_type', RichText::class)->get();
        $this->assertSame($stats['lessons'], $richTexts->count());
        $this->assertStringContainsString('**Sources**', $richTexts->first()->topicable->value);
        $this->assertSame($stats['questions'], GiftQuestion::query()->whereIn('topic_gift_quiz_id', Topic::query()->whereIn('lesson_id', $course->lessons()->pluck('id'))->where('topicable_type', \Ulams\TopicTypeGift\Models\GiftQuiz::class)->pluck('topicable_id'))->count());
        $this->assertSame(Version::APPROVED, $session->currentVersion->status);
        $this->assertSame($session->current_version_id, $session->applied_version_id);
    }
}
