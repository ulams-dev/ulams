<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Topic;

/**
 * Course-as-code, milestone CAC-4 (ADR 0160, plan 7.4): edits an admin made in the LMS after an apply are read back into a
 * proposed `author` version the author approves; what a blueprint cannot hold is listed, never guessed. AI is off throughout.
 */
class CourseAsCodeDriftTest extends TestCase
{
    private const P = '/api/admin/course-as-code';

    private function applied(): Session
    {
        $session = $this->toApplied($this->author());
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);

        return $session;
    }

    private function exported(Session $session): array
    {
        return $this->actingAs($session->author, 'api')->getJson(self::P . "/courses/{$session->course_id}/blueprint")->assertOk()->json('data');
    }

    /** A person approves an edit made in the studio: an approved version on top of the current one. */
    private function studioEdit(Session $session, callable $edit): Version
    {
        $doc = $session->refresh()->currentVersion->document;
        $edit($doc);
        $versions = app(VersionService::class);
        $version = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'studio edit', null, [], $session->author_id);
        $versions->setCurrent($session, $version);

        return $version;
    }

    /* ------------------------------------------------------------ absorbing admin edits */

    /** An admin edit of an entity, with a clock that is after the apply. */
    private function adminEdit(string $table, int $id, array $values): void
    {
        DB::table($table)->where('id', $id)->update($values + ['updated_at' => now()->addMinutes(5)]);
    }

    private function entity(Session $session, string $elementId, string $type): int
    {
        return (int) EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $elementId)->where('entity_type', $type)->firstOrFail()->entity_id;
    }

    private function absorb(Session $session, bool $dryRun = false)
    {
        return $this->actingAs($session->author, 'api')->postJson(self::P . "/sessions/{$session->id}/drift", $dryRun ? ['dryRun' => true] : []);
    }

    public function testNothingToAbsorbOnAFreshApply(): void
    {
        $session = $this->applied();
        $this->absorb($session)->assertOk()->assertJsonPath('data.unchanged', true)->assertJsonPath('data.version', null)->assertJsonPath('data.absorbed', [])->assertJsonPath('data.unmapped', []);
        $this->assertSame(['absorbable' => [], 'unmapped' => []], $this->exported($session)['edits']);
    }

    public function testAdminEditsAreReadBackIntoAProposedAuthorVersion(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $doc = $data['document'];
        $lesson = $doc['modules'][0]['lessons'][0];
        $courseEntity = $this->entity($session, $doc['course']['id'], 'course');
        $moduleEntity = $this->entity($session, $doc['modules'][0]['id'], 'lesson');
        $topicEntity = $this->entity($session, $lesson['id'], 'topic');
        $question = $lesson['quiz']['questions'][0];
        $questionEntity = $this->entity($session, $question['id'], 'gift_question');

        $this->adminEdit('courses', $courseEntity, ['title' => 'Coffee brewing, the admin way']);
        $this->adminEdit('lessons', $moduleEntity, ['title' => 'Module title from the admin']);
        $this->adminEdit('topics', $topicEntity, ['title' => 'Lesson title from the admin']);
        $text = Topic::query()->findOrFail($topicEntity)->topicable;
        $newText = str_replace('Roughly 30%', 'Around a third', (string) $text->value);
        DB::table($text->getTable())->where('id', $text->getKey())->update(['value' => $newText]);
        $gift = DB::table((new \Ulams\TopicTypeGift\Models\GiftQuestion())->getTable())->where('id', $questionEntity)->value('value');
        $this->adminEdit((new \Ulams\TopicTypeGift\Models\GiftQuestion())->getTable(), $questionEntity, ['value' => str_replace('Which statement matches', 'Which statement fits', $gift)]);

        $keys = array_column($this->exported($session)['edits']['absorbable'], 'element');
        sort($keys);
        $this->assertSame(['course:' . $doc['course']['id'], 'gift_question:' . $question['id'], 'lesson:' . $doc['modules'][0]['id'], 'topic:' . $lesson['id']], $keys);

        // a dry run lists and stores nothing
        $count = Version::query()->where('session_id', $session->id)->count();
        $dry = $this->absorb($session, true)->assertOk()->assertJsonPath('data.unchanged', false)->assertJsonPath('data.version', null)->assertJsonPath('data.dryRun', true);
        $this->assertCount(4, $dry->json('data.absorbed'));
        $this->assertSame($count, Version::query()->where('session_id', $session->id)->count());

        $res = $this->absorb($session)->assertCreated();
        $res->assertJsonPath('data.version.kind', 'author')->assertJsonPath('data.version.status', 'proposed')->assertJsonPath('data.version.parentId', $data['versionId']);
        $this->assertSame([], $res->json('data.unmapped'));
        $stored = Version::query()->findOrFail($res->json('data.version.id'));
        $this->assertSame('author', $stored->origin);
        $this->assertStringStartsWith('Admin edits since version', (string) $stored->reason);
        $new = $stored->document;
        $this->assertSame('Coffee brewing, the admin way', $new['course']['title']);
        $this->assertSame('Module title from the admin', $new['modules'][0]['title']);
        $this->assertSame('Lesson title from the admin', $new['modules'][0]['lessons'][0]['title']);
        $this->assertStringContainsString('Around a third', $new['modules'][0]['lessons'][0]['blocks'][0]['markdown']);
        $this->assertSame('author', $new['modules'][0]['lessons'][0]['blocks'][0]['origin'], 'a human wrote it now');
        $this->assertSame($lesson['blocks'][1]['markdown'], $new['modules'][0]['lessons'][0]['blocks'][1]['markdown'], 'blocks nobody touched keep their text and origin');
        $this->assertArrayNotHasKey('origin', $new['modules'][0]['lessons'][0]['blocks'][1]);
        $this->assertStringStartsWith('Which statement fits', $new['modules'][0]['lessons'][0]['quiz']['questions'][0]['stem']);
        $this->assertSame($question['options'], array_map(fn ($o) => $o, $new['modules'][0]['lessons'][0]['quiz']['questions'][0]['options']), 'options are unchanged');
        $this->assertEquals(['changed' => 5, 'added' => 0, 'removed' => 0], $res->json('data.counts'));

        // the current version and the course are untouched until a person approves and applies
        $this->assertSame($data['versionId'], $session->refresh()->current_version_id);
        $event = Event::query()->where('session_id', $session->id)->where('type', 'CUSTOM')->get()->first(fn ($e) => ($e->payload['name'] ?? null) === 'course_as_code.drift_absorbed');
        $this->assertSame($stored->id, $event->payload['value']['versionId']);

        // approval follows the same rules as any import; then the apply may write the same values back
        $this->actingAs($session->author, 'api')->postJson(self::P . "/versions/{$stored->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame($stored->id, $session->refresh()->current_version_id);
        $drift = array_column($this->exported($session)['drift'], 'element');
        sort($drift);
        $this->assertSame($keys, $drift, 'what an apply would overwrite is exactly what was absorbed');
        $run = $this->actingAs($session->author, 'api')->postJson(self::P . "/sessions/{$session->id}/apply", ['overwrite' => true])->assertStatus(202);
        $this->assertSame($stored->id, $session->refresh()->applied_version_id);
        $this->assertSame('Coffee brewing, the admin way', \Ulams\Courses\Models\Course::query()->find($courseEntity)->title);
        $this->assertStringContainsString('Around a third', (string) Topic::query()->findOrFail($topicEntity)->topicable->value);
        $this->assertSame(['absorbable' => [], 'unmapped' => []], $this->exported($session)['edits'], 'nothing is left to read back');
        $this->assertNotNull($run->json('data.runId'));
    }

    public function testEditsThatCannotBeMappedBackAreListedAndTheBlueprintKeepsItsValue(): void
    {
        $session = $this->applied();
        $doc = $this->exported($session)['document'];
        $lesson = $doc['modules'][0]['lessons'][0];
        $lesson2 = $doc['modules'][1]['lessons'][0];
        $question = $lesson['quiz']['questions'][0];
        $topic = $this->entity($session, $lesson['id'], 'topic');
        $topic2 = $this->entity($session, $lesson2['id'], 'topic');
        $questionEntity = $this->entity($session, $question['id'], 'gift_question');

        // HTML from the visual editor; a paragraph added in the admin; a changed correct answer
        $text = Topic::query()->findOrFail($topic)->topicable;
        DB::table($text->getTable())->where('id', $text->getKey())->update(['value' => '<p>Rewritten with the <b>visual</b> editor.</p>']);
        $this->adminEdit('topics', $topic, []);
        $text2 = Topic::query()->findOrFail($topic2)->topicable;
        DB::table($text2->getTable())->where('id', $text2->getKey())->update(['value' => preg_replace('/\n\n---\n\n\*\*Sources\*\*/', "\n\nA whole new paragraph.\n\n---\n\n**Sources**", (string) $text2->value)]);
        $this->adminEdit('topics', $topic2, []);
        $gift = (string) DB::table((new \Ulams\TopicTypeGift\Models\GiftQuestion())->getTable())->where('id', $questionEntity)->value('value');
        $flipped = str_replace(['=Brewing', '~A short'], ['~Brewing', '=A short'], $gift);
        $this->assertNotSame($gift, $flipped);
        $this->adminEdit((new \Ulams\TopicTypeGift\Models\GiftQuestion())->getTable(), $questionEntity, ['value' => $flipped]);

        $count = Version::query()->where('session_id', $session->id)->count();
        $res = $this->absorb($session)->assertOk()->assertJsonPath('data.unchanged', true)->assertJsonPath('data.absorbed', []);
        $reasons = collect($res->json('data.unmapped'))->keyBy('key');
        $this->assertStringContainsString('HTML', $reasons['topic:' . $lesson['id']]['reason']);
        $this->assertStringContainsString('added, removed or merged', $reasons['topic:' . $lesson2['id']]['reason']);
        $this->assertStringContainsString('correct', $reasons['gift_question:' . $question['id']]['reason']);
        $this->assertSame(3, count($reasons));
        $this->assertSame($count, Version::query()->where('session_id', $session->id)->count(), 'nothing was stored');
    }

    public function testWhereTheBlueprintChangedTooTheEditIsLeftForThePeopleToResolve(): void
    {
        $session = $this->applied();
        $doc = $this->exported($session)['document'];
        $this->studioEdit($session, function (array &$d) {
            $d['course']['title'] = 'Studio title since the apply';
        });
        $this->adminEdit('courses', $this->entity($session, $doc['course']['id'], 'course'), ['title' => 'Admin title since the apply']);
        $this->adminEdit('lessons', $this->entity($session, $doc['modules'][0]['id'], 'lesson'), ['summary' => 'A summary written in the admin']);

        $res = $this->absorb($session)->assertCreated();
        $this->assertSame(['lesson:' . $doc['modules'][0]['id']], array_column($res->json('data.absorbed'), 'key'));
        $this->assertSame(['course:' . $doc['course']['id']], array_column($res->json('data.unmapped'), 'key'));
        $new = Version::query()->findOrFail($res->json('data.version.id'))->document;
        $this->assertSame('Studio title since the apply', $new['course']['title'], 'the blueprint value stays');
        $this->assertSame('A summary written in the admin', $new['modules'][0]['summary']);
    }

    public function testAbsorbingNeedsAnAppliedCourseAndABlueprint(): void
    {
        $author = $this->author();
        $empty = Session::query()->create(['author_id' => $author->getKey()]);
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$empty->id}/drift")->assertStatus(409)->assertJsonPath('code', 'NO_BLUEPRINT');

        $review = $this->toApplyReview($author);
        config(['ai.driver' => 'disabled']);
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$review->id}/drift")->assertStatus(409)->assertJsonPath('code', 'NOT_APPLIED');
    }

    public function testAnAgentTokenMayAbsorbButNeverApproveWhatItAbsorbed(): void
    {
        $session = $this->applied();
        $doc = $this->exported($session)['document'];
        $this->adminEdit('courses', $this->entity($session, $doc['course']['id'], 'course'), ['title' => 'Admin title']);
        $issuer = app(PersonalAccessTokenServiceContract::class);
        $agent = $issuer->issue($session->author, 'agent', ['builder:write'], 7, 'agent', 'claude')->secret;
        $this->app['auth']->forgetGuards();
        $id = $this->withHeaders(['Authorization' => "Bearer {$agent}"])->postJson(self::P . "/sessions/{$session->id}/drift")->assertCreated()->json('data.version.id');
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => "Bearer {$agent}"])->postJson(self::P . "/versions/{$id}/approve")->assertStatus(403)->assertJsonPath('code', 'APPROVAL_REQUIRES_HUMAN');
        $this->assertSame(Version::PROPOSED, Version::query()->find($id)->status);
        $this->app['auth']->forgetGuards();
        $this->actingAs($session->author, 'api')->postJson(self::P . "/versions/{$id}/approve")->assertOk();
    }
}
