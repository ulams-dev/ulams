<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\FragmentChange;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\TestCase;

class ChangeDetectionTest extends TestCase
{
    private function upload($author, $session, string $fixture): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture($fixture)]);
    }

    public function testVersionTwoOfTheCoffeeHandbookIsDiffedAgainstTheSyncedRevision(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $response = $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $two = Revision::query()->findOrFail($response->json('data.revision.id'));

        $this->assertSame('ingested', $two->status);
        $counts = $two->metadata['counts'];
        $this->assertSame(1, $counts['removed'], 'the "Water quality" section is gone');
        $this->assertSame(1, $counts['added'], 'the "Storing beans" section is new');
        $this->assertSame(4, $counts['changed'], 'ratio, dose, temperature and the bloom wording');
        $this->assertSame(3, $counts['substantive']);
        $this->assertSame(1, $counts['minor']);
        $this->assertSame(0, $counts['moved']);
        $this->assertSame(1, $two->metadata['against']);

        $changes = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/revisions/{$two->id}/changes")->assertOk()->json('data');
        $this->assertSame(1, $changes['from']['number']);
        $this->assertSame(2, $changes['to']['number']);
        $this->assertSame($counts, $changes['counts']);
        $byLabel = collect($changes['changes'])->keyBy(fn ($c) => ($c['new']['label'] ?? $c['old']['label']));
        $ratio = $byLabel['§2.1 The brew ratio'];
        $this->assertSame('changed', $ratio['kind']);
        $this->assertSame('substantive', $ratio['magnitude']);
        $this->assertContains('number', $ratio['signals']);
        $this->assertStringContainsString('1:16', $ratio['old']['text']);
        $this->assertStringContainsString('1:15', $ratio['new']['text']);
        $this->assertContains(['-', ' 1:16'], $ratio['wordDiff']);
        $this->assertContains(['+', ' 1:15'], $ratio['wordDiff']);
        $this->assertSame('removed', $byLabel['§4.2 Water quality']['kind']);
        $this->assertNull($byLabel['§4.2 Water quality']['new']);
        $this->assertSame('added', $byLabel['§7.1 Storing beans']['kind']);
        $this->assertSame('minor', $byLabel['§5.1 The bloom']['magnitude']);

        // stored rows and the on-the-fly comparison agree
        $this->assertSame(6, FragmentChange::query()->where('to_revision_id', $two->id)->count());
        $live = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/revisions/{$two->id}/changes?against={$changes['from']['id']}")->assertOk()->json('data');
        $this->assertSame($counts, $live['counts']);
        $this->assertCount(6, $live['changes']);

        $audit = AuditEntry::query()->where('action', 'revision.detected')->where('revision_id', $two->id)->firstOrFail();
        $this->assertSame($counts, $audit->data['counts']);
        $this->assertSame((int) $author->getKey(), $audit->actor_id);
    }

    public function testChangesAreInDocumentOrder(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $two = $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated()->json('data.revision.id');

        $labels = collect($this->actingAs($author, 'api')->getJson("/api/admin/living-course/revisions/{$two}/changes")->json('data.changes'))
            ->map(fn ($c) => $c['new']['label'] ?? $c['old']['label'])->all();

        $this->assertSame(['§2.1 The brew ratio', '§2.2 Calculating a dose', '§4.1 Temperature', '§4.2 Water quality', '§5.1 The bloom', '§7.1 Storing beans'], $labels);
    }

    public function testAWhitespaceOnlyUploadIsUnchangedAndKeepsTheSyncedRevision(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $md = (string) file_get_contents(__DIR__ . '/../../resources/fixtures/coffee-brewing.v1.md');
        $path = tempnam(sys_get_temp_dir(), 'lcws') . '.md';
        file_put_contents($path, str_replace(["\n\n", ' the '], ["\n\n\n", '  the '], $md));

        $response = $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => new UploadedFile($path, 'spaced.md', null, null, true)])->assertCreated();

        $this->assertTrue($response->json('data.unchanged'));
        $this->assertSame('unchanged', $response->json('data.revision.status'));
        $connection = $this->connectionOf($session);
        $this->assertNotSame($connection->synced_revision_id, $connection->latest_revision_id);
        $this->assertSame(0, FragmentChange::query()->where('to_revision_id', $connection->latest_revision_id)->count());
        @unlink($path);
    }

    public function testAChangeOutsideEveryFragmentIsAcceptedSilentlyAndPromoted(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $md = (string) file_get_contents(__DIR__ . '/../../resources/fixtures/coffee-brewing.v1.md');
        $path = tempnam(sys_get_temp_dir(), 'lcco') . '.md';
        // the document title is not part of any fragment: the text differs, no fragment does
        file_put_contents($path, str_replace('# Coffee Brewing Fundamentals', '# Coffee Brewing Essentials', $md));
        $source = $this->sourceOf($session);
        $idsBefore = Fragment::query()->where('source_id', $source->id)->pluck('id')->all();

        $response = $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$source->id}/revisions", ['file' => new UploadedFile($path, 'v1-retitled.md', null, null, true)])->assertCreated();

        $this->assertSame('no_impact', $response->json('data.revision.status'));
        $connection = $this->connectionOf($session);
        $this->assertSame($response->json('data.revision.id'), $connection->synced_revision_id, 'the synced pointer advanced by itself');
        $this->assertSame($idsBefore, Fragment::query()->where('source_id', $source->id)->orderBy('ordinal')->pluck('id')->all());
        $this->assertSame('Coffee Brewing Essentials', $source->refresh()->metadata['title']);
        $actions = AuditEntry::query()->where('revision_id', $connection->synced_revision_id)->orderBy('id')->pluck('action')->all();
        $this->assertSame(['revision.no_impact', 'revision.promoted'], $actions);
        @unlink($path);
    }

    public function testPromotingARevisionMakesItsFragmentsTheLiveOnes(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $source = $this->sourceOf($session);
        $two = $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated()->json('data.revision.id');

        app(\Ulams\LivingCourse\Services\RevisionService::class)->promote(Revision::query()->findOrFail($two), (int) $author->getKey());

        $live = Fragment::query()->where('source_id', $source->id)->orderBy('ordinal')->get();
        $this->assertSame(Revision::query()->findOrFail($two)->fragment_count, $live->count());
        $this->assertStringContainsString('1:15', $live->first(fn ($f) => $f->label() === '§2.1 The brew ratio')->text);
        $this->assertNull($live->first(fn ($f) => $f->label() === '§4.2 Water quality'));
        $this->assertNotNull($live->first(fn ($f) => $f->label() === '§7.1 Storing beans'));
        $source->refresh();
        $this->assertSame('ready', $source->status);
        $this->assertSame($two, $this->connectionOf($session)->synced_revision_id);
        $this->assertSame(hash('sha256', (string) file_get_contents(__DIR__ . '/../../resources/fixtures/coffee-brewing.v2.md')), $source->sha256);
    }

    public function testChangesEndpointIsGuardedLikeTheRest(): void
    {
        $owner = $this->author();
        $session = $this->sessionWithSource($owner);
        $two = $this->upload($owner, $session, 'coffee-brewing.v2.md')->assertCreated()->json('data.revision.id');
        $url = "/api/admin/living-course/revisions/{$two}/changes";

        $this->actingAs($owner, 'api')->getJson($url)->assertOk();
        $this->actingAs($this->admin(), 'api')->getJson($url)->assertOk();
        $this->actingAs($this->tutor(), 'api')->getJson($url)->assertStatus(403);
        $this->actingAs($this->student(), 'api')->getJson($url)->assertStatus(403);
        $this->actingAs($owner, 'api')->getJson('/api/admin/living-course/revisions/01aaaaaaaaaaaaaaaaaaaaaaaa/changes')->assertStatus(404);
        $this->actingAs($owner, 'api')->getJson($url . '?against=01aaaaaaaaaaaaaaaaaaaaaaaa')->assertStatus(404);
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertStatus(401);
    }
}
