<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Topic;
use Ulams\H5P\Testing\H5PServiceFake;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Models\InteractivePackageVersion;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\LiaScript\Models\LiaScriptDocument;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/** Lesson formats (ADR 0050): LiaScript with self-checks, an H5P activity, an interactive from the library. */
class LessonFormatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        H5PServiceFake::$libraries = ['H5P.Blanks' => '1.14', 'H5P.DragText' => '1.10', 'H5P.Dialogcards' => '1.9'];
        H5PServiceFake::fake();
    }

    /** Outline → the author picks formats while approving → generation, up to the apply review. */
    private function generated(array $formats): Session
    {
        $author = $this->author();
        $session = $this->toOutline($author, 'coffee-brewing.md', ['quiz']);
        $doc = $session->currentVersion->document;
        $lessons = iterator_to_array(Blueprint::lessons($doc), false);
        $choices = [];
        foreach ($formats as $index => $format) {
            $choices[] = ['lessonId' => $lessons[$index]['lesson']['id'], 'contentType' => $format];
        }
        $outline = $session->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline, 'formats' => $choices])->assertStatus(202);

        return $session->refresh();
    }

    private function lesson(Session $session, int $index): array
    {
        return iterator_to_array(Blueprint::lessons($session->currentVersion->document), false)[$index]['lesson'];
    }

    private function apply(Session $session): void
    {
        $version = $session->current_version_id;
        $this->action($session->author, $session, 'approve_apply', "apply-{$version}", ['versionId' => $version])->assertStatus(202);
    }

    /** The LMS topic of a blueprint element (a lesson's text, or its interaction), through the entity map. */
    private function topicOf(Session $session, string $elementId, string $type = 'topic'): ?Topic
    {
        $id = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', $type)->where('element_id', $elementId)->value('entity_id');

        return $id === null ? null : Topic::query()->find($id);
    }

    public function testTheAuthorChoosesAFormatInTheOutlineAndTheLessonGetsSelfChecks(): void
    {
        $session = $this->generated(['liascript']);
        $this->assertSame(Session::APPLY_REVIEW, $session->status);
        $doc = $session->currentVersion->document;
        $this->assertSame(2, $doc['schemaVersion']);
        $this->assertSame([], app(SchemaRegistry::class)->validateBlueprint($doc));

        $first = $this->lesson($session, 0);
        $this->assertSame('liascript', $first['contentType']);
        $this->assertGreaterThanOrEqual(2, count($first['selfChecks']));
        $this->assertLessThanOrEqual(3, count($first['selfChecks']));
        foreach ($first['selfChecks'] as $check) {
            $this->assertNotEmpty($check['citations']);
            $this->assertNotEmpty($check['objectiveIds']);
        }
        $known = array_fill_keys($session->fragmentIds(), true);
        $this->assertSame([], array_values(array_filter(Checks::blueprint($doc, $known), fn ($e) => !str_starts_with($e, 'warning'))));

        // only the LiaScript lesson got a follow-up step
        $this->assertSame(1, Step::query()->where('key', 'like', 'selfcheck:%')->where('status', 'done')->count());
        $this->assertSame(0, Step::query()->where('key', 'like', 'interaction:%')->count());
        $this->assertSame('author', $session->currentVersion->origin, 'the choice is an author version on top of the outline');
    }

    public function testAnApplyCreatesALiaScriptTopicThatKeepsItsVersionsAndSwitchesBack(): void
    {
        $session = $this->generated(['liascript']);
        $this->apply($session);
        $session->refresh();
        $lesson = $this->lesson($session, 0);
        $topic = $this->topicOf($session, $lesson['id']);

        $this->assertSame(LiaScriptTopic::class, $topic->topicable_type);
        $document = LiaScriptDocument::query()->findOrFail($topic->topicable->value);
        $markdown = $document->version($document->current_version)->markdown;
        $this->assertStringContainsString('# ' . $lesson['title'], $markdown);
        $this->assertStringContainsString('## Check yourself', $markdown);
        $this->assertStringContainsString('- [(X)]', $markdown);
        $this->assertStringContainsString('**Sources**', $markdown);
        $this->assertStringNotContainsString('<script', $markdown);
        // the quiz topic follows, the second lesson stays rich text
        $this->assertSame(1, $document->current_version);
        $this->assertSame(RichText::class, $this->topicOf($session, $this->lesson($session, 1)['id'])->topicable_type);

        // an edited self-check re-applies as a new LiaScript version of the same document and topic
        $doc = $session->currentVersion->document;
        $doc['modules'][0]['lessons'][0]['selfChecks'][0]['stem'] = 'A reworded question about the same passage?';
        $versions = app(VersionService::class);
        $edited = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'edit a self-check', null, [], $session->author_id);
        $versions->setCurrent($session, $edited);
        app(BlueprintApplier::class)->apply($session, $edited, $session->author);
        $same = Topic::query()->findOrFail($topic->id);
        $this->assertSame($document->getKey(), (int) $same->topicable->value);
        $this->assertSame(2, $document->refresh()->current_version);
        $this->assertStringContainsString('A reworded question', $document->version(2)->markdown);
        $this->assertStringNotContainsString('A reworded question', $document->version(1)->markdown);

        // back to rich text: the topic is replaced and the document goes with it
        $doc['modules'][0]['lessons'][0]['contentType'] = 'richtext';
        unset($doc['modules'][0]['lessons'][0]['selfChecks']);
        $back = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $edited, 'rich text again', null, [], $session->author_id);
        $versions->setCurrent($session, $back);
        app(BlueprintApplier::class)->apply($session, $back, $session->author);
        $this->assertNull(LiaScriptDocument::query()->find($document->getKey()));
        $this->assertSame(RichText::class, $this->topicOf($session, $lesson['id'])->topicable_type);
        $this->assertSame(1, EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lesson['id'])->where('entity_type', 'topic')->count());
    }

    public function testAnH5pLessonCreatesTheActivityThroughTheServiceAndUpdatesItInPlace(): void
    {
        $session = $this->generated(['h5p']);
        $first = $this->lesson($session, 0);
        $this->assertSame('h5p', $first['contentType']);
        $this->assertSame('h5p', $first['interaction']['kind']);
        $this->assertSame('H5P.Blanks', $first['interaction']['library']);
        $this->assertNotEmpty($first['interaction']['citations']);
        $known = array_fill_keys($session->fragmentIds(), true);
        $this->assertSame([], array_values(array_filter(Checks::blueprint($session->currentVersion->document, $known), fn ($e) => !str_starts_with($e, 'warning'))));

        $this->apply($session);
        $text = $this->topicOf($session->refresh(), $first['id']);
        $activity = $this->topicOf($session, $first['interaction']['id'], 'interaction_topic');
        $this->assertSame(RichText::class, $text->topicable_type);
        $this->assertSame(H5P::class, $activity->topicable_type);
        $this->assertSame($text->lesson_id, $activity->lesson_id);
        $this->assertSame($text->order + 1, $activity->order, 'the activity follows the text');
        $contentId = (int) $activity->topicable->value;
        $content = DB::table('h5p.contents')->where('id', $contentId)->first();
        $this->assertSame('H5P.Blanks', $content->main_library);
        $params = json_decode($content->parameters, true);
        $this->assertNotEmpty($params['questions']);
        $this->assertStringContainsString('*', $params['questions'][0]);
        $this->assertStringNotContainsString('{1}', $params['questions'][0]);

        // a changed activity updates the same H5P content
        $doc = $session->currentVersion->document;
        $doc['modules'][0]['lessons'][0]['interaction']['data']['instruction'] = 'Complete each sentence.';
        $versions = app(VersionService::class);
        $edited = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'edit the activity', null, [], $session->author_id);
        $versions->setCurrent($session, $edited);
        app(BlueprintApplier::class)->apply($session, $edited, $session->author);
        $this->assertSame(1, DB::table('h5p.contents')->where('id', $contentId)->count());
        $this->assertSame($contentId, (int) $this->topicOf($session, $first['interaction']['id'], 'interaction_topic')->topicable->value);
        $this->assertStringContainsString('Complete each sentence.', (string) DB::table('h5p.contents')->where('id', $contentId)->value('parameters'));

        // dropping the activity removes the topic and the content
        $doc['modules'][0]['lessons'][0]['contentType'] = 'richtext';
        $doc['modules'][0]['lessons'][0]['interaction'] = null;
        $back = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $edited, 'no activity', null, [], $session->author_id);
        $versions->setCurrent($session, $back);
        app(BlueprintApplier::class)->apply($session, $back, $session->author);
        $this->assertSame(0, DB::table('h5p.contents')->where('id', $contentId)->count());
        $this->assertNull($this->topicOf($session, $first['interaction']['id'], 'interaction_topic'));
        $this->assertNotNull($this->topicOf($session, $first['id']));
    }

    public function testAnInteractiveFromTheLibraryIsAttachedThroughTheTopicRepository(): void
    {
        $package = InteractivePackage::query()->create(['title' => 'Extraction explorer', 'current_version' => 1]);
        InteractivePackageVersion::query()->create([
            'interactive_package_id' => $package->id, 'version' => 1, 'entry' => 'index.html', 'files' => ['index.html' => ['size' => 10, 'sha256' => str_repeat('a', 64)]], 'total_bytes' => 10, 'licence' => 'MIT',
            'manifest' => ['id' => 'extraction', 'defaultLocale' => 'en', 'locales' => ['en'], 'title' => ['en' => 'Extraction explorer'], 'steps' => [
                ['id' => 'intro', 'title' => ['en' => 'Introduction'], 'textAlternative' => ['en' => 'A cup of coffee.']],
                ['id' => 'grind', 'title' => ['en' => 'Grind size'], 'textAlternative' => ['en' => 'Finer grounds extract faster.']],
            ]],
        ]);

        $session = $this->generated(['interactive']);
        $first = $this->lesson($session, 0);
        $this->assertSame('interactive', $first['interaction']['kind']);
        $this->assertSame($package->id, $first['interaction']['packageId']);
        $this->assertSame('intro', $first['interaction']['startStep']);
        $this->assertSame('grind', $first['interaction']['endStep']);
        $this->assertSame([], array_values(array_filter(Checks::blueprint($session->currentVersion->document, array_fill_keys($session->fragmentIds(), true)), fn ($e) => !str_starts_with($e, 'warning'))));

        $this->apply($session);
        $text = $this->topicOf($session->refresh(), $first['id']);
        $activity = $this->topicOf($session, $first['interaction']['id'], 'interaction_topic');
        $this->assertSame(RichText::class, $text->topicable_type);
        $this->assertSame(InteractiveTopic::class, $activity->topicable_type);
        $topic = $activity->topicable;
        $this->assertSame($package->id, (int) $topic->value);
        $this->assertSame('intro', $topic->start_step);
        $this->assertStringContainsString('**Sources:**', (string) $topic->text);
        $this->assertSame(1, (int) $topic->version, 'the topic pins the version it was applied with');
    }

    public function testAFormatThatIsNotAvailableCannotBeChosen(): void
    {
        H5PServiceFake::$libraries = [];
        $registry = app(ContentTypeRegistry::class);
        $this->assertArrayNotHasKey('h5p', $registry->enabled());
        $this->assertArrayNotHasKey('interactive', $registry->enabled(), 'no package in the library');
        $this->assertArrayHasKey('liascript', $registry->enabled());

        $author = $this->author();
        $session = $this->toOutline($author);
        $lessonId = $session->currentVersion->document['modules'][0]['lessons'][0]['id'];
        $outline = $session->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline, 'formats' => [['lessonId' => $lessonId, 'contentType' => 'h5p']]]);
        $this->assertSame(Session::OUTLINE_REVIEW, $session->refresh()->status, 'the outline stays open for review');
    }

    public function testTheRuleBehindAutoFormatsNeedsTwoObjectives(): void
    {
        config(['course_builder.auto_formats' => true]);
        $author = $this->author();
        $session = $this->toOutline($author);
        foreach (Blueprint::lessons($session->currentVersion->document) as $item) {
            $expected = count($item['lesson']['objectives']) >= 2 ? 'liascript' : 'richtext';
            $this->assertSame($expected, $item['lesson']['contentType']);
        }
    }

    public function testTheSuggestionsListWhatSuitsALesson(): void
    {
        $registry = app(ContentTypeRegistry::class);
        $this->assertSame(['richtext', 'liascript', 'h5p'], $registry->suggest(['objectives' => [1, 2]]));
        $this->assertSame(['richtext', 'h5p'], $registry->suggest(['objectives' => [1]]));
        $this->assertSame('richtext', $registry->for('unknown')->key());
    }
}
