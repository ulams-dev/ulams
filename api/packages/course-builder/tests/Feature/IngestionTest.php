<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Ulams\CourseBuilder\Ingestion\FragmentId;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Tests\Support\DocumentFixtures;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\Tests\ZipFixtures;

class IngestionTest extends TestCase
{
    use ZipFixtures;

    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $file) {
            @unlink($file);
        }
        $this->cleanZipFixtures();
        parent::tearDown();
    }

    private function tmp(string $suffix): string
    {
        return $this->tmp[] = tempnam(sys_get_temp_dir(), 'cbing') . $suffix;
    }

    private function ingestText(Session $session, string $markdown, string $name = 'doc.md'): Source
    {
        $path = $this->tmp('.md');
        file_put_contents($path, $markdown);
        $ingestor = app(SourceIngestor::class);
        $source = $ingestor->store($session, new UploadedFile($path, $name, null, null, true));
        $ingestor->ingest($source);

        return $source->refresh();
    }

    public function testMarkdownBecomesFragmentsWithSectionLabels(): void
    {
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        $source = $this->ingestText($session, (string) file_get_contents(__DIR__ . '/../../resources/fixtures/coffee-brewing.md'));

        $this->assertSame('Coffee Brewing Fundamentals', $source->metadata['title']);
        $this->assertSame('en', $source->metadata['language']);
        $fragments = $source->fragments()->get();
        $this->assertGreaterThan(10, $fragments->count());
        foreach ($fragments as $f) {
            $this->assertTrue(FragmentId::isValid($f->id), $f->id);
            $this->assertSame(hash('sha256', $f->text), $f->content_hash);
        }
        $labels = $fragments->map(fn (Fragment $f) => $f->label())->all();
        $this->assertContains('§2.1 The brew ratio', $labels);
        $this->assertContains('Introduction', $labels);
        $this->assertSame(['Ratio and dose', 'Calculating a dose'], $fragments->first(fn ($f) => $f->label() === '§2.2 Calculating a dose')->heading_path);
    }

    public function testFragmentIdsSurviveTypoFixesButNotMoves(): void
    {
        $author = $this->author();
        $md = "# T\n\n## Alpha\n\nAlpha text about brewing.\n\n## Beta\n\nBeta text about grinding.\n";
        $a = $this->ingestText(Session::query()->create(['author_id' => $author->getKey()]), $md);
        $ids = $a->fragments()->pluck('id', 'text')->all();

        // same source key, typo fixed: the ids stay, the content hash changes
        $typo = FragmentId::make($a->id, ['Beta'], 0);
        $this->assertSame($ids['Beta text about grinding.'], $typo);
        // moved under another heading: a different id
        $this->assertNotSame($typo, FragmentId::make($a->id, ['Alpha', 'Beta'], 0));
        $this->assertNotSame($typo, FragmentId::make($a->id, ['Beta'], 1));
    }

    public function testCodeBlocksAreNeverSplitAndHeadingsInsideThemIgnored(): void
    {
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        $code = "```bash\n# not a heading\n" . str_repeat("echo line\n", 400) . "```";
        $source = $this->ingestText($session, "# Doc\n\n## Shell\n\nIntro paragraph.\n\n{$code}\n\n## Next\n\nMore.\n");

        $paths = $source->fragments()->get()->map(fn ($f) => implode('/', $f->heading_path))->unique()->values()->all();
        $this->assertSame(['Shell', 'Next'], $paths);
        $this->assertTrue($source->fragments()->get()->contains(fn ($f) => str_contains($f->text, "# not a heading") && str_contains($f->text, '```bash') && str_ends_with(trim($f->text), '```')));
    }

    public function testPdfWithPagesAndNumberedHeadings(): void
    {
        $path = DocumentFixtures::pdf($this->tmp('.pdf'), [
            ['1 Extraction', 'Brewing coffee is extraction: hot water dissolves soluble compounds from ground coffee.', 'Acids dissolve first, then sugars, and the bitter compounds come last.'],
            ['2 Ratio', 'The brew ratio is the weight of coffee compared to the weight of water.', '2.1 Calculating A Dose', 'For 20 g of coffee at 1:16 you need 320 g of water.'],
        ], 'Coffee Handbook');
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        $ingestor = app(SourceIngestor::class);
        $source = $ingestor->store($session, new UploadedFile($path, 'handbook.pdf', null, null, true));
        $ingestor->ingest($source);
        $source->refresh();

        $this->assertSame('pdf', $source->kind());
        $this->assertSame(2, $source->metadata['pages']);
        $fragments = $source->fragments()->get();
        $ratio = $fragments->first(fn ($f) => str_contains($f->text, '320 g'));
        $this->assertNotNull($ratio);
        $this->assertSame(2, $ratio->page_start);
        $this->assertSame(['Ratio', 'Calculating A Dose'], $ratio->heading_path);
        $this->assertSame(1, $fragments->first(fn ($f) => str_contains($f->text, 'Acids dissolve'))->page_start);
    }

    public function testDocxHeadingsListsTablesCodeAndLinks(): void
    {
        $path = DocumentFixtures::docx($this->tmp('.docx'), [
            ['h1', 'Git Basics'],
            ['h2', 'Committing'],
            ['p', 'Stage files, then commit them.'],
            ['li', 'git add stages a file'],
            ['li', 'git commit records the snapshot'],
            ['code', 'git commit -m "Add intro"'],
            ['code', 'git log --oneline'],
            ['table', 'Command|Purpose;status|show changes'],
            ['link', 'the Git book'],
            ['h2', 'Branches'],
            ['bold', 'Important'],
        ], 'Git Basics');
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        $ingestor = app(SourceIngestor::class);
        $source = $ingestor->store($session, new UploadedFile($path, 'git.docx', null, null, true));
        $doc = $ingestor->ingest($source);

        $md = $doc->markdown;
        $this->assertStringContainsString("- git add stages a file\n- git commit records the snapshot", $md);
        $this->assertStringContainsString("```\ngit commit -m \"Add intro\"\ngit log --oneline\n```", $md);
        $this->assertStringContainsString('| Command | Purpose |', $md);
        $this->assertStringContainsString('| --- | --- |', $md);
        $this->assertStringContainsString('[the Git book](https://example.org/docs)', $md);
        $this->assertStringContainsString('**Important** follows.', $md);
        $this->assertSame('Git Basics', $source->refresh()->metadata['title']);
        $this->assertSame(['Committing', 'Branches'], $source->fragments()->get()->map(fn ($f) => $f->heading_path[0] ?? '')->unique()->values()->all());
    }

    public function testDocxZipBombIsRejected(): void
    {
        $bomb = $this->makeZipBomb(8);
        $docx = $this->tmp('.docx');
        copy($bomb, $docx);
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        config(['course_builder.limits.docx_uncompressed_bytes' => 1024 * 1024]);
        $ingestor = app(SourceIngestor::class);
        $source = $ingestor->store($session, new UploadedFile($docx, 'bomb.docx', null, null, true));

        $this->expectException(\RuntimeException::class);
        $ingestor->ingest($source);
    }

    public function testExtensionMustMatchTheSniffedType(): void
    {
        $path = $this->tmp('.pdf');
        file_put_contents($path, "# Not a PDF\n\nJust text.\n");
        $author = $this->author();
        $session = $this->newSession($author);

        $this->actingAs($author, 'api')
            ->post("/api/admin/course-builder/sessions/{$session->id}/sources", ['file' => new UploadedFile($path, 'fake.pdf', null, null, true)])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'wrong_type');
        $this->assertSame(0, $session->sources()->count());
    }

    public function testOversizedUploadsAreRejected(): void
    {
        config(['ulams_uploads.policies.course-builder-source.max_size' => 100]);
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        $path = $this->tmp('.md');
        file_put_contents($path, str_repeat('word ', 100));

        $this->expectException(UploadRejected::class);
        app(SourceIngestor::class)->store($session, new UploadedFile($path, 'big.md', null, null, true));
    }

    public function testSameFileIsStoredOnce(): void
    {
        $session = Session::query()->create(['author_id' => $this->author()->getKey()]);
        $a = $this->ingestText($session, "# A\n\nText one here.\n");
        $path = $this->tmp('.md');
        file_put_contents($path, "# A\n\nText one here.\n");
        $b = app(SourceIngestor::class)->store($session, new UploadedFile($path, 'again.md', null, null, true));
        $this->assertSame($a->id, $b->id);
    }

    public function testSourceTokenLimitStopsBeforeAnyGeneration(): void
    {
        config(['course_builder.limits.source_tokens' => 50]);
        $author = $this->author();
        $session = $this->uploaded($author);

        $source = $session->sources()->first();
        $this->assertSame('failed', $source->status);
        $this->assertStringContainsString('limit', $source->error);
        $this->assertNull($session->stateValue('interview'));
        $this->assertSame(0, \Ulams\Ai\Models\AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->count());
    }
}
