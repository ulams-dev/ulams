<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Tests\TestCase;

class CitationIndexTest extends TestCase
{
    public function testSectionsListTheElementsThatCiteThem(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $doc = $session->currentVersion->document;

        $data = $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/citations")->assertOk()->json('data');

        $this->assertSame($session->current_version_id, $data['versionId']);
        $this->assertCount(1, $data['sources']);
        $source = $data['sources'][0];
        $this->assertSame(count($source['sections']), $source['total']);
        $cited = array_filter($source['sections'], fn ($s) => $s['citedByCount'] > 0);
        $this->assertNotEmpty($cited, 'generated blocks cite sections');
        $this->assertSame($source['total'] - count($cited), $source['uncovered']);

        // a block of the first lesson shows up under each section it cites, and the reverse map agrees
        $block = Blueprint::lessons($doc)->current()['lesson']['blocks'][0];
        $this->assertArrayHasKey($block['id'], $data['elements']);
        $this->assertEqualsCanonicalizing(array_values(array_unique($block['citations'])), $data['elements'][$block['id']]);
        foreach ($block['citations'] as $fragmentId) {
            $section = collect($source['sections'])->firstWhere('fragmentId', $fragmentId);
            $this->assertContains($block['id'], array_column($section['citedBy'], 'id'));
        }
    }

    public function testAnObjectiveJumpsToItsLesson(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $doc = $session->currentVersion->document;
        $lesson = Blueprint::lessons($doc)->current()['lesson'];

        $data = $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/citations")->json('data');
        $row = collect($data['sources'][0]['sections'])->flatMap(fn ($s) => $s['citedBy'])->firstWhere('id', $lesson['objectives'][0]['id']);

        $this->assertSame('objective', $row['type']);
        $this->assertSame($lesson['id'], $row['target']);
    }

    public function testAFreshSessionHasNoSources(): void
    {
        $author = $this->author();
        $session = $this->newSession($author);

        $data = $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/citations")->assertOk()->json('data');
        $this->assertSame([], $data['sources']);
        $this->assertNull($data['versionId']);
    }
}
