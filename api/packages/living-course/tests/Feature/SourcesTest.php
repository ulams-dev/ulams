<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;
use Ulams\LivingCourse\Services\RevisionService;
use Ulams\LivingCourse\Tests\TestCase;

class SourcesTest extends TestCase
{
    public function testIngestingASourceRecordsRevisionOneFromTheLiveFragments(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $source = $this->sourceOf($session);
        $connection = $this->connectionOf($session);

        $this->assertSame('upload', $connection->connector);
        $this->assertSame('manual', $connection->schedule);
        $this->assertSame(26, strlen($connection->webhook_id));
        $revision = Revision::query()->findOrFail($connection->synced_revision_id);
        $this->assertSame($revision->id, $connection->latest_revision_id);
        $this->assertSame(1, $revision->number);
        $this->assertSame('initial', $revision->origin);
        $this->assertSame($source->sha256, $revision->origin_ref);
        $this->assertSame('ingested', $revision->status);

        $live = Fragment::query()->where('source_id', $source->id)->orderBy('ordinal')->get();
        $copy = RevisionFragment::query()->where('revision_id', $revision->id)->orderBy('ordinal')->get();
        $this->assertSame($live->count(), $copy->count());
        $this->assertSame($live->count(), $revision->fragment_count);
        $this->assertSame($live->pluck('id')->all(), $copy->pluck('fragment_id')->all());
        $this->assertSame($live->pluck('content_hash')->all(), $copy->pluck('content_hash')->all());
        $this->assertSame($live->pluck('text')->all(), $copy->pluck('text')->all());
        $this->assertSame(64, strlen($copy->first()->normalised_hash));
    }

    public function testEnsureInitialIsIdempotent(): void
    {
        $session = $this->sessionWithSource($this->author());
        $source = $this->sourceOf($session);
        $first = Revision::query()->where('source_id', $source->id)->firstOrFail();

        $again = app(RevisionService::class)->ensureInitial($source);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Revision::query()->where('source_id', $source->id)->count());
        $this->assertSame(1, Connection::query()->where('source_id', $source->id)->count());
    }

    public function testSourcesAndRevisionsAreListedForTheAuthor(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $source = $this->sourceOf($session);
        $p = '/api/admin/living-course';

        $sources = $this->actingAs($author, 'api')->getJson("{$p}/sessions/{$session->id}/sources")->assertOk()->json('data');
        $this->assertCount(1, $sources);
        $this->assertSame($source->id, $sources[0]['id']);
        $this->assertSame('upload', $sources[0]['connection']['connector']);
        $this->assertSame(1, $sources[0]['connection']['syncedRevision']['number']);
        $this->assertSame(1, $sources[0]['revisionCount']);

        $revisions = $this->actingAs($author, 'api')->getJson("{$p}/sources/{$source->id}/revisions")->assertOk()->json('data');
        $this->assertCount(1, $revisions);
        $this->assertTrue($revisions[0]['synced']);
        $one = $this->actingAs($author, 'api')->getJson("{$p}/revisions/{$revisions[0]['id']}")->assertOk()->json('data');
        $this->assertSame(1, $one['number']);
        $this->assertGreaterThan(10, $one['fragmentCount']);
    }

    public function testSecretsAreNeverSerialised(): void
    {
        $session = $this->sessionWithSource($this->author());
        $connection = $this->connectionOf($session);
        $connection->forceFill(['secrets' => ['token' => 'ghp_supersecret', 'webhook_secret' => 'wh_supersecret']])->save();

        $this->assertStringNotContainsString('supersecret', json_encode($connection->refresh()->toArray()));
        $this->assertSame('ghp_supersecret', $connection->secrets['token']);
        $this->assertStringNotContainsString('supersecret', (string) DB::table('living_course_connections')->where('id', $connection->id)->value('secrets'), 'secrets are encrypted at rest');
        $response = $this->actingAs($session->author, 'api')->getJson("/api/admin/living-course/sessions/{$session->id}/sources")->assertOk();
        $this->assertStringNotContainsString('supersecret', $response->getContent());
        $this->assertSame(['token', 'webhook_secret'], $response->json('data.0.connection.secretsSet'));
    }

    /** @return array<int,array{0:string,1:string}> */
    private function endpoints(string $sessionId, string $sourceId, string $revisionId): array
    {
        $p = '/api/admin/living-course';

        return [
            ['GET', "{$p}/sessions/{$sessionId}/sources"],
            ['GET', "{$p}/sources/{$sourceId}/revisions"],
            ['GET', "{$p}/revisions/{$revisionId}"],
        ];
    }

    public function testAnotherTutorStudentsAndGuestsAreRefusedAndUnknownIdsAreNotFound(): void
    {
        $owner = $this->author();
        $session = $this->sessionWithSource($owner);
        $source = $this->sourceOf($session);
        $revision = Revision::query()->where('source_id', $source->id)->firstOrFail();
        $endpoints = $this->endpoints($session->id, $source->id, $revision->id);

        foreach ($endpoints as [$method, $uri]) {
            $this->actingAs($owner, 'api')->json($method, $uri)->assertOk();
            $this->actingAs($this->tutor(), 'api')->json($method, $uri)->assertStatus(403);
            $this->actingAs($this->student(), 'api')->json($method, $uri)->assertStatus(403);
            $this->actingAs($this->admin(), 'api')->json($method, $uri)->assertOk();
        }
        $this->app['auth']->forgetGuards();
        foreach ($endpoints as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(401);
        }

        // ids that do not exist in this tenant database (another tenant's ids look like this) are 404
        $missing = '01aaaaaaaaaaaaaaaaaaaaaaaa';
        foreach ($this->endpoints($missing, $missing, $missing) as [$method, $uri]) {
            $this->actingAs($owner, 'api')->json($method, $uri)->assertStatus(404);
        }
        $this->actingAs($owner, 'api')->getJson('/api/admin/living-course/sessions/not-an-id/sources')->assertStatus(404);
    }

    public function testUploadingANewVersionCreatesARevisionWithItsOwnFragments(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author, 'coffee-brewing.md');
        $source = $this->sourceOf($session);
        $liveBefore = Fragment::query()->where('source_id', $source->id)->pluck('text', 'id')->all();
        $one = Revision::query()->where('source_id', $source->id)->firstOrFail();

        $response = $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$source->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
        $this->assertFalse($response->json('data.unchanged'));
        $this->assertSame(2, $response->json('data.revision.number'));
        $this->assertSame('upload', $response->json('data.revision.origin'));
        $two = Revision::query()->findOrFail($response->json('data.revision.id'));

        // the course keeps reading from the synced revision: live fragments are untouched
        $this->assertSame($liveBefore, Fragment::query()->where('source_id', $source->id)->pluck('text', 'id')->all());
        $connection = $this->connectionOf($session);
        $this->assertSame($one->id, $connection->synced_revision_id);
        $this->assertSame($two->id, $connection->latest_revision_id);

        // same source row, same heading positions: unchanged positions keep their ids across revisions
        $oldIds = RevisionFragment::query()->where('revision_id', $one->id)->pluck('fragment_id')->all();
        $newRows = RevisionFragment::query()->where('revision_id', $two->id)->get();
        $newIds = $newRows->pluck('fragment_id')->all();
        $this->assertGreaterThan(10, count(array_intersect($oldIds, $newIds)));
        $this->assertNotEmpty(array_diff($newIds, $oldIds), 'the added section has new ids');
        $this->assertNotEmpty(array_diff($oldIds, $newIds), 'the removed section is gone from revision 2');
        $ratio = $newRows->first(fn ($r) => $r->label() === '§2.1 The brew ratio');
        $this->assertStringContainsString('1:15', $ratio->text);
        $this->assertSame($liveBefore[$ratio->fragment_id] !== $ratio->text, true);
        $this->assertSame($two->fragment_count, $newRows->count());
        $this->assertSame(64, strlen((string) $two->normalised_sha256));
        $this->assertNotSame($one->normalised_sha256, $two->normalised_sha256);
        $this->assertSame('coffee-brewing.v2.md', $two->metadata['name']);
        $this->assertTrue(\Storage::disk('course_builder_private')->exists($two->markdown_path));
        $this->assertTrue(\Storage::disk('course_builder_private')->exists($two->raw_path));
    }

    public function testUploadingTheSameFileAgainIsReportedAsUnchanged(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $source = $this->sourceOf($session);
        $url = "/api/admin/living-course/sources/{$source->id}/revisions";

        $this->actingAs($author, 'api')->post($url, ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
        $again = $this->actingAs($author, 'api')->post($url, ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertOk();

        $this->assertTrue($again->json('data.unchanged'));
        $this->assertSame(2, Revision::query()->where('source_id', $source->id)->count());
    }

    public function testAnotherSupportedTypeMayReplaceTheSource(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $source = $this->sourceOf($session);

        $response = $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$source->id}/revisions", ['file' => $this->fixture('coffee-handbook.pdf')])->assertCreated();

        $this->assertSame('coffee-handbook.pdf', $response->json('data.revision.name'));
        $this->assertGreaterThan(0, $response->json('data.revision.fragmentCount'));
    }

    public function testUnsupportedAndMismatchedUploadsAreRejectedWithoutARevision(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $source = $this->sourceOf($session);
        $url = "/api/admin/living-course/sources/{$source->id}/revisions";
        $exe = tempnam(sys_get_temp_dir(), 'lcbad') . '.exe';
        file_put_contents($exe, "MZ\x90\x00binary");
        $fake = tempnam(sys_get_temp_dir(), 'lcbad') . '.md';
        file_put_contents($fake, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n");

        $this->actingAs($author, 'api')->post($url, ['file' => new UploadedFile($exe, 'tool.exe', null, null, true)])->assertStatus(422);
        $this->actingAs($author, 'api')->post($url, ['file' => new UploadedFile($fake, 'notes.md', null, null, true)])->assertStatus(422);
        $this->actingAs($author, 'api')->postJson($url, [])->assertStatus(422);
        $this->assertSame(1, Revision::query()->where('source_id', $source->id)->count());
        @unlink($exe);
        @unlink($fake);
    }

    public function testOnlyTheAuthorOrAReviewerMayUploadAVersion(): void
    {
        $owner = $this->author();
        $session = $this->sessionWithSource($owner);
        $source = $this->sourceOf($session);
        $url = "/api/admin/living-course/sources/{$source->id}/revisions";

        $this->actingAs($this->tutor(), 'api')->post($url, ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertStatus(403);
        $this->actingAs($this->student(), 'api')->post($url, ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertStatus(403);
        $this->assertSame(1, Revision::query()->where('source_id', $source->id)->count());
        // an admin holding living_course_review keeps the course in sync when the author is away
        $this->actingAs($this->admin(), 'api')->post($url, ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
        // without that permission an admin can only look
        $this->actingAs($this->readOnlyAdmin(), 'api')->post($url, ['file' => $this->lcFixture('coffee-brewing.v1.md')])->assertStatus(403);
        $this->actingAs($this->readOnlyAdmin(), 'api')->getJson("/api/admin/living-course/sessions/{$session->id}/sources")->assertOk();
        $this->assertSame(2, Revision::query()->where('source_id', $source->id)->count());
    }

    public function testBackfillRecordsRevisionOneForSourcesBuiltBeforePhaseThree(): void
    {
        $session = $this->sessionWithSource($this->author());
        $source = $this->sourceOf($session);
        // a session built before Phase 3 has no connection
        Revision::query()->where('source_id', $source->id)->delete();
        RevisionFragment::query()->delete();
        Connection::query()->where('source_id', $source->id)->delete();

        $this->artisan('living-course:backfill', ['--session' => $session->id])->expectsOutputToContain('1 new revision(s) recorded')->assertSuccessful();
        $this->artisan('living-course:backfill')->expectsOutputToContain('0 new revision(s) recorded')->assertSuccessful();

        $connection = $this->connectionOf($session);
        $this->assertSame(1, Revision::query()->findOrFail($connection->synced_revision_id)->number);
        $this->assertSame(Fragment::query()->where('source_id', $source->id)->count(), RevisionFragment::query()->where('revision_id', $connection->synced_revision_id)->count());
    }
}
