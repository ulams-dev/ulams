<?php

namespace Ulams\LiaScript\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\LiaScript\Models\LiaScriptDocument;
use Ulams\LiaScript\Tests\TestCase;

class LiaScriptApiTest extends TestCase
{
    use CreatesUsers;

    private const COURSE = <<<'MD'
<!--
author: ulams
language: en
-->

# Intro to Git

## Commits

A commit records a snapshot.

    [( )] A branch
    [(X)] A snapshot

![Diagram](img/diagram.png)
MD;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    public function testCreateFromMarkdownEditRestoreAndFetchEveryVersion(): void
    {
        $tutor = $this->makeInstructor();

        $created = $this->actingAs($tutor, 'api')->postJson('/api/admin/liascript', ['markdown' => self::COURSE])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Intro to Git')
            ->assertJsonPath('data.current_version', 1)
            ->json('data');

        $v2 = str_replace('A commit records a snapshot.', 'A commit records a snapshot of the tree.', self::COURSE);
        $this->actingAs($tutor, 'api')->postJson("/api/admin/liascript/{$created['id']}/versions", ['markdown' => $v2, 'change_note' => 'clarify'])
            ->assertCreated()
            ->assertJsonPath('data.current_version', 2);

        $this->actingAs($tutor, 'api')->get("/api/admin/liascript/{$created['id']}/source")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->assertHeader('X-LiaScript-Version', '2')
            ->assertSee('snapshot of the tree');
        $this->actingAs($tutor, 'api')->get("/api/admin/liascript/{$created['id']}/source?version=1")
            ->assertOk()
            ->assertDontSee('snapshot of the tree');

        $this->actingAs($tutor, 'api')->postJson("/api/admin/liascript/{$created['id']}/versions/1/restore")
            ->assertCreated()
            ->assertJsonPath('data.current_version', 3);
        $this->actingAs($tutor, 'api')->get("/api/admin/liascript/{$created['id']}/source")->assertDontSee('snapshot of the tree');

        $versions = $this->actingAs($tutor, 'api')->getJson("/api/admin/liascript/{$created['id']}/versions")->assertOk()->json('data');
        $this->assertSame([1, 2, 3], array_column($versions, 'version'));
        $this->assertSame('clarify', $versions[1]['change_note']);
        $this->assertSame(1, $versions[2]['restored_from']);

        $this->actingAs($tutor, 'api')->putJson("/api/admin/liascript/{$created['id']}", ['title' => 'Git basics'])
            ->assertOk()->assertJsonPath('data.title', 'Git basics')->assertJsonPath('data.current_version', 3);
        $this->actingAs($tutor, 'api')->getJson('/api/admin/liascript')->assertOk()->assertJsonPath('data.0.title', 'Git basics');
    }

    public function testUploadAZipWithMarkdownAndAssetsKeepsAssetsAcrossTextEdits(): void
    {
        $admin = $this->makeAdmin();
        $zip = $this->makeZip(['README.md' => self::COURSE, 'img/diagram.png' => 'PNGDATA']);

        $id = $this->actingAs($admin, 'api')->post('/api/admin/liascript', [
            'file' => new UploadedFile($zip, 'course.zip', null, null, true),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.assets', ['img/diagram.png'])->json('data.id');

        Storage::disk(config('filesystems.default'))->assertExists("liascript/{$id}/v1/img/diagram.png");
        Storage::disk(config('filesystems.default'))->assertMissing("liascript/{$id}/v1/README.md");

        $this->actingAs($admin, 'api')->postJson("/api/admin/liascript/{$id}/versions", ['markdown' => self::COURSE . "\n\nMore."])
            ->assertCreated()
            ->assertJsonPath('data.assets', ['img/diagram.png']);
    }

    public function testUploadAMarkdownFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lia-');
        file_put_contents($path, self::COURSE);

        $this->actingAs($this->makeAdmin(), 'api')->post('/api/admin/liascript', [
            'file' => new UploadedFile($path, 'course.md', null, null, true),
            'title' => 'Uploaded',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.title', 'Uploaded');
        @unlink($path);
    }

    public function testHostileOrInvalidUploadsAreRejectedAndLeaveNothingBehind(): void
    {
        $admin = $this->makeAdmin();

        $slip = $this->makeZip(['README.md' => self::COURSE, '../../escape.js' => 'x']);
        $this->actingAs($admin, 'api')->post('/api/admin/liascript', ['file' => new UploadedFile($slip, 'c.zip', null, null, true)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'leaves its folder']);

        $noMarkdown = $this->makeZip(['img/a.png' => 'x']);
        $this->actingAs($admin, 'api')->post('/api/admin/liascript', ['file' => new UploadedFile($noMarkdown, 'c.zip', null, null, true)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'README.md']);

        $this->actingAs($admin, 'api')->postJson('/api/admin/liascript', ['markdown' => '   '])->assertUnprocessable();
        $this->actingAs($admin, 'api')->postJson('/api/admin/liascript', ['markdown' => "bad \xC3\x28 utf8"])->assertUnprocessable();
        $this->actingAs($admin, 'api')->postJson('/api/admin/liascript', [])->assertUnprocessable();

        $this->assertSame(0, LiaScriptDocument::query()->count());
        $this->assertSame([], Storage::disk(config('filesystems.default'))->allFiles('liascript'));
    }

    public function testRemoteImportsAreFlagged(): void
    {
        $markdown = "<!--\nimport: https://raw.githubusercontent.com/someone/macros/main/README.md\n-->\n\n# With imports\n";

        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/liascript', ['markdown' => $markdown])
            ->assertCreated()
            ->assertJsonPath('data.warnings.0', fn ($w) => str_contains($w, 'raw.githubusercontent.com'));
    }

    public function testDeleteRemovesVersionsAndAssets(): void
    {
        $admin = $this->makeAdmin();
        $zip = $this->makeZip(['README.md' => self::COURSE, 'img/diagram.png' => 'PNGDATA']);
        $id = $this->actingAs($admin, 'api')->post('/api/admin/liascript', ['file' => new UploadedFile($zip, 'course.zip', null, null, true)], ['Accept' => 'application/json'])
            ->json('data.id');

        $this->actingAs($admin, 'api')->deleteJson("/api/admin/liascript/{$id}")->assertOk();

        $this->assertNull(LiaScriptDocument::query()->find($id));
        $this->assertDatabaseMissing('liascript_versions', ['liascript_document_id' => $id]);
        $this->assertSame([], Storage::disk(config('filesystems.default'))->allFiles("liascript/{$id}"));
    }

    public function testEveryEndpointNeedsTheLiaScriptManagePermission(): void
    {
        $document = $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/liascript', ['markdown' => self::COURSE])->json('data.id');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/admin/liascript')->assertUnauthorized();
        $student = $this->makeStudent();
        foreach ([
            ['GET', '/api/admin/liascript'],
            ['POST', '/api/admin/liascript'],
            ['GET', "/api/admin/liascript/{$document}"],
            ['PUT', "/api/admin/liascript/{$document}"],
            ['DELETE', "/api/admin/liascript/{$document}"],
            ['GET', "/api/admin/liascript/{$document}/source"],
            ['GET', "/api/admin/liascript/{$document}/versions"],
            ['POST', "/api/admin/liascript/{$document}/versions"],
            ['POST', "/api/admin/liascript/{$document}/versions/1/restore"],
        ] as [$method, $uri]) {
            $this->actingAs($student, 'api')->json($method, $uri, ['markdown' => 'x', 'title' => 'x'])->assertForbidden();
        }
        $this->assertNotNull(LiaScriptDocument::query()->find($document));
    }

    public function testUnknownDocumentsAndVersions(): void
    {
        $admin = $this->makeAdmin();
        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/liascript', ['markdown' => self::COURSE])->json('data.id');

        $this->actingAs($admin, 'api')->getJson('/api/admin/liascript/999999999')->assertNotFound();
        $this->actingAs($admin, 'api')->getJson("/api/admin/liascript/{$id}/source?version=7")->assertUnprocessable();
        $this->actingAs($admin, 'api')->postJson("/api/admin/liascript/{$id}/versions/7/restore")->assertUnprocessable();
    }
}
