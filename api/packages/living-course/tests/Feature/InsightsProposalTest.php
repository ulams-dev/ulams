<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\Models\User;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\ElementStatus;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Services\AnalysisService;
use Ulams\LivingCourse\Services\ProposalService;
use Ulams\LivingCourse\Services\StalenessService;
use Ulams\LivingCourse\Tests\TestCase;

/**
 * Improvement proposals from learner insights (ADR 0181): a proposal of kind `insights` without a source
 * revision, created from aggregated evidence only, analysed by the `insights_improve` task, reviewed as
 * a Living Course diff and applied without touching the source revisions.
 */
class InsightsProposalTest extends TestCase
{
    private const LEARNER_NAME = 'Zeldaquill Featherstone';

    /** The aggregates a caller hands over; the extra keys are what must never be kept. */
    private function evidence(array $overrides = []): array
    {
        return $overrides + [
            'periodDays' => 30, 'learners' => 42, 'minGroupSize' => 10, 'struggleRate' => 0.38, 'remediationRate' => 0.2, 'recoveryRate' => 0.5, 'helpfulShare' => null,
            'topReasons' => [['rule' => 'failed_attempts', 'count' => 16]], 'wrongOptions' => [['optionId' => 'opt-b', 'label' => 'Brew with boiling water.', 'share' => 0.38]], 'tutorOutOfScope' => 2,
            'userId' => 7, 'name' => self::LEARNER_NAME, 'learners_detail' => [['userId' => 7]],
        ];
    }

    /** @return array{0:User,1:Session} */
    private function applied(): array
    {
        $author = $this->author();

        return [$author, $this->toApplied($author)];
    }

    private function lessonOf(Session $session, string $number = '2.1'): array
    {
        foreach (Blueprint::lessons($session->currentVersion->document) as $item) {
            if ($item['number'] === $number) {
                return $item['lesson'];
            }
        }
        $this->fail("No lesson {$number}");
    }

    private function blockId(Session $session, string $number = '2.1'): string
    {
        return $this->lessonOf($session, $number)['blocks'][0]['id'];
    }

    private function questionId(Session $session, string $number = '2.1'): string
    {
        return $this->lessonOf($session, $number)['quiz']['questions'][0]['id'];
    }

    private function create(Session $session, string $element, User $author, array $evidence = []): array
    {
        return app(ProposalService::class)->createFromInsights($session, $element, $evidence ?: $this->evidence(), (int) $author->getKey());
    }

    private function analyse(Proposal $proposal, User $author): array
    {
        return app(AnalysisService::class)->begin($proposal, true, (int) $author->getKey());
    }

    public function testAProposalOfItsOwnKindIsCreatedFromAggregatedEvidenceOnly(): void
    {
        [$author, $session] = $this->applied();
        $revisions = Revision::query()->count();
        $block = $this->blockId($session);

        $created = $this->create($session, $block, $author);

        $this->assertSame('created', $created['state']);
        $proposal = $created['proposal']->refresh();
        $this->assertSame('insights', $proposal->kind);
        $this->assertSame('insights', $proposal->trigger);
        $this->assertSame('awaiting_analysis', $proposal->status);
        $this->assertNull($proposal->source_id);
        $this->assertNull($proposal->from_revision_id);
        $this->assertNull($proposal->to_revision_id);
        $this->assertSame($session->current_version_id, $proposal->base_version_id);
        $this->assertSame($revisions, Revision::query()->count(), 'no source revision is invented');

        // whatever else the caller passed is gone: no user id, no name, no row of one learner
        $stored = json_encode($proposal->evidence);
        $this->assertStringNotContainsString(self::LEARNER_NAME, $stored);
        $this->assertStringNotContainsString('userId', $stored);
        $this->assertStringNotContainsString('learners_detail', $stored);
        $this->assertSame(42, $proposal->evidence['learners']);
        $this->assertSame(0.38, $proposal->evidence['struggleRate']);

        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->sole();
        $this->assertSame('manual', $item->kind);
        $this->assertSame($block, $item->element_id);
        $this->assertSame('block', $item->element_type);
        $this->assertSame('38 % of 42 learners struggled here in the last 30 days; failing quiz attempts is the most common reason; 38 % chose the same wrong answer.', $item->reason);
        $this->assertNotEmpty($item->fragment_ids, 'the passages it will be grounded on are listed');

        $audit = AuditEntry::query()->where('action', 'proposal.created')->where('subject_id', $proposal->id)->sole();
        $this->assertSame('insights', $audit->data['kind']);
        $this->assertSame(['learners' => 42, 'periodDays' => 30, 'struggleRate' => 0.38, 'remediationRate' => 0.2], $audit->data['evidence']);
        $this->assertSame((int) $author->getKey(), $audit->actor_id);
        $this->assertNull($audit->revision_id);
    }

    public function testEvidenceFromASmallGroupIsRefusedWhateverTheCallerClaims(): void
    {
        [$author, $session] = $this->applied();

        foreach ([['learners' => 9], ['learners' => 5, 'minGroupSize' => 2], ['learners' => 0]] as $small) {
            try {
                $this->create($session, $this->blockId($session), $author, $this->evidence($small));
                $this->fail('a group below ten learners must be refused');
            } catch (ProposalException $e) {
                $this->assertSame(422, $e->status);
                $this->assertSame('small_group', $e->extra['code']);
            }
        }
        $this->assertSame(0, Proposal::query()->count());

        // a tenant that raised its minimum keeps it
        $this->expectException(ProposalException::class);
        $this->create($session, $this->blockId($session), $author, $this->evidence(['learners' => 15, 'minGroupSize' => 20]));
    }

    public function testOnlyBlocksQuestionsAndLessonsOfTheCurrentVersionCanBeImproved(): void
    {
        [$author, $session] = $this->applied();
        $objective = $this->lessonOf($session)['objectives'][0]['id'];

        foreach ([$objective, $session->currentVersion->document['course']['id'], 'no-such-element'] as $element) {
            try {
                $this->create($session, $element, $author);
                $this->fail("{$element} cannot be improved");
            } catch (ProposalException $e) {
                $this->assertSame('element_not_improvable', $e->extra['code']);
            }
        }
        $this->assertSame('question', ProposalItem::query()->where('proposal_id', $this->create($session, $this->questionId($session), $author)['proposal']->id)->sole()->element_type);

        // a lesson stands for its text blocks
        $lesson = $this->create($session, $this->lessonOf($session, '2.2')['id'], $author)['proposal'];
        $items = ProposalItem::query()->where('proposal_id', $lesson->id)->get();
        $this->assertGreaterThan(0, $items->count());
        $this->assertLessThanOrEqual(4, $items->count());
        $this->assertSame(['block'], $items->pluck('element_type')->unique()->values()->all());
    }

    public function testAnElementThatCitesNothingCannotBeGrounded(): void
    {
        [$author, $session] = $this->applied();
        $doc = $session->currentVersion->document;
        $found = Blueprint::find($doc, $this->blockId($session));
        $found['node']['citations'] = [];
        $doc = Blueprint::setAt($doc, $found['path'], $found['node']);
        $lesson = Blueprint::find($doc, $this->lessonOf($session)['id']);
        $doc = Blueprint::setAt($doc, [...$lesson['path'], 'citations'], []);
        $session->currentVersion->forceFill(['document' => $doc])->save();

        try {
            $this->create($session->refresh(), $found['node']['id'], $author);
            $this->fail('nothing to cite, nothing to propose');
        } catch (ProposalException $e) {
            $this->assertSame('no_sources', $e->extra['code']);
        }
    }

    public function testAnOpenProposalForTheSameElementIsReturnedAndTheDayHasALimit(): void
    {
        [$author, $session] = $this->applied();
        config(['living_course.cost.insights_proposals_per_session_per_day' => 2]);

        $first = $this->create($session, $this->blockId($session), $author);
        $again = $this->create($session, $this->blockId($session), $author);
        $this->assertSame('kept_existing', $again['state']);
        $this->assertSame($first['proposal']->id, $again['proposal']->id);
        $this->assertSame(1, Proposal::query()->count());

        $this->assertSame('created', $this->create($session, $this->questionId($session), $author)['state']);
        try {
            $this->create($session, $this->blockId($session, '2.2'), $author);
            $this->fail('a third proposal in one day is over the limit');
        } catch (ProposalException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame('daily_limit', $e->extra['code']);
        }
        // an element that already has an open proposal is not a new one, even at the limit
        $this->assertSame('kept_existing', $this->create($session, $this->questionId($session), $author)['state']);

        // yesterday's proposals do not count today
        Proposal::query()->update(['created_at' => now()->subDay()]);
        $this->assertSame('created', $this->create($session, $this->blockId($session, '2.2'), $author)['state']);
    }

    public function testTheAnalysisProposesAGroundedDiffFromEvidenceThatNamesNoLearner(): void
    {
        [$author, $session] = $this->applied();
        $proposal = $this->create($session, $this->blockId($session), $author)['proposal'];

        $result = $this->analyse($proposal, $author);

        $this->assertSame('started', $result['state']);
        $proposal->refresh();
        $this->assertSame('ready', $proposal->status);
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->sole();
        $this->assertSame('update', $item->kind);
        $this->assertSame('pending', $item->status, 'the author decides');
        $this->assertNotSame($item->before['markdown'], $item->after['markdown']);
        $this->assertSame($item->before['id'], $item->after['id']);
        // every cited passage is one of the live fragments the request carried
        $live = Fragment::query()->whereIn('source_id', $session->sources()->select('id'))->pluck('id')->all();
        $this->assertNotEmpty($item->after['citations']);
        $this->assertSame([], array_diff($item->after['citations'], $live));
        $this->assertNotEmpty($item->ai_call_ids);

        $calls = AiCall::query()->forSubject(Proposal::SUBJECT_TYPE, $proposal->id)->get();
        $this->assertSame(1, $calls->where('task', 'insights_improve')->count());
        $this->assertSame(1, $calls->where('task', 'grounding')->count());
        $this->assertSame(0, $calls->where('task', 'update')->count());
        $this->assertNull($calls->where('task', 'insights_improve')->first()->user_id, 'the call is about learners: nobody is on it, the proposal and the audit trail say who asked');
        $this->assertSame((int) $author->getKey(), (int) $proposal->created_by);
        $this->assertSame($proposal->cost_micro_usd, (int) $calls->sum('cost_micro_usd'));

        $sent = collect($this->fake()->sent())->where('task', 'insights_improve')->sole();
        $text = $sent->system . "\n" . implode("\n", array_map(fn ($b) => $b->text, $sent->blocks));
        $this->assertStringContainsString('<learner_evidence aggregated="true">', $text);
        $this->assertStringContainsString('"struggleRate":0.38', $text);
        $this->assertStringContainsString('<source_fragments untrusted="true">', $text);
        foreach (['userId', 'user_id', self::LEARNER_NAME, 'learners_detail'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text);
        }
        // source text only inside the untrusted wrapper, and the cacheable blocks come first
        $this->assertTrue($sent->blocks[0]->cache && $sent->blocks[1]->cache && !$sent->blocks[2]->cache);
    }

    public function testAnImprovementWithoutCitationsIsRefusedAndTheItemStaysManual(): void
    {
        [$author, $session] = $this->applied();
        $question = $this->questionId($session);
        $proposal = $this->create($session, $question, $author)['proposal'];
        $node = Blueprint::find($session->currentVersion->document, $question)['node'];
        $uncited = ['citations' => []] + $node;
        $answer = ['items' => [['elementId' => $question, 'decision' => 'update', 'reason' => 'Clearer wording.', 'severity' => 'minor', 'fragmentIds' => [], 'answerStatus' => 'unchanged', 'block' => null, 'question' => $uncited, 'objective' => null]], 'reply' => 'ok'];
        // the answer and its one repair both lack a citation
        $this->fake()->queueJson('insights_improve', $answer)->queueJson('insights_improve', $answer);

        $this->analyse($proposal, $author);

        $proposal->refresh();
        $this->assertSame('ready', $proposal->status);
        $this->assertCount(1, $proposal->counts['failedGroups'], 'the group the model could not answer validly failed');
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->sole();
        $this->assertSame('manual', $item->kind);
        $this->assertNull($item->after);
        $this->assertSame(1, Step::query()->where('status', 'failed')->count());
        $repairs = collect($this->fake()->sent())->where('task', 'insights_improve')->filter(fn ($r) => $r->turns !== []);
        $this->assertCount(1, $repairs, 'the model was told what was wrong once');
    }

    public function testAnImprovementThatChangesTheCorrectAnswerIsRefused(): void
    {
        [$author, $session] = $this->applied();
        $question = $this->questionId($session);
        $proposal = $this->create($session, $question, $author)['proposal'];
        $node = Blueprint::find($session->currentVersion->document, $question)['node'];
        $swapped = $node;
        $swapped['options'] = array_map(fn ($o) => ['correct' => !$o['correct']] + $o, $node['options']);
        $answer = ['items' => [['elementId' => $question, 'decision' => 'update', 'reason' => 'Clearer wording.', 'severity' => 'major', 'fragmentIds' => $node['citations'], 'answerStatus' => 'unchanged', 'block' => null, 'question' => $swapped, 'objective' => null]], 'reply' => 'ok'];
        $this->fake()->queueJson('insights_improve', $answer)->queueJson('insights_improve', $answer);

        $this->analyse($proposal, $author);

        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->sole();
        $this->assertSame('manual', $item->kind, 'an improvement never decides which answer is correct');
        $this->assertNull($item->after);
        $turns = collect($this->fake()->sent())->where('task', 'insights_improve')->flatMap(fn ($r) => array_map(fn ($t) => $t['text'], $r->turns))->implode("\n");
        $this->assertStringContainsString('must not change which answer is correct', $turns);
    }

    public function testAClaimThePassagesDoNotSupportLeavesTheItemForTheAuthorToEdit(): void
    {
        [$author, $session] = $this->applied();
        $proposal = $this->create($session, $this->blockId($session), $author)['proposal'];
        $unsupported = ['unsupported' => [['blockIndex' => 0, 'claim' => 'Pour at 99 degrees', 'reason' => 'The source says 96.']]];
        $this->fake()->queueJson('grounding', $unsupported)->queueJson('grounding', $unsupported);

        $this->analyse($proposal, $author);

        $proposal->refresh();
        $this->assertSame('ready', $proposal->status);
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->sole();
        $this->assertSame('manual', $item->kind);
        $this->assertNull($item->after);
        $this->assertSame('The AI could not propose a grounded change; edit it yourself.', $item->reason);
        $this->assertStringContainsString('Possibly unsupported: Pour at 99 degrees', $item->flags['ungrounded'][0]);
        $this->assertSame(2, AiCall::query()->forSubject(Proposal::SUBJECT_TYPE, $proposal->id)->where('task', 'insights_improve')->count(), 'one regeneration, then it stops');
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk()->assertJsonPath('data.accepted', 0);
    }

    public function testApplyingAnImprovementWritesAVersionWithoutTouchingTheSourceRevisions(): void
    {
        [$author, $session] = $this->applied();
        $block = $this->blockId($session);
        $lesson = $this->lessonOf($session);
        $proposal = $this->create($session, $block, $author)['proposal'];
        $this->analyse($proposal, $author);
        // an unrelated source update that is still waiting for review must stay waiting
        ElementStatus::query()->create(['session_id' => $session->id, 'element_id' => $this->blockId($session, '2.2'), 'status' => 'pending', 'element_type' => 'block', 'label' => 'Lesson 2.2', 'since' => now(), 'updated_at' => now()]);
        $revisions = Revision::query()->count();
        $synced = $this->connectionOf($session)->synced_revision_id;
        $before = $session->current_version_id;
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->sole();

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk()->assertJsonPath('data.accepted', 1);
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);

        $proposal->refresh();
        $this->assertSame('applied', $proposal->status);
        $version = \Ulams\CourseBuilder\Models\Version::query()->findOrFail($proposal->result_version_id);
        $this->assertSame('update', $version->kind);
        $this->assertSame($before, $version->parent_id);
        $this->assertSame('Improvement from learner insights: ' . $proposal->counts['targetLabel'], $version->reason);
        $this->assertSame($version->id, $session->refresh()->applied_version_id);
        // the course shows the new text; the source revisions are exactly as they were
        $this->assertStringContainsString(substr($item->after['markdown'], -40), (string) Topic::query()->find(EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lesson['id'])->where('entity_type', 'topic')->value('entity_id'))->topicable->value);
        $this->assertSame($revisions, Revision::query()->count());
        $this->assertSame($synced, $this->connectionOf($session)->synced_revision_id);
        $this->assertSame('pending', ElementStatus::query()->where('session_id', $session->id)->where('element_id', $this->blockId($session, '2.2'))->value('status'));
        $this->assertSame('in_sync', ElementStatus::query()->where('session_id', $session->id)->where('element_id', $block)->value('status') ?? 'in_sync');

        $audit = AuditEntry::query()->where('action', 'proposal.applied')->where('subject_id', $proposal->id)->sole();
        $this->assertSame('insights', $audit->data['kind']);
        $this->assertSame(42, $audit->data['evidence']['learners']);
        $this->assertNull($audit->revision_id);
        $this->assertNotEmpty($audit->ai_call_ids);
        $this->assertSame($version->number, $audit->version_to);
        $this->assertTrue(app(\Ulams\LivingCourse\Services\AuditLog::class)->verify()['ok']);
        // learners are told about a rewritten lesson the usual way (ADR 0033), without the model's reasons
        $this->assertSame('', app(\Ulams\LivingCourse\Services\ProgressRules::class)->note($proposal));
    }

    public function testRejectingAnImprovementLeavesTheSourceAndItsStalenessAlone(): void
    {
        [$author, $session] = $this->applied();
        $proposal = $this->create($session, $this->blockId($session), $author)['proposal'];
        $this->analyse($proposal, $author);
        ElementStatus::query()->create(['session_id' => $session->id, 'element_id' => $this->blockId($session, '2.2'), 'status' => 'pending', 'element_type' => 'block', 'label' => 'Lesson 2.2', 'since' => now(), 'updated_at' => now()]);
        $synced = $this->connectionOf($session)->synced_revision_id;

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/reject")->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame($synced, $this->connectionOf($session)->synced_revision_id);
        $this->assertSame('pending', ElementStatus::query()->where('session_id', $session->id)->where('element_id', $this->blockId($session, '2.2'))->value('status'));
        $this->assertSame('proposal.rejected', AuditEntry::query()->where('subject_id', $proposal->id)->where('action', 'proposal.rejected')->sole()->action);
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/reanalyse")->assertStatus(409);
    }

    public function testAnOpenImprovementDoesNotMakeTheCourseStaleAndTheApiShowsKindAndEvidence(): void
    {
        [$author, $session] = $this->applied();
        $proposal = $this->create($session, $this->blockId($session), $author)['proposal'];
        $this->analyse($proposal, $author);

        $this->assertSame('in_sync', app(StalenessService::class)->summary($session)['state']);
        $list = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/sessions/{$session->id}/proposals")->assertOk()->json('data');
        $this->assertSame('insights', $list[0]['kind']);
        $this->assertNull($list[0]['fromRevision']);
        $detail = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/proposals/{$proposal->id}")->assertOk()->json('data');
        $this->assertSame('insights', $detail['kind']);
        $this->assertSame(42, $detail['evidence']['learners']);
        $this->assertSame('failed_attempts', $detail['evidence']['topReasons'][0]['rule']);
        $item = $detail['items'][0];
        $this->assertSame('update', $item['kind']);
        $this->assertNotEmpty($item['fragments']);
        $this->assertNotSame($item['fragments'][0]['fragmentId'], $item['fragments'][0]['label'], 'the cited passage has a readable label');
    }

    public function testAnotherAuthorCannotDecideSomeoneElsesImprovement(): void
    {
        [$author, $session] = $this->applied();
        $proposal = $this->create($session, $this->blockId($session), $author)['proposal'];
        $this->analyse($proposal, $author);
        $other = $this->author();

        $this->actingAs($other, 'api')->getJson("/api/admin/living-course/proposals/{$proposal->id}")->assertForbidden();
        $this->actingAs($other, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertForbidden();
        $this->actingAs($other, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertForbidden();
    }

    public function testTheEstimateIsKnownBeforeAnythingIsCreated(): void
    {
        [$author, $session] = $this->applied();

        $estimate = app(\Ulams\LivingCourse\Services\InsightsProposalService::class)->estimate($session, $this->blockId($session), $this->evidence());

        $this->assertGreaterThan(0, $estimate['estimateMicroUsd']);
        $this->assertSame(1, $estimate['elements']);
        $this->assertSame(0, Proposal::query()->count());
        $this->assertSame(0, AiCall::query()->where('task', 'insights_improve')->count(), 'asking what it costs calls no model');
        // what begin() later estimates for the stored proposal is the same figure
        $proposal = $this->create($session, $this->blockId($session), $author)['proposal'];
        $this->analyse($proposal, $author);
        $this->assertSame($estimate['estimateMicroUsd'], (int) $proposal->refresh()->estimated_cost_micro_usd);
    }
}
