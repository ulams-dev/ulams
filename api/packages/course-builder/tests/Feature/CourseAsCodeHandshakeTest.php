<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * The server side of the handshake with the `ulams` CLI. `fixtures/handshake/cli-pushes.json` holds a real export and the
 * documents the CLI builds from a course folder written from it (front/cli/tests/course/handshake.test.ts keeps the file in
 * step with the CLI), together with the studio edits they are pushed against. They are replayed here against the real endpoints.
 *
 * With `ULAMS_DUMP_HANDSHAKE=<file>` the answers are written to that file: the CLI's `real-merge.test.ts` replays them.
 */
class CourseAsCodeHandshakeTest extends TestCase
{
    private const P = '/api/admin/course-as-code';

    /** @var array<string,mixed> */
    private static array $dump = [];

    private function pushes(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../fixtures/handshake/cli-pushes.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** A session whose current version is the real export, with the fragments its citations name. */
    private function sessionFor(array $export): array
    {
        config(['ai.driver' => 'disabled']);
        $author = $this->author();
        $session = Session::query()->create(['author_id' => $author->getKey()]);
        $source = Source::query()->create(['session_id' => $session->id, 'original_name' => 'coffee.md', 'mime' => 'text/markdown', 'size' => 1, 'sha256' => str_repeat('a', 64), 'path' => 'x', 'status' => 'ready']);
        $cited = [];
        array_walk_recursive($export['document'], function ($value, $key) use (&$cited) {
            if (is_string($value) && preg_match('/^frg_[a-z2-7]{12}$/', $value)) {
                $cited[$value] = true;
            }
        });
        Fragment::query()->whereIn('id', array_keys($cited))->delete(); // fragment ids are global: one source owns them at a time
        foreach (array_keys($cited) as $i => $id) {
            Fragment::query()->create(['id' => $id, 'source_id' => $source->id, 'ordinal' => $i, 'heading_path' => ['Coffee'], 'section' => '1', 'level' => 1, 'text' => 'text', 'char_start' => 0, 'char_end' => 4, 'token_estimate' => 1, 'content_hash' => str_repeat('b', 64)]);
        }
        $versions = app(VersionService::class);
        $v1 = $versions->create($session, $export['document'], 'content', 'ai', Version::APPROVED, null, 'generated', null, [], $author->getKey());
        $versions->setCurrent($session, $v1);

        return [$session->refresh(), $v1, $author];
    }

    public function testTheDocumentsTheCliBuildsAreAcceptedAndMergedByTheRealEndpoints(): void
    {
        $fixture = $this->pushes();
        $out = [];
        foreach ($fixture['scenarios'] as $scenario) {
            [$session, $v1, $author] = $this->sessionFor($fixture['export']);
            $studio = null;
            if ($scenario['studio'] !== null) {
                $versions = app(VersionService::class);
                $studio = $versions->create($session, $scenario['studio'], 'author', 'author', Version::APPROVED, $v1, 'studio edit', null, [], $author->getKey());
                $versions->setCurrent($session, $studio);
            }
            $count = Version::query()->where('session_id', $session->id)->count();
            $res = $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $scenario['pushed'], 'baseVersionId' => $v1->id, 'message' => $scenario['name']]);
            $name = $scenario['name'];

            switch ($name) {
                case 'plain':
                    $res->assertCreated()->assertJsonPath('data.merged', null)->assertJsonPath('data.version.parentId', $v1->id);
                    $this->assertEquals(['changed' => 1, 'added' => 0, 'removed' => 0], $res->json('data.counts'));
                    break;
                case 'disjoint':
                    $res->assertCreated()->assertJsonPath('data.version.parentId', $studio->id)->assertJsonPath('data.merged.baseVersionId', $v1->id)->assertJsonPath('data.merged.currentVersionId', $studio->id);
                    $this->assertSame([$scenario['blockId']], array_column($res->json('data.merged.fromPush'), 'id'));
                    $this->assertSame('changed', $res->json('data.merged.fromServer.0.kind'));
                    $this->assertCount(1, $res->json('data.merged.fromServer'));
                    $this->assertEquals(['changed' => 1, 'added' => 0, 'removed' => 0], $res->json('data.counts'));
                    $merged = Version::query()->findOrFail($res->json('data.version.id'))->document;
                    $this->assertSame('Measuring your coffee, retitled in the studio', $merged['modules'][1]['lessons'][0]['title']);
                    $this->assertStringContainsString('An added sentence.', $merged['modules'][0]['lessons'][0]['blocks'][0]['markdown']);
                    break;
                case 'conflict':
                    $res->assertStatus(409)->assertJsonPath('code', 'BASE_OUTDATED');
                    $this->assertSame([$scenario['blockId']], array_column($res->json('data.conflicts'), 'elementId'));
                    $this->assertSame('edit-edit', $res->json('data.conflicts.0.kind'));
                    $this->assertSame('The author rewrote this paragraph.', $res->json('data.conflicts.0.fields.0.ours'));
                    $this->assertSame('The studio rewrote this paragraph.', $res->json('data.conflicts.0.fields.0.theirs'));
                    $this->assertSame($count, Version::query()->where('session_id', $session->id)->count());
                    break;
                case 'delete against edit':
                    $res->assertStatus(409)->assertJsonPath('code', 'BASE_OUTDATED');
                    $this->assertSame([$scenario['blockId']], array_column($res->json('data.conflicts'), 'elementId'));
                    $this->assertSame('removed-ours', $res->json('data.conflicts.0.kind'));
                    break;
                default:
                    $this->fail("unknown scenario {$name}");
            }

            $out[$name] = ['status' => $res->getStatusCode(), 'response' => $res->json(), 'baseVersionId' => $v1->id, 'studioVersionId' => $studio?->id, 'sessionId' => $session->id];
            self::$dump['version'] ??= $this->actingAs($author, 'api')->getJson(self::P . "/versions/{$v1->id}")->assertOk()->json();
        }
        self::$dump['pushes'] = $out;
        $this->dumpIfAsked();
    }

    /** The state after admin edits, from the generated course (it has real entities), for the CLI's `absorb-drift` replay. */
    public function testAbsorbResponseForTheCliReplay(): void
    {
        $session = $this->toApplied($this->author());
        config(['ai.driver' => 'disabled']);
        $doc = $session->currentVersion->document;
        $entry = \Ulams\CourseBuilder\Models\EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $doc['course']['id'])->where('entity_type', 'course')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('courses')->where('id', $entry->entity_id)->update(['title' => 'A title from the admin', 'updated_at' => now()->addMinutes(5)]);
        $export = $this->actingAs($session->author, 'api')->getJson(self::P . "/courses/{$session->course_id}/blueprint")->assertOk()->json();
        $this->assertSame(['course:' . $doc['course']['id']], array_column($export['data']['edits']['absorbable'], 'element'));
        $res = $this->actingAs($session->author, 'api')->postJson(self::P . "/sessions/{$session->id}/drift")->assertCreated();
        self::$dump['absorb'] = ['export' => $export, 'response' => $res->json()];
        $this->dumpIfAsked();
    }

    private function dumpIfAsked(): void
    {
        $file = getenv('ULAMS_DUMP_HANDSHAKE');
        if ($file !== false && $file !== '') {
            $existing = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            file_put_contents($file, json_encode(self::$dump + (array) $existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        }
    }
}
