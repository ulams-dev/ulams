<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\ElementStatus;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\TestCase;

/** Impact analysis and the deterministic proposal on the coffee handbook v1 to v2 (plan 7, 13.1). */
class ImpactAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // these tests look at the deterministic proposal: the analysis waits for the author
        config(['living_course.cost.auto_analyse_usd' => 0]);
    }

    private function upload($author, Session $session, string|UploadedFile $file): TestResponse
    {
        $file = is_string($file) ? $this->lcFixture($file) : $file;

        return $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $file]);
    }

    private function markdown(string $replace, string $with, string $fixture = 'coffee-brewing.v1.md'): UploadedFile
    {
        $md = str_replace($replace, $with, (string) file_get_contents(__DIR__ . '/../../resources/fixtures/' . $fixture));
        $path = tempnam(sys_get_temp_dir(), 'lcmd') . '.md';
        file_put_contents($path, $md);

        return new UploadedFile($path, 'edited.md', null, null, true);
    }

    private function disableAi(): void
    {
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(LlmClient::class);
        $this->app->forgetInstance(LlmDriver::class);
        $this->app->forgetInstance(\Ulams\LivingCourse\Services\ProposalService::class);
    }

    public function testVersionTwoMarksTheLessonsThatCiteChangedSections(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $doc = $session->currentVersion->document;

        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();

        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $this->assertSame(1, $proposal->number);
        $this->assertSame('awaiting_analysis', $proposal->status, 'with the model enabled the AI analysis is the next step');
        $this->assertSame($session->current_version_id, $proposal->base_version_id);

        $items = ProposalItem::query()->where('proposal_id', $proposal->id)->get();
        $manual = $items->where('kind', 'manual');
        // ratio (2.1), dose (2.2), temperature (4.1), water quality removed (4.2): objective, 2 blocks and 2 questions each; two course objectives
        $this->assertCount(22, $manual);
        $byLabel = $manual->groupBy(fn ($i) => explode(' › ', (string) $i->label)[0]);
        $this->assertEqualsCanonicalizing(['Lesson 2.1', 'Lesson 2.2', 'Lesson 4.1', 'Lesson 4.2', 'Course objective 2', 'Course objective 4'], $byLabel->keys()->all());
        $this->assertCount(5, $byLabel['Lesson 2.1']);
        $this->assertSame(['block' => 2, 'objective' => 1, 'question' => 2], $byLabel['Lesson 2.1']->countBy('element_type')->sortKeys()->all());

        // quiz questions whose answer may now be wrong: substantive change or removed section
        $checks = $manual->where('element_type', 'question');
        $this->assertCount(8, $checks);
        $this->assertCount(8, $checks->where('answer_check', true));
        $this->assertSame(0, $manual->where('element_type', 'block')->where('answer_check', true)->count());

        $removed = $manual->first(fn ($i) => str_starts_with((string) $i->label, 'Lesson 4.2') && $i->element_type === 'block');
        $this->assertStringContainsString('Water quality was removed from the source', $removed->reason);
        $ratio = $manual->first(fn ($i) => str_starts_with((string) $i->label, 'Lesson 2.1') && $i->element_type === 'block');
        $this->assertStringContainsString('The brew ratio changed (a number changed)', $ratio->reason);
        $this->assertSame('major', $ratio->severity);
        $this->assertSame($doc['modules'][1]['lessons'][0]['blocks'][0], $ratio->before);

        // the removed lesson keeps a citation housekeeping item; the new section is reported as uncovered
        $remap = $items->where('kind', 'citation_remap');
        $this->assertCount(1, $remap);
        $this->assertSame('accepted', $remap->first()->status);
        $uncovered = $items->where('kind', 'uncovered');
        $this->assertCount(1, $uncovered);
        $this->assertStringContainsString('Storing beans', (string) $uncovered->first()->label);

        $this->assertSame([
            'items' => 24, 'elements' => 22, 'remaps' => 1, 'uncovered' => 1, 'major' => 22, 'answerChecks' => 8, 'groups' => 5,
        ], array_intersect_key($proposal->counts, array_flip(['items', 'elements', 'remaps', 'uncovered', 'major', 'answerChecks', 'groups'])));
        $this->assertSame('proposal.created', AuditEntry::query()->where('subject_id', $proposal->id)->firstOrFail()->action);
        $this->assertSame($this->connectionOf($session)->latest_revision_id !== $this->connectionOf($session)->synced_revision_id, true);
    }

    public function testStalenessIsPerElementAndPerCourse(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();

        $data = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/sessions/{$session->id}/staleness")->assertOk()->json('data');
        $this->assertSame('stale', $data['summary']['state']);
        $this->assertSame(22, $data['summary']['pendingElements']);
        $this->assertSame(1, $data['summary']['syncedRevision']);
        $this->assertSame(2, $data['summary']['latestRevision']);
        $this->assertNotNull($data['summary']['openProposalId']);
        $this->assertSame(0, $data['summary']['days']);
        $this->assertCount(22, $data['elements']);
        $question = collect($data['elements'])->first(fn ($e) => $e['type'] === 'question');
        $this->assertTrue($question['answerCheck']);
        $this->assertSame('pending', $question['status']);
        $removed = collect($data['elements'])->first(fn ($e) => str_starts_with((string) $e['label'], 'Lesson 4.2') && $e['type'] === 'block');
        $this->assertSame('source_removed', $removed['status']);

        // the session list carries a freshness badge
        $row = collect($this->actingAs($author, 'api')->getJson('/api/admin/course-builder/sessions')->json('data'))->firstWhere('id', $session->id);
        $this->assertSame('stale', $row['freshness']['state']);
    }

    public function testTheProposalShowsItemsGroupedByLessonWithCitationLabels(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();

        $list = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/sessions/{$session->id}/proposals")->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertEquals(['number' => 2, 'id' => $this->connectionOf($session)->latest_revision_id], array_intersect_key($list[0]['toRevision'], array_flip(['number', 'id'])));

        $detail = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/proposals/{$proposal->id}")->assertOk()->json('data');
        $keys = array_column($detail['groups'], 'label');
        $this->assertContains('Course', $keys);
        $this->assertContains('New in the source', $keys);
        $this->assertCount(1, array_filter($keys, fn ($k) => str_starts_with($k, 'Lesson 2.1:')));
        $lesson = collect($detail['groups'])->first(fn ($g) => str_starts_with($g['label'], 'Lesson 2.1:'));
        $this->assertSame('§2.1 The brew ratio', $lesson['items'][0]['fragments'][0]['label']);
        $this->assertSame(['objective', 'block', 'block', 'question', 'question'], array_column($lesson['items'], 'type'));
    }

    public function testWithoutAiTheProposalStillExistsWithManualItems(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->disableAi();

        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();

        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $this->assertSame('ready', $proposal->status);
        $this->assertSame(22, ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'manual')->count());
        $this->actingAs($author, 'api')->getJson("/api/admin/living-course/sessions/{$session->id}/staleness")->assertOk()->assertJsonPath('data.summary.state', 'stale');
    }

    public function testMovedSectionsOnlyRemapCitations(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $before = Fragment::query()->where('source_id', $this->sourceOf($session)->id)->get()->first(fn ($f) => $f->label() === '§2.1 The brew ratio');

        $this->upload($author, $session, $this->markdown('### The brew ratio', '### The brewing ratio'))->assertCreated();

        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $this->assertSame('ready', $proposal->status, 'nothing to decide: every item is an accepted remap');
        $items = ProposalItem::query()->where('proposal_id', $proposal->id)->get();
        $this->assertSame(['citation_remap'], $items->pluck('kind')->unique()->values()->all());
        $this->assertSame(['accepted'], $items->pluck('status')->unique()->values()->all());
        $this->assertCount(7, $items, 'lesson 2.1 (lesson, objective, 2 blocks, 2 questions) and course objective 2');
        $item = $items->first(fn ($i) => $i->element_type === 'block');
        $this->assertSame([$before->id], $item->fragment_ids);
        $this->assertNotContains($before->id, $item->after['citations']);
        $this->assertSame('none', $item->change_class);
        $this->assertSame(0, ElementStatus::query()->where('session_id', $session->id)->count(), 'a remap does not make anything stale');
    }

    public function testRevisionsThatNoElementCitesAreAcceptedWithoutAProposalToDecide(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        // the bloom section is not cited by any lesson of the generated course
        $edit = $this->markdown('Start by pouring about twice the weight', 'Begin by pouring about twice the weight');
        $response = $this->upload($author, $session, $edit)->assertCreated();

        $this->assertSame('no_impact', $response->json('data.revision.status'));
        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $this->assertSame('no_impact', $proposal->status);
        $connection = $this->connectionOf($session);
        $this->assertSame($connection->latest_revision_id, $connection->synced_revision_id, 'the synced pointer advanced');
        $this->assertSame(0, ElementStatus::query()->where('session_id', $session->id)->count());
        $this->assertStringContainsString('Begin by pouring', Fragment::query()->where('source_id', $this->sourceOf($session)->id)->get()->pluck('text')->implode(' '));
    }

    public function testANewerRevisionSupersedesAnUndecidedProposal(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $first = Proposal::query()->where('session_id', $session->id)->firstOrFail();

        $this->upload($author, $session, $this->markdown('A ratio of 1:15', 'A ratio of 1:17', 'coffee-brewing.v2.md'))->assertCreated();

        $this->assertSame('superseded', $first->refresh()->status);
        $second = Proposal::query()->where('session_id', $session->id)->where('number', 2)->firstOrFail();
        $this->assertSame($first->from_revision_id, $second->from_revision_id, 'still compared with the revision the course reflects');
        $this->assertSame(3, Revision::query()->findOrFail($second->to_revision_id)->number);
        $this->assertSame(1, Proposal::query()->where('session_id', $session->id)->whereIn('status', Proposal::OPEN)->count());
        $this->assertSame('proposal.superseded', AuditEntry::query()->where('subject_id', $first->id)->where('action', 'proposal.superseded')->firstOrFail()->action);
    }

    public function testAProposalWithDecidedItemsIsKeptWhenANewerRevisionArrives(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $first = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        ProposalItem::query()->where('proposal_id', $first->id)->where('kind', 'manual')->first()->forceFill(['status' => 'accepted', 'decided_by' => $author->getKey()])->save();

        $this->upload($author, $session, $this->markdown('A ratio of 1:15', 'A ratio of 1:17', 'coffee-brewing.v2.md'))->assertCreated();

        $this->assertSame(1, Proposal::query()->where('session_id', $session->id)->count());
        $first->refresh();
        $this->assertNotSame('superseded', $first->status);
        $this->assertSame(3, $first->counts['newerRevision']);
    }

    public function testSourcesChangedBeforeTheCourseIsGeneratedCreateNoProposal(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);

        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();

        $this->assertSame(0, Proposal::query()->where('session_id', $session->id)->count());
    }

    public function testProposalEndpointsAreGuarded(): void
    {
        $owner = $this->author();
        $session = $this->toApplied($owner);
        $this->upload($owner, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $p = '/api/admin/living-course';
        $urls = ["{$p}/sessions/{$session->id}/staleness", "{$p}/sessions/{$session->id}/proposals", "{$p}/proposals/{$proposal->id}"];

        foreach ($urls as $url) {
            $this->actingAs($owner, 'api')->getJson($url)->assertOk();
            $this->actingAs($this->admin(), 'api')->getJson($url)->assertOk();
            $this->actingAs($this->tutor(), 'api')->getJson($url)->assertStatus(403);
            $this->actingAs($this->student(), 'api')->getJson($url)->assertStatus(403);
        }
        $missing = '01aaaaaaaaaaaaaaaaaaaaaaaa';
        foreach (["{$p}/sessions/{$missing}/staleness", "{$p}/sessions/{$missing}/proposals", "{$p}/proposals/{$missing}"] as $url) {
            $this->actingAs($owner, 'api')->getJson($url)->assertStatus(404);
        }
        $this->app['auth']->forgetGuards();
        foreach ($urls as $url) {
            $this->getJson($url)->assertStatus(401);
        }
    }
}
