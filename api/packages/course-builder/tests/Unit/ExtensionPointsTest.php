<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\RunService;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;

/** The small hooks Phase 3 (Living Course) adds to the builder; Phase 2 behaviour stays the same. */
class ExtensionPointsTest extends TestCase
{
    public function testFragmentLabelPrefixesTheFileOfAMultiFileSource(): void
    {
        $f = new Fragment(['heading_path' => ['docs/guide/rebase.md', 'Rebasing'], 'section' => '2.3']);
        $this->assertSame('§2.3 Rebasing', $f->label());
        $f->file_path = 'docs/guide/rebase.md';
        $this->assertSame('rebase.md §2.3 Rebasing', $f->label());
    }

    public function testUpdateVersionsAreContentVersionsWithSourceRevisions(): void
    {
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        $versions = app(VersionService::class);
        $doc = Blueprint::empty('Course', 'en');
        $v1 = $versions->create($session, $doc, 'content', 'ai', Version::APPROVED, null, 'first', null, [], 1);
        $versions->setCurrent($session, $v1);
        $doc['course']['title'] = 'Course v2';
        $v2 = $versions->create($session, $doc, 'update', 'ai', Version::APPROVED, $v1, 'Source update r1 to r2', null, [], 1, ['src1' => 'rev2']);
        $versions->setCurrent($session, $v2);

        $this->assertContains('update', VersionService::CONTENT_KINDS);
        $this->assertSame(['src1' => 'rev2'], $v2->refresh()->source_revisions);
        $this->assertNull($v1->refresh()->source_revisions);
        $this->assertSame($v1->id, $versions->undoTarget($session->refresh())->id);
        $this->assertSame(['src1' => 'rev2'], collect($versions->history($session))->firstWhere('number', 2)['sourceRevisions']);
    }

    public function testRegisteredRunHandlersExecuteRunsAndCanKeepThemOpen(): void
    {
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        $seen = [];
        RunService::extend('test.close', function (Run $run, Session $s) use (&$seen) {
            $seen[] = [$run->kind, $s->id];
        });
        RunService::extend('test.open', fn (Run $run) => false);

        $closing = Run::query()->create(['session_id' => $session->id, 'kind' => 'sync', 'status' => 'queued', 'input' => ['handler' => 'test.close']]);
        app(RunService::class)->execute($closing);
        $this->assertSame('finished', $closing->refresh()->status);
        $this->assertSame([['sync', $session->id]], $seen);

        $open = Run::query()->create(['session_id' => $session->id, 'kind' => 'sync', 'status' => 'queued', 'input' => ['handler' => 'test.open']]);
        app(RunService::class)->execute($open);
        $this->assertSame('running', $open->refresh()->status);
        app(RunService::class)->finish($open);
        $this->assertSame('finished', $open->refresh()->status);
    }
}
