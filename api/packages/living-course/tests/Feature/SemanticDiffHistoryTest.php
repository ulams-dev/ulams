<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\LivingCourse\Models\FragmentChange;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;
use Ulams\LivingCourse\Tests\TestCase;

/**
 * ADR 0192, CV-2: with Living Course installed the compare API matches a citation that moved to the fragment it became in
 * a newer source revision (ADR 0031) and shows the passage as it was in each revision (the builder's own test of the API
 * is CompareApiTest, with a fake source history).
 */
class SemanticDiffHistoryTest extends TestCase
{
    private function fragmentOf(Revision $revision, string $heading): RevisionFragment
    {
        return RevisionFragment::query()->where('revision_id', $revision->id)->get()->first(fn (RevisionFragment $f) => in_array($heading, (array) $f->heading_path, true))
            ?? $this->fail("No fragment '{$heading}' in revision {$revision->number}.");
    }

    public function testACitationThatMovedToAnotherFragmentIsRepointedWithBothPassages(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $source = $this->sourceOf($session);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$source->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
        $one = Revision::query()->where('source_id', $source->id)->where('number', 1)->firstOrFail();
        $two = Revision::query()->where('source_id', $source->id)->where('number', 2)->firstOrFail();
        $old = $this->fragmentOf($one, 'Water quality');
        $new = $this->fragmentOf($two, 'Storing beans');
        FragmentChange::query()->create(['from_revision_id' => $one->id, 'to_revision_id' => $two->id, 'kind' => 'moved', 'old_fragment_id' => $old->fragment_id, 'new_fragment_id' => $new->fragment_id, 'magnitude' => 'minor', 'similarity' => 0.5]);

        $versions = app(VersionService::class);
        $parent = $session->refresh()->currentVersion;
        $before = $parent->document;
        $before['modules'][0]['lessons'][0]['citations'] = [$old->fragment_id];
        $a = $versions->create($session, $before, 'author', 'author', Version::APPROVED, $parent, 'cites the old passage', null, [], $author->getKey(), [$source->id => $one->id]);
        $after = $before;
        $after['modules'][0]['lessons'][0]['citations'] = [$new->fragment_id];
        $b = $versions->create($session, $after, 'update', 'ai', Version::APPROVED, $a, 'cites the moved passage', null, [], $author->getKey(), [$source->id => $two->id]);

        $data = $this->actingAs($author, 'api')->getJson("/api/admin/course-versions/courses/{$session->course_id}/compare?from={$a->id}&to={$b->id}")->assertOk()->json('data');

        $lesson = collect($data['changes'])->firstWhere('type', 'lesson');
        $this->assertSame([['from' => $old->fragment_id, 'to' => $new->fragment_id]], $lesson['citations']['repointed']);
        $this->assertSame([[], []], [$lesson['citations']['added'], $lesson['citations']['removed']]);
        $this->assertSame(1, $data['summary']['citations']['repointed']);
        $this->assertSame(['id' => $one->id, 'number' => 1], $data['fragments'][$old->fragment_id]['sourceRevision']);
        $this->assertSame(['id' => $two->id, 'number' => 2], $data['fragments'][$new->fragment_id]['sourceRevision']);
        $this->assertSame($old->text === '' ? '' : mb_substr($old->text, 0, 600), $data['fragments'][$old->fragment_id]['text']);
        $this->assertStringContainsString('Water quality', $data['fragments'][$old->fragment_id]['label']);
        $this->assertStringContainsString('Storing beans', $data['fragments'][$new->fragment_id]['label']);

        // without a move recorded between the two ids the same pair is a plain removed and added citation
        FragmentChange::query()->where('old_fragment_id', $old->fragment_id)->delete();
        Cache::flush();
        $plain = $this->actingAs($author, 'api')->getJson("/api/admin/course-versions/courses/{$session->course_id}/compare?from={$a->id}&to={$b->id}")->assertOk()->json('data');
        $lesson = collect($plain['changes'])->firstWhere('type', 'lesson');
        $this->assertSame([[$new->fragment_id], [$old->fragment_id], []], [$lesson['citations']['added'], $lesson['citations']['removed'], $lesson['citations']['repointed']]);
    }
}
