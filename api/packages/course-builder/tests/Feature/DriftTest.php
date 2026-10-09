<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Exceptions\BuilderException;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Topic;

/** ADR 0010: an apply never silently overwrites what an admin edited after the last apply. */
class DriftTest extends TestCase
{
    private function editedVersion(Session $session): Version
    {
        $doc = $session->currentVersion->document;
        $doc['modules'][0]['lessons'][0]['blocks'][0]['markdown'] .= "\n\nAn extra sentence added in the blueprint.";
        $versions = app(VersionService::class);
        $version = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'blueprint edit', null, [], $session->author_id);
        $versions->setCurrent($session, $version);

        return $version;
    }

    private function adminEdit(Session $session): Topic
    {
        $lessonId = $session->currentVersion->document['modules'][0]['lessons'][0]['id'];
        $entry = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lessonId)->where('entity_type', 'topic')->firstOrFail();
        DB::table('topics')->where('id', $entry->entity_id)->update(['summary' => 'Edited by an admin', 'updated_at' => now()->addMinutes(5)]);

        return Topic::query()->findOrFail($entry->entity_id);
    }

    public function testAnUntouchedCourseHasNoDrift(): void
    {
        $session = $this->toApplied($this->author());
        $version = $this->editedVersion($session);

        $this->assertSame([], app(BlueprintApplier::class)->drift($session, $version->document));
    }

    public function testAdminEditsStopTheReapplyOfTheSameElement(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $topic = $this->adminEdit($session);
        $version = $this->editedVersion($session);
        $applier = app(BlueprintApplier::class);

        $drift = $applier->drift($session, $version->document);
        $this->assertCount(1, $drift);
        $this->assertSame($session->currentVersion->document['modules'][0]['lessons'][0]['title'], array_values($drift)[0]);
        $warnings = $applier->plan($session, $version->document)['warnings'];
        $this->assertStringContainsString('Edited in the admin after the last apply', implode(' ', $warnings));

        try {
            $applier->apply($session, $version, $author);
            $this->fail('The apply overwrote an admin edit.');
        } catch (BuilderException $e) {
            $this->assertSame(409, $e->status);
            $this->assertStringContainsString('edited in the admin', $e->getMessage());
        }
        $this->assertSame('Edited by an admin', $topic->refresh()->summary);

        // the author may confirm the overwrite
        $applier->apply($session, $version, $author, true);
        $this->assertStringContainsString('An extra sentence added in the blueprint', (string) $topic->refresh()->topicable->value);
        // and after that the element is in sync again
        $this->assertSame([], $applier->drift($session, $version->document));
    }

    public function testEditsToOtherElementsDoNotBlockTheApply(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        // an admin edits lesson 2's topic, the blueprint edit is about lesson 1
        $second = $session->currentVersion->document['modules'][0]['lessons'][1]['id'];
        $entry = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $second)->where('entity_type', 'topic')->firstOrFail();
        DB::table('topics')->where('id', $entry->entity_id)->update(['updated_at' => now()->addMinutes(5)]);
        $version = $this->editedVersion($session);

        $this->assertSame([], app(BlueprintApplier::class)->drift($session, $version->document));
        app(BlueprintApplier::class)->apply($session, $version, $author);
        $this->assertTrue(true);
    }

    public function testTheApplyEndpointCarriesTheOverwriteConfirmation(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->adminEdit($session);
        $this->editedVersion($session);
        $url = "/api/admin/course-builder/sessions/{$session->id}/apply";

        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $run = $session->runs()->where('kind', 'apply')->orderByDesc('id')->first();
        $this->assertSame('failed', $run->refresh()->status);
        $this->assertStringContainsString('edited in the admin', (string) $run->error);

        $this->actingAs($author, 'api')->postJson($url, ['overwrite' => true])->assertStatus(202);
        $this->assertSame('finished', $session->runs()->where('kind', 'apply')->orderByDesc('id')->first()->status);
    }
}
