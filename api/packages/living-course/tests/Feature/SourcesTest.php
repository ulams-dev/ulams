<?php

namespace Ulams\LivingCourse\Tests\Feature;

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
}
