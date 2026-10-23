<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;
use Ulams\Courses\Tests\Models\User;
use Ulams\LivingCourse\Models\LearnerNotice;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\TestCase;

/**
 * A source update reaches every variant of a course as its own reviewable proposal (ADR 0192, CV-5): the elements a variant
 * shares with the base take the base's patch without another model call, only what the variant changed is analysed for it,
 * the author can accept the shared patch everywhere at once, and applying a variant proposal changes that variant's course only.
 */
class VariantUpdatesTest extends TestCase
{
    /** A base course and an applied expert variant from one source, then the source is replaced by its second version. */
    protected function updated(bool $auto = true): array
    {
        $author = $this->author();
        $session = $this->uploaded($author);
        $p = '/api/admin/course-builder';
        $this->action($author, $session, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 30, 'lessonMinutes' => 10]])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'assessments', 'value' => ['quiz', 'final']])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'audiences', 'value' => ['level:expert']])->assertStatus(202)->assertJsonPath('data.accepted', true);
        $this->action($author, $session, 'decide_for_me', 'interview')->assertStatus(202);
        $outline = $session->refresh()->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);
        $content = $session->refresh()->current_version_id;
        $this->action($author, $session, 'approve_apply', "apply-{$content}", ['versionId' => $content])->assertStatus(202);
        $expert = Variant::query()->where('session_id', $session->id)->where('key', 'expert')->firstOrFail();
        $variantOutline = $expert->refresh()->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$variantOutline}", ['versionId' => $variantOutline])->assertStatus(202);
        $version = $expert->refresh()->current_version_id;
        $this->action($author, $session, 'approve_variant', "variant-{$version}", ['versionId' => $version])->assertStatus(202);
        $session->refresh();
        $this->assertNotNull($expert->refresh()->course_id);

        if (!$auto) {
            $this->connectionOf($session)->forceFill(['auto_analyse' => false])->save();
        }
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();

        return [$author, $session, $expert];
    }

    private function proposalsOf(Session $session): array
    {
        $base = Proposal::query()->where('session_id', $session->id)->whereNull('variant_id')->firstOrFail();
        $variant = Proposal::query()->where('session_id', $session->id)->whereNotNull('variant_id')->first();

        return [$base, $variant];
    }

    public function testTheVariantGetsItsOwnProposalInTheSameGroupAsTheBase(): void
    {
        [, $session, $expert] = $this->updated();

        [$base, $variant] = $this->proposalsOf($session);

        $this->assertNotNull($variant, 'the expert variant cites changed passages too');
        $this->assertNotNull($base->proposal_group);
        $this->assertSame($base->proposal_group, $variant->proposal_group);
        $this->assertSame($expert->id, $variant->variant_id);
        $this->assertSame($expert->applied_version_id, $variant->base_version_id, 'the variant is reviewed against what its learners have');
        $this->assertSame($base->to_revision_id, $variant->to_revision_id);
        $this->assertSame(0, ProposalItem::query()->where('proposal_id', $variant->id)->where('kind', 'uncovered')->count(), 'new source sections are for the base only');
    }

    public function testSharedElementsTakeTheBasePatchWithoutAnotherModelCall(): void
    {
        [, $session] = $this->updated();
        [$base, $variant] = $this->proposalsOf($session);

        $shared = ProposalItem::query()->where('proposal_id', $variant->id)->get()->filter(fn (ProposalItem $i) => !empty($i->flags['reusedFrom']));

        $this->assertGreaterThan(0, $shared->count(), 'a variant keeps lessons of the base unchanged');
        foreach ($shared as $item) {
            $from = ProposalItem::query()->findOrFail($item->flags['reusedFrom']);
            $this->assertSame($base->id, $from->proposal_id);
            $this->assertSame($from->kind, $item->kind);
            $this->assertEquals($from->after, $item->after);
            $this->assertSame('pending', $item->status);
        }
        $this->assertSame([], $shared->filter(fn (ProposalItem $i) => !empty($i->ai_call_ids))->all(), 'the shared patches cost the variant nothing');
        $own = ProposalItem::query()->where('proposal_id', $variant->id)->whereNull('flags->reusedFrom')->whereIn('kind', ['update', 'no_change', 'remove'])->get();
        $this->assertGreaterThan(0, $own->count(), 'the elements the variant changed were analysed for it');
        $this->assertSame('ready', $variant->refresh()->status);
        $this->assertSame($shared->count(), (int) $variant->refresh()->counts['reused']);
    }

    public function testAnalysingTheVariantAsksAboutItsOwnElementsOnly(): void
    {
        [$author, $session] = $this->updated(false);
        [$base, $variant] = $this->proposalsOf($session);
        $this->assertSame(0, ProposalItem::query()->where('proposal_id', $variant->id)->whereNotNull('flags->reusedFrom')->count(), 'nothing to take before the base is analysed');
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$base->id}/analyse", ['confirmEstimate' => true])->assertStatus(202);
        $reused = fn () => ProposalItem::query()->where('proposal_id', $variant->id)->whereNotNull('flags->reusedFrom')->get()->mapWithKeys(fn (ProposalItem $i) => [$i->id => [$i->kind, json_encode($i->after)]])->all();
        $before = $reused();
        $own = ProposalItem::query()->where('proposal_id', $variant->id)->where('kind', 'manual')->get();
        $this->assertNotEmpty($before, 'the shared elements were filled from the base');
        $this->assertGreaterThan(0, $own->count(), 'the variant changed some elements itself');
        $this->assertSame(0, $own->filter(fn (ProposalItem $i) => !empty($i->flags['inherits']))->count());
        $this->assertSame('awaiting_analysis', $variant->refresh()->status);
        $this->assertSame(0, AiCall::query()->forSubject(Proposal::SUBJECT_TYPE, $variant->id)->count());

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$variant->id}/analyse", ['confirmEstimate' => true])->assertStatus(202);

        $this->assertGreaterThan(0, AiCall::query()->forSubject(Proposal::SUBJECT_TYPE, $variant->id)->where('task', 'update')->count());
        $this->assertSame($before, $reused(), 'what was taken from the base is not asked again');
        $this->assertSame('ready', $variant->refresh()->status);
    }

    public function testOneDecisionAcceptsTheSharedPatchEverywhere(): void
    {
        [$author, $session] = $this->updated();
        [$base, $variant] = $this->proposalsOf($session);
        $item = ProposalItem::query()->where('proposal_id', $base->id)->where('kind', 'update')->get()
            ->first(fn (ProposalItem $i) => ProposalItem::query()->where('proposal_id', $variant->id)->where('element_id', $i->element_id)->whereNotNull('flags->reusedFrom')->exists());
        $this->assertNotNull($item, 'the base has an update the variant shares');

        $response = $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$base->id}/items/{$item->id}/accept-in-group")->assertOk();

        $this->assertGreaterThanOrEqual(1, $response->json('data.accepted'));
        $this->assertSame([$variant->id], $response->json('data.proposals'));
        $this->assertSame('accepted', $item->refresh()->status);
        $twin = ProposalItem::query()->where('proposal_id', $variant->id)->where('element_id', $item->element_id)->firstOrFail();
        $this->assertSame('accepted', $twin->status);
        // the rest of the variant is still undecided
        $this->assertGreaterThan(0, ProposalItem::query()->where('proposal_id', $variant->id)->where('status', 'pending')->count());

        $detail = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/proposals/{$base->id}")->assertOk();
        $this->assertSame($variant->id, $detail->json('data.siblings.0.id'));
        $this->assertSame('expert', $detail->json('data.siblings.0.variant.key'));
        $this->actingAs($author, 'api')->getJson("/api/admin/living-course/proposals/{$variant->id}")->assertOk()->assertJsonPath('data.variant.key', 'expert')->assertJsonPath('data.proposalGroup', $base->proposal_group);
    }

    public function testApplyingTheVariantProposalUpdatesThatVariantsCourseOnly(): void
    {
        [$author, $session, $expert] = $this->updated();
        [$base, $variant] = $this->proposalsOf($session);
        $baseVersionBefore = $session->refresh()->current_version_id;
        $variantVersionBefore = $expert->applied_version_id;
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$variant->id}/accept-all")->assertOk();

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$variant->id}/apply")->assertStatus(202);

        $variant->refresh();
        $this->assertSame('applied', $variant->status);
        $expert->refresh();
        $version = Version::query()->findOrFail($variant->result_version_id);
        $this->assertSame($expert->id, $version->variant_id);
        $this->assertSame('update', $version->kind);
        $this->assertSame($variantVersionBefore, $version->parent_id);
        $this->assertSame($version->id, $expert->applied_version_id);
        $this->assertSame($version->id, $expert->current_version_id);
        $this->assertSame($baseVersionBefore, $session->refresh()->current_version_id, 'the base course is not touched');
        $this->assertSame('open', Proposal::query()->findOrFail($base->id)->status === 'ready' ? 'open' : $base->status);

        $release = Release::query()->where('session_id', $session->id)->where('variant_id', $expert->id)->orderByDesc('number')->firstOrFail();
        $this->assertSame('update', $release->trigger);
        $this->assertSame($version->id, $release->version_id);
        $this->assertSame((int) $expert->course_id, (int) $release->course_id);
        $this->assertSame(1, Release::query()->where('session_id', $session->id)->whereNull('variant_id')->count(), 'the base has no new release');

        // the source revision stays unacknowledged until the base proposal is decided
        $revision = Revision::query()->findOrFail($base->to_revision_id);
        $this->assertNotSame('synced', $revision->status);
    }

    public function testLearnersOfTheVariantAreToldAboutTheVariantCourse(): void
    {
        [$author, $session, $expert] = $this->updated();
        [, $variant] = $this->proposalsOf($session);
        $student = User::factory()->create();
        $student->courses()->attach($expert->course_id);
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$variant->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$variant->id}/apply")->assertStatus(202);

        $notices = LearnerNotice::query()->where('proposal_id', $variant->id)->get();

        foreach ($notices as $notice) {
            $this->assertSame((int) $expert->course_id, (int) $notice->course_id, 'notices belong to the variant course, not the base');
        }
        $this->assertSame(0, LearnerNotice::query()->where('proposal_id', $variant->id)->where('course_id', $session->course_id)->count());
    }

    public function testRejectingTheVariantProposalDoesNotAcknowledgeTheRevision(): void
    {
        [$author, $session] = $this->updated();
        [$base, $variant] = $this->proposalsOf($session);

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$variant->id}/reject")->assertOk();

        $this->assertSame('rejected', $variant->refresh()->status);
        $this->assertNotSame('rejected', $base->refresh()->status, 'the base proposal is decided on its own');
        $this->assertNotSame('synced', Revision::query()->findOrFail($base->to_revision_id)->status);
    }

    public function testACourseWithoutVariantsIsUnchanged(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();

        $proposals = Proposal::query()->where('session_id', $session->id)->get();

        $this->assertCount(1, $proposals);
        $this->assertNull($proposals[0]->variant_id);
        $this->assertNull($proposals[0]->proposal_group);
    }
}
