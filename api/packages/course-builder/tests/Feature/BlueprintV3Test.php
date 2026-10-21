<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Tests\TestCase;

/** Blueprint v3 (ADR 0160): lossless upgrade from v1 and v2, element origin and the citation rule, new lesson fields. */
class BlueprintV3Test extends TestCase
{
    /** A generated, valid document (v3 now). */
    private function document(): array
    {
        return $this->toApplied($this->author())->currentVersion->document;
    }

    private function schema(): SchemaRegistry
    {
        return app(SchemaRegistry::class);
    }

    /** A v2 document: what the builder wrote before v3. */
    private function asV2(array $doc): array
    {
        $doc['schemaVersion'] = 2;

        return $doc;
    }

    /** A v1 document: rich text lessons only, no self-checks or interactions. */
    private function asV1(array $doc): array
    {
        $doc['schemaVersion'] = 1;
        foreach ($doc['modules'] as $m => $module) {
            foreach ($module['lessons'] as $l => $lesson) {
                unset($doc['modules'][$m]['lessons'][$l]['selfChecks'], $doc['modules'][$m]['lessons'][$l]['interaction']);
                $doc['modules'][$m]['lessons'][$l]['contentType'] = 'richtext';
            }
        }

        return $doc;
    }

    public function testEveryOlderDocumentUpgradesToV3WithoutLosingAnything(): void
    {
        $v3 = $this->document();
        $this->assertSame(3, Blueprint::SCHEMA_VERSION);
        $this->assertSame([], $this->schema()->validateBlueprint($v3));

        foreach ([1 => $this->asV1($v3), 2 => $this->asV2($v3)] as $version => $old) {
            $this->assertSame([], $this->schema()->validateBlueprint($old), "the v{$version} document is valid against its own schema");
            $up = Blueprint::upgrade($old);
            $this->assertSame(3, $up['schemaVersion']);
            $this->assertSame([], $this->schema()->validateBlueprint($up), "v{$version} is a valid v3 document after the bump");
            $withoutVersion = fn (array $d) => array_diff_key($d, ['schemaVersion' => 1]);
            $this->assertSame($withoutVersion($old), $withoutVersion($up), 'only the version number changes');
            $this->assertSame([], BlueprintDiff::compare($old, $up), 'the upgrade is invisible in the diff');
            $this->assertSame([], BlueprintDiff::compare($up, Blueprint::upgrade($up)));
        }
        // upgrading twice, or a v3 document, changes nothing
        $this->assertSame($v3, Blueprint::upgrade($v3));
    }

    public function testOnlyAuthorElementsMayHaveNoCitations(): void
    {
        $doc = $this->document();
        $path = fn (array $d, ?string $origin, array $citations) => (function () use ($d, $origin, $citations) {
            $block = &$d['modules'][0]['lessons'][0]['blocks'][0];
            $block['citations'] = $citations;
            $origin === null ? null : $block['origin'] = $origin;

            return $d;
        })();

        $this->assertSame([], $this->schema()->validateBlueprint($path($doc, 'author', [])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($path($doc, null, [])), 'absent origin means ai');
        $this->assertNotSame([], $this->schema()->validateBlueprint($path($doc, 'ai', [])));
        $this->assertSame([], $this->schema()->validateBlueprint($path($doc, 'ai', $doc['modules'][0]['lessons'][0]['blocks'][0]['citations'])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($path($doc, 'robot', $doc['modules'][0]['lessons'][0]['blocks'][0]['citations'])));

        // the same rule for lessons, questions and interactions
        $d = $doc;
        $d['modules'][0]['lessons'][0]['origin'] = 'author';
        $d['modules'][0]['lessons'][0]['citations'] = [];
        $this->assertSame([], $this->schema()->validateBlueprint($d));
        $d['modules'][0]['lessons'][0]['origin'] = 'ai';
        $this->assertNotSame([], $this->schema()->validateBlueprint($d));
        $d = $doc;
        $quiz = &$d['modules'][0]['lessons'][0]['quiz'];
        if (is_array($quiz)) {
            $quiz['questions'][0]['citations'] = [];
            $this->assertNotSame([], $this->schema()->validateBlueprint($d));
            $quiz['questions'][0]['origin'] = 'author';
            $this->assertSame([], $this->schema()->validateBlueprint($d));
        }
    }

    public function testChecksReadTheOriginAndWarnAboutUncitedAuthorBlocks(): void
    {
        $session = $this->toApplied($this->author());
        $doc = $session->currentVersion->document;
        $known = array_fill_keys($session->fragmentIds(), true);
        $errors = fn (array $d) => Checks::blueprint($d, $known);
        $hard = fn (array $d) => array_values(array_filter($errors($d), fn ($e) => !str_starts_with($e, 'warning')));

        $this->assertSame([], $hard($doc));
        $doc['modules'][0]['lessons'][0]['blocks'][0]['citations'] = [];
        $this->assertStringContainsString('must cite at least 1 source fragment', implode(' ', $hard($doc)));
        $doc['modules'][0]['lessons'][0]['blocks'][0]['origin'] = 'author';
        $this->assertSame([], $hard($doc));
        $this->assertStringContainsString('written by the author, no sources', implode(' ', $errors($doc)));
        // an author block still may not cite what does not exist
        $doc['modules'][0]['lessons'][0]['blocks'][0]['citations'] = ['frg_aaaaaaaaaaaa'];
        $this->assertStringContainsString('not a fragment of the source', implode(' ', $hard($doc)));
    }

    public function testNewLessonFieldsOfV3(): void
    {
        $doc = $this->document();
        $asset = ['path' => 'pack.zip', 'sha256' => str_repeat('a', 64), 'bytes' => 100, 'mediaType' => 'application/zip'];
        $with = function (array $lesson) use ($doc) {
            $d = $doc;
            $d['modules'][0]['lessons'][0] = $lesson + $d['modules'][0]['lessons'][0];

            return $d;
        };

        $this->assertSame([], $this->schema()->validateBlueprint($with(['contentType' => 'scorm', 'asset' => $asset, 'assetRef' => ['entityType' => 'scorm_sco', 'entityId' => 5]])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($with(['contentType' => 'scorm'])), 'a SCORM lesson names its file');
        $this->assertNotSame([], $this->schema()->validateBlueprint($with(['contentType' => 'cmi5'])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($with(['contentType' => 'file'])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($with(['contentType' => 'video'])));
        $this->assertSame([], $this->schema()->validateBlueprint($with(['contentType' => 'video', 'video' => ['provider' => 'youtube', 'url' => 'https://youtu.be/x']])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($with(['contentType' => 'embed'])));
        $this->assertSame([], $this->schema()->validateBlueprint($with(['contentType' => 'embed', 'embed' => ['url' => 'https://example.org', 'title' => 'Demo']])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($with(['asset' => ['path' => 'x'] + $asset, 'contentType' => 'file', 'exercises' => [['id' => 'x']]])), 'exercises are reserved');
        $this->assertSame([], $this->schema()->validateBlueprint($with(['exercises' => []])));
        $this->assertSame([], $this->schema()->validateBlueprint($with(['key' => 'install-kubectl', 'meta' => ['createdBy' => 'git']])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($with(['key' => 'Not A Slug'])));
        $this->assertNotSame([], $this->schema()->validateBlueprint($with(['asset' => ['sha256' => 'short'] + $asset, 'contentType' => 'file'])));
    }

    public function testStructureChecksIds_Keys_AndFormatsThisServerCanApply(): void
    {
        $doc = $this->document();
        $this->assertSame([], Checks::structure($doc));

        $d = $doc;
        $d['modules'][0]['key'] = 'basics';
        $d['modules'][0]['lessons'][0]['key'] = 'intro';
        $this->assertSame([], Checks::structure($d));
        if (count($d['modules'][0]['lessons']) > 1) {
            $d['modules'][0]['lessons'][1]['key'] = 'intro';
            $this->assertStringContainsString('key intro is used by another lesson', implode(' ', Checks::structure($d)));
        }
        if (count($d['modules']) > 1) {
            $d['modules'][1]['key'] = 'basics';
            $this->assertStringContainsString('key basics is used by another module', implode(' ', Checks::structure($d)));
        }

        $d = $doc;
        $d['modules'][0]['lessons'][0]['id'] = 'NOT-AN-ID';
        $this->assertStringContainsString('26 lowercase letters and digits', implode(' ', Checks::structure($d)));
        $d = $doc;
        $d['course']['objectives'][0]['id'] = $d['modules'][0]['id'];
        $this->assertStringContainsString('used by more than one element', implode(' ', Checks::structure($d)));

        $d = $doc;
        $d['modules'][0]['lessons'][0]['contentType'] = 'video';
        $this->assertStringContainsString('cannot be applied by this server yet', implode(' ', Checks::structure($d)));
    }

    public function testThePublicSchemaFilesAgree(): void
    {
        $v3 = $this->schema()->get('course-blueprint/v3');
        $this->assertSame('https://ulams.dev/schemas/course-blueprint/v3.json', $v3['$id']);
        $this->assertSame(3, $v3['properties']['schemaVersion']['const']);
        $this->assertSame('https://ulams.dev/schemas/course-blueprint/v2.json', $this->schema()->get('course-blueprint/v2')['$id'], 'v2 is never edited in place');
    }
}
