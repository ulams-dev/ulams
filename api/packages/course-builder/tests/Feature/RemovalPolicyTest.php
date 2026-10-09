<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Apply\DeleteEverything;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Contracts\FragmentArchive;
use Ulams\CourseBuilder\Contracts\RemovalPolicy;
use Ulams\CourseBuilder\Ingestion\NoFragmentArchive;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Models\GiftQuestion;

/** The extension points of the applier: what happens to removed elements, and fragments that left the source. */
class RemovalPolicyTest extends TestCase
{
    private function withoutFirstLessonAndQuestion(Session $session): Version
    {
        $doc = $session->currentVersion->document;
        $lessonId = $doc['modules'][0]['lessons'][0]['id'];
        unset($doc['modules'][0]['lessons'][0]);
        $doc['modules'][0]['lessons'] = array_values($doc['modules'][0]['lessons']);
        // and one question of the next lesson
        unset($doc['modules'][0]['lessons'][0]['quiz']['questions'][0]);
        $doc['modules'][0]['lessons'][0]['quiz']['questions'] = array_values($doc['modules'][0]['lessons'][0]['quiz']['questions']);
        $versions = app(VersionService::class);
        $version = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'remove ' . $lessonId, null, [], $session->author_id);
        $versions->setCurrent($session, $version);

        return $version;
    }

    public function testTheDefaultsAreThePhaseTwoBehaviour(): void
    {
        $this->assertInstanceOf(DeleteEverything::class, app(RemovalPolicy::class));
        $this->assertTrue(app(RemovalPolicy::class)->shouldDelete('topic', 1));
        $this->assertInstanceOf(NoFragmentArchive::class, app(FragmentArchive::class));
        $this->assertNull(app(FragmentArchive::class)->find('frg_aaaaaaaaaaaa'));
        $this->assertSame([], app(FragmentArchive::class)->knownIds('x'));

        $author = $this->author();
        $session = $this->toApplied($author);
        $lessonId = $session->currentVersion->document['modules'][0]['lessons'][0]['id'];
        $topicId = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lessonId)->where('entity_type', 'topic')->value('entity_id');
        $version = $this->withoutFirstLessonAndQuestion($session);

        app(BlueprintApplier::class)->apply($session, $version, $author);

        $this->assertNull(Topic::query()->find($topicId), 'deleted, as before');
        $this->assertNull(EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lessonId)->first());
    }

    public function testAPolicyThatRefusesDeactivatesTopicsAndArchivesQuestionsAndKeepsTheMapEntry(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $oldDoc = $session->currentVersion->document;
        $lessonId = $oldDoc['modules'][0]['lessons'][0]['id'];
        $questionId = $oldDoc['modules'][0]['lessons'][1]['quiz']['questions'][0]['id'];
        $entry = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lessonId)->where('entity_type', 'topic')->firstOrFail();
        $questionEntry = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $questionId)->where('entity_type', 'gift_question')->firstOrFail();
        $this->app->singleton(RemovalPolicy::class, fn () => new class implements RemovalPolicy {
            public function shouldDelete(string $entityType, int $entityId): bool
            {
                return false;
            }
        });
        $this->app->forgetInstance(BlueprintApplier::class);
        $version = $this->withoutFirstLessonAndQuestion($session);

        $applier = app(BlueprintApplier::class);
        $plan = $applier->plan($session, $version->document);
        $this->assertSame(2, $plan['delete']['topics'] ?? 0, 'the lesson page and its quiz page');
        $applier->apply($session, $version, $author);

        $topic = Topic::query()->findOrFail($entry->entity_id);
        $this->assertFalse((bool) $topic->active, 'deactivated, not deleted');
        $this->assertNotNull($entry->refresh()->retired_at);
        $this->assertNull($entry->fingerprint);
        $this->assertNotNull(GiftQuestion::query()->findOrFail($questionEntry->entity_id)->archived_at, 'the question is archived');
        $this->assertNull(EntityMapEntry::query()->find($questionEntry->id), 'an archived question has no map entry any more');
        $this->assertSame(0, $applier->plan($session, $version->document)['delete']['topics'], 'a retired entity is not removed again on every apply');

        // adding the lesson back reactivates the same topic
        $versions = app(VersionService::class);
        $back = $versions->create($session, $oldDoc, 'restore', 'restore', Version::APPROVED, $session->currentVersion, 'back', null, [], $author->getKey());
        $versions->setCurrent($session, $back);
        $applier->apply($session, $back, $author);
        $this->assertTrue((bool) $topic->refresh()->active);
        $this->assertNull($entry->refresh()->retired_at);
        $this->assertSame($entry->entity_id, $topic->getKey());
    }

    public function testEntitiesCreatedByAProposalAreMarked(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $doc = $session->currentVersion->document;
        $new = $doc['modules'][0]['lessons'][0];
        $new['id'] = Blueprint::newId();
        $new['title'] = 'A brand new lesson';
        foreach ($new['objectives'] as $i => $o) {
            $new['objectives'][$i]['id'] = Blueprint::newId();
        }
        foreach ($new['blocks'] as $i => $b) {
            $new['blocks'][$i]['id'] = Blueprint::newId();
            $new['blocks'][$i]['objectiveIds'] = [$new['objectives'][0]['id']];
        }
        $new['quiz'] = null;
        $doc['modules'][0]['lessons'][] = $new;
        $versions = app(VersionService::class);
        $version = $versions->create($session, $doc, 'update', 'ai', Version::APPROVED, $session->currentVersion, 'add', null, [], $author->getKey());
        $versions->setCurrent($session, $version);

        app(BlueprintApplier::class)->apply($session, $version, $author, false, '01prop0sa1000000000000000a');

        $this->assertSame('01prop0sa1000000000000000a', EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $new['id'])->value('added_by_proposal_id'));
        $this->assertNull(EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $doc['modules'][0]['lessons'][0]['id'])->where('entity_type', 'topic')->value('added_by_proposal_id'));
    }

    public function testTheFragmentEndpointFallsBackToTheArchive(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $url = '/api/admin/course-builder/fragments/frg_aaaaaaaaaaaa';
        $this->actingAs($author, 'api')->getJson($url)->assertStatus(404);

        $this->app->singleton(FragmentArchive::class, fn () => new class($session->id) implements FragmentArchive {
            public function __construct(private readonly string $sessionId)
            {
            }

            public function find(string $fragmentId): ?array
            {
                return ['id' => $fragmentId, 'label' => '§9.9 Gone', 'section' => '9.9', 'headingPath' => ['Gone'], 'text' => 'Old text.', 'pageStart' => null, 'pageEnd' => null, 'source' => ['id' => 's', 'name' => 'old.md'], 'sessionId' => $this->sessionId, 'revision' => 1];
            }

            public function knownIds(string $sessionId): array
            {
                return ['frg_aaaaaaaaaaaa'];
            }
        });

        $data = $this->actingAs($author, 'api')->getJson($url)->assertOk()->json('data');
        $this->assertTrue($data['removed']);
        $this->assertSame('Old text.', $data['text']);
        $this->assertSame(1, $data['revision']);
        $this->assertArrayNotHasKey('sessionId', $data);
        // another author never reads it
        $this->actingAs($this->tutor(), 'api')->getJson($url)->assertStatus(403);
    }
}
