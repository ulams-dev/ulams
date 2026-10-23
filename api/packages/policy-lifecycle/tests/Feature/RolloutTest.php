<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\PolicyLifecycle\Events\PolicyChangeApproved;
use Ulams\PolicyLifecycle\Events\PolicyRetrainingAssigned;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyCertificate;
use Ulams\PolicyLifecycle\Models\PolicyReassignment;
use Ulams\PolicyLifecycle\Models\PolicyRollout;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Services\RolloutService;
use Ulams\PolicyLifecycle\Tests\Support\TrainsPeople;
use Ulams\PolicyLifecycle\Tests\TestCase;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Models\FabricPDF;

/** An approved policy change: the department courses are updated, then certificates are revoked and people reassigned. */
class RolloutTest extends TestCase
{
    use TrainsPeople;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplatesPdfSeeder::class);
    }

    /**
     * Version 1 approved, Ada (Engineering) and Sam (Sales) have finished and hold certificates, Linus has not; version 2
     * is submitted and every proposal accepted.
     *
     * @return array{0:\Ulams\Auth\Models\User,1:Policy,2:array<int,\Ulams\Courses\Tests\Models\User>,3:\Ulams\Courses\Tests\Models\User,4:PolicyVersion}
     */
    private function inReview($owner = null): array
    {
        $owner ??= $this->admin();
        [$policy, $engineers, $seller] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);
        $this->finish($seller, $policy->variants[1]->course_id);
        $this->submitChange($owner, $policy->id)->assertCreated();
        $version = PolicyVersion::query()->where('policy_id', $policy->id)->where('number', 2)->firstOrFail();
        $this->acceptAllProposals($owner, $version);

        return [$owner, $policy, $engineers, $seller, $version];
    }

    private function certificateOf($user, int $courseId): FabricPDF
    {
        return FabricPDF::query()->where('user_id', $user->getKey())->where('assignable_id', $courseId)->orderBy('id')->firstOrFail();
    }

    public function testAMajorChangeUpdatesTheCoursesRevokesCertificatesAndReassignsTheCourse(): void
    {
        [$owner, $policy, $engineers, $seller, $version] = $this->inReview();
        $adaCertificate = $this->certificateOf($engineers[0], $policy->variants[0]->course_id);
        $courseOfAda = $policy->variants[0]->course_id;

        $this->approveVersion($owner, $policy->id, 2)->assertOk();

        $version->refresh();
        $this->assertSame(PolicyVersion::APPROVED, $version->status);
        $this->assertSame('major', $version->change_class);
        $this->assertSame('major', $version->suggested_class);
        $this->assertNull($version->class_reason);
        $this->assertNotNull($version->rolled_out_at);
        $policy->refresh();
        $this->assertSame($version->id, $policy->current_version_id);
        $this->assertSame(PolicyVersion::SUPERSEDED, PolicyVersion::query()->where('policy_id', $policy->id)->where('number', 1)->value('status'));
        // every department proposal was applied
        $this->assertSame(['applied', 'applied'], Proposal::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(['applied', 'applied'], PolicyRollout::query()->orderBy('id')->pluck('status')->all());

        // Ada's certificate is revoked with the reason, and she owes the department course again
        $adaCertificate->refresh();
        $this->assertSame('revoked', $adaCertificate->status());
        $this->assertSame('policy_superseded:v2', $adaCertificate->revoked_reason);
        $assignment = Assignment::query()->where('user_id', $engineers[0]->getKey())->where('source', 'policy_change')->sole();
        $this->assertSame($courseOfAda, $assignment->course_id);
        $this->assertSame(AssignmentStatus::ASSIGNED, $assignment->status);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $assignment->due_at->timestamp, 5);
        $this->assertFalse((bool) CourseUserPivot::query()->where('user_id', $engineers[0]->getKey())->where('course_id', $courseOfAda)->value('finished'), 'the course starts over');
        // Sam too, in his own department; Linus never completed and is not touched
        $this->assertSame('revoked', $this->certificateOf($seller, $policy->variants[1]->course_id)->status());
        $this->assertSame(2, Assignment::query()->where('source', 'policy_change')->count());
        $this->assertSame(0, Assignment::query()->where('user_id', $engineers[1]->getKey())->where('source', 'policy_change')->count());
        $this->assertSame(AssignmentStatus::ASSIGNED, Assignment::query()->where('user_id', $engineers[1]->getKey())->sole()->status);
        // a major change builds no exam course
        $this->assertNull($policy->variants[0]->refresh()->exam_course_id);
        $this->assertSame(2, PolicyReassignment::query()->where('kind', 'course')->count());
    }

    public function testTheDepartmentCoursesCarryTheNewPolicyText(): void
    {
        [$owner, $policy, , , $version] = $this->inReview();
        $session = Session::query()->findOrFail($policy->variants[0]->session_id);
        $before = $session->applied_version_id;

        $this->approveVersion($owner, $policy->id, 2)->assertOk();

        $session->refresh();
        $this->assertNotSame($before, $session->applied_version_id);
        $lessons = [];
        foreach (\Ulams\CourseBuilder\Blueprint\Blueprint::lessons($session->currentVersion->document) as $item) {
            foreach ($item['lesson']['blocks'] as $block) {
                $lessons[] = $block['markdown'];
            }
        }
        $text = implode("\n", $lessons);
        $this->assertStringContainsString('two days a week', $text);
        $this->assertStringNotContainsString('three days a week', $text);
        // the apply is a release of the course (ADR 0192) with the trigger update
        $this->assertSame('update', \Ulams\CourseBuilder\Models\Release::query()->where('session_id', $session->id)->orderByDesc('number')->value('trigger'));
    }

    public function testAMinorChangeAssignsTheExamCourseInsteadOfTheCourse(): void
    {
        [$owner, $policy, $engineers, $seller, $version] = $this->inReview();

        $this->approveVersion($owner, $policy->id, 2, ['change_class' => 'minor', 'reason' => 'Wording only: the limits did not change in substance.'])->assertOk();

        $version->refresh();
        $this->assertSame('minor', $version->change_class);
        $this->assertSame('major', $version->suggested_class);
        $this->assertSame('Wording only: the limits did not change in substance.', $version->class_reason);
        $variant = $policy->variants[0]->refresh();
        $this->assertNotNull($variant->exam_course_id);
        $exam = Course::query()->findOrFail($variant->exam_course_id);
        $this->assertSame(CourseStatusEnum::PUBLISHED, $exam->status);
        $this->assertFalse((bool) $exam->findable, 'the exam never shows in the catalogue');
        $this->assertStringEndsWith('(exam)', $exam->title);
        $setting = app(CourseSettings::class)->for($exam->getKey());
        $this->assertTrue($setting->mandatory);
        $this->assertSame(14, $setting->default_due_days);

        $assignment = Assignment::query()->where('user_id', $engineers[0]->getKey())->where('source', 'policy_change')->sole();
        $this->assertSame($exam->getKey(), $assignment->course_id);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $assignment->due_at->timestamp, 5);
        $this->assertSame('revoked', $this->certificateOf($engineers[0], $variant->course_id)->status());
        $this->assertSame(2, PolicyReassignment::query()->where('kind', 'exam')->count());
        // the department course itself still carries the updated text
        $this->assertStringContainsString('two days a week', json_encode(Session::query()->findOrFail($variant->session_id)->currentVersion->document));
    }

    public function testTheExamCourseIsTheCitedFinalTestOfTheUpdatedDepartmentCourse(): void
    {
        [$owner, $policy, , , $version] = $this->inReview();
        $this->approveVersion($owner, $policy->id, 2, ['change_class' => 'minor', 'reason' => 'Wording only.'])->assertOk();

        $variant = $policy->variants[0]->refresh();
        $department = Session::query()->findOrFail($variant->session_id)->currentVersion->document;
        $exam = Session::query()->findOrFail($variant->exam_session_id);
        $document = $exam->currentVersion->document;

        $this->assertSame($department['finalTest'], $document['finalTest']);
        $this->assertCount(1, $document['modules']);
        $this->assertCount(1, $document['modules'][0]['lessons']);
        $this->assertSame([], $document['modules'][0]['lessons'][0]['blocks']);
        $this->assertNull($document['pages']['landing']);
        $this->assertSame([], app(\Ulams\CourseBuilder\Blueprint\SchemaRegistry::class)->validateBlueprint($document));
        $known = array_fill_keys(\Ulams\CourseBuilder\Models\Fragment::query()->where('source_id', $variant->source_id)->pluck('id')->all(), true);
        $this->assertSame([], array_values(array_filter(\Ulams\CourseBuilder\Blueprint\Checks::blueprint($document, $known), fn ($e) => !str_starts_with($e, 'warning'))));
        // the department course is a different course from the exam
        $this->assertNotSame($variant->course_id, $variant->exam_course_id);
    }

    public function testFinishingTheExamClosesTheAssignmentAndLinksTheReplacementCertificateToTheNewVersion(): void
    {
        [$owner, $policy, $engineers, , $version] = $this->inReview();
        $this->approveVersion($owner, $policy->id, 2, ['change_class' => 'minor', 'reason' => 'Wording only.'])->assertOk();
        $variant = $policy->variants[0]->refresh();

        $this->finish($engineers[0], $variant->exam_course_id);

        $assignment = Assignment::query()->where('user_id', $engineers[0]->getKey())->where('course_id', $variant->exam_course_id)->sole();
        $this->assertSame(AssignmentStatus::COMPLETED, $assignment->status);
        $this->assertNotNull($assignment->certificate_id);
        $link = PolicyCertificate::query()->where('fabric_pdf_id', $assignment->certificate_id)->sole();
        $this->assertSame($version->id, $link->policy_version_id);
        $this->assertSame($variant->id, $link->variant_id);

        $people = collect($this->actingAs($owner, 'api')->getJson("/api/admin/policies/{$policy->id}/dashboard")->assertOk()->json('data.people'))->keyBy('user.name');
        $ada = $people['Ada Engineer'];
        $this->assertSame('completed', $ada['status']);
        $this->assertSame(2, $ada['policyVersion']);
        $this->assertTrue($ada['exam']);
        $this->assertNull($ada['courseVersion'], 'the exam has no course releases');
        $this->assertSame('valid', $ada['certificate']['status']);
    }

    public function testTheDashboardCountsEachPersonOnceAfterAChange(): void
    {
        [$owner, $policy, $engineers, , $version] = $this->inReview();
        $this->approveVersion($owner, $policy->id, 2, ['change_class' => 'minor', 'reason' => 'Wording only.'])->assertOk();

        $data = $this->actingAs($owner, 'api')->getJson("/api/admin/policies/{$policy->id}/dashboard")->assertOk()->json('data');

        [$eng, $sales] = $data['departments'];
        $this->assertSame(['assigned' => 2, 'completed' => 0, 'pending' => 2, 'overdue' => 0], array_intersect_key($eng, array_flip(['assigned', 'completed', 'pending', 'overdue'])));
        $this->assertSame(['assigned' => 1, 'completed' => 0, 'pending' => 1, 'overdue' => 0], array_intersect_key($sales, array_flip(['assigned', 'completed', 'pending', 'overdue'])));
        $this->assertCount(3, $data['people']);
        $this->assertSame(2, $data['policy']['currentVersion']);
    }

    public function testEveryRevokedPersonIsToldOnceWithTheSourceDiffAndTheOwnerGetsTheCounts(): void
    {
        $retraining = [];
        $approved = [];
        Event::listen(PolicyRetrainingAssigned::class, function ($e) use (&$retraining) { $retraining[] = $e; });
        Event::listen(PolicyChangeApproved::class, function ($e) use (&$approved) { $approved[] = $e; });
        [$owner, $policy, $engineers, $seller, $version] = $this->inReview();

        $this->approveVersion($owner, $policy->id, 2)->assertOk();

        $this->assertCount(2, $retraining);
        $this->assertEqualsCanonicalizing([$engineers[0]->getKey(), $seller->getKey()], array_map(fn ($e) => $e->getUser()->getKey(), $retraining));
        $first = $retraining[0];
        $this->assertSame('course', $first->kind);
        $this->assertStringStartsWith('Sections 2 changed', $first->summary);
        $this->assertSame(2, $first->version->number);
        $this->assertNotNull(PolicyReassignment::query()->where('user_id', $first->getUser()->getKey())->value('notified_at'));
        $this->assertCount(1, $approved);
        $this->assertSame($owner->getKey(), $approved[0]->getUser()->getKey());
        $this->assertSame([['department' => 'Engineering', 'revoked' => 1, 'reassigned' => 1], ['department' => 'Sales', 'revoked' => 1, 'reassigned' => 1]], $approved[0]->departments);
    }

    public function testARerunOfTheRolloutChangesAndSendsNothingTwice(): void
    {
        $retraining = [];
        Event::listen(PolicyRetrainingAssigned::class, function ($e) use (&$retraining) { $retraining[] = $e; });
        [$owner, $policy, , , $version] = $this->inReview();
        $this->approveVersion($owner, $policy->id, 2)->assertOk();
        $assignments = Assignment::query()->count();
        $certificates = FabricPDF::query()->whereNotNull('revoked_at')->count();

        // a worker crashed after the last step and the version was put back to "applying"
        $version->refresh()->forceFill(['status' => PolicyVersion::APPLYING])->save();
        app(RolloutService::class)->run($version);
        app(RolloutService::class)->run($version->refresh());

        $this->assertSame($assignments, Assignment::query()->count());
        $this->assertSame($certificates, FabricPDF::query()->whereNotNull('revoked_at')->count());
        $this->assertCount(2, $retraining);
        $this->assertSame(2, PolicyReassignment::query()->count());
        $this->assertSame(PolicyVersion::APPROVED, $version->refresh()->status);
        $this->assertSame(2, AuditEntry::query()->where('action', 'policy.reassigned')->count(), 'a second run never reassigns anybody');
        $this->assertSame(2, AuditEntry::query()->where('action', 'policy.certificate.revoked')->count());
    }

    public function testARunThatStoppedBetweenRevokingAndAssigningResumes(): void
    {
        [$owner, $policy, $engineers, , $version] = $this->inReview();
        $this->approveVersion($owner, $policy->id, 2)->assertOk();
        // as if the worker died after revoking Ada's certificate and before assigning
        $adaAssignment = Assignment::query()->where('user_id', $engineers[0]->getKey())->where('source', 'policy_change')->sole();
        PolicyReassignment::query()->where('user_id', $engineers[0]->getKey())->update(['assignment_id' => null, 'notified_at' => null]);
        $adaAssignment->forceFill(['status' => AssignmentStatus::CANCELLED])->save();
        $version->refresh()->forceFill(['status' => PolicyVersion::APPLYING])->save();

        app(RolloutService::class)->run($version);

        $row = PolicyReassignment::query()->where('user_id', $engineers[0]->getKey())->sole();
        $this->assertNotNull($row->assignment_id);
        $this->assertNotNull($row->revoked_certificate_id);
        $this->assertSame(AssignmentStatus::ASSIGNED, Assignment::query()->findOrFail($row->assignment_id)->status);
        $this->assertSame(PolicyVersion::APPROVED, $version->refresh()->status);
    }

    public function testNoDepartmentIsRolledOutWhenOneCannotBeUpdated(): void
    {
        [$owner, $policy, $engineers, , $version] = $this->inReview();
        // someone edited a lesson of the Sales course in the admin after it was applied: applying would overwrite it
        $sales = Session::query()->findOrFail($policy->variants[1]->session_id);
        $entry = \Ulams\CourseBuilder\Models\EntityMapEntry::query()->where('session_id', $sales->id)->where('entity_type', 'topic')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('topics')->where('id', $entry->entity_id)->update(['title' => 'Edited by hand in the admin', 'updated_at' => now()->addMinutes(5)]);

        $this->approveVersion($owner, $policy->id, 2)->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Sales: these elements were edited in the admin') && !str_contains($m, 'Engineering'));

        $this->assertSame(PolicyVersion::IN_REVIEW, $version->refresh()->status);
        $this->assertSame(['ready', 'ready'], PolicyRollout::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(0, Assignment::query()->where('source', 'policy_change')->count());
        $this->assertNull(FabricPDF::query()->whereNotNull('revoked_at')->first());
        $this->assertSame(1, Policy::query()->findOrFail($policy->id)->versions()->where('status', 'approved')->count());

        // the owner decides to overwrite the hand edit: now it goes through, all departments together
        $this->approveVersion($owner, $policy->id, 2, ['overwrite' => true])->assertOk();
        $this->assertSame(PolicyVersion::APPROVED, $version->refresh()->status);
        $this->assertSame(2, Assignment::query()->where('source', 'policy_change')->count());
    }

    public function testAnApplyThatFailsReturnsTheVersionToReviewAndApprovingAgainResumes(): void
    {
        [$owner, $policy, , , $version] = $this->inReview();
        $rollout = PolicyRollout::query()->where('policy_version_id', $version->id)->orderBy('id')->first();
        $proposal = Proposal::query()->findOrFail($rollout->proposal_id);
        // the apply run of the first department started and failed: its proposal is open again with the error
        $version->forceFill(['status' => PolicyVersion::APPLYING])->save();
        $rollout->forceFill(['status' => PolicyRollout::APPLYING])->save();
        $proposal->forceFill(['error' => 'The updated course would not pass its checks.'])->save();

        app(\Ulams\PolicyLifecycle\Services\RolloutCoordinator::class)->applyFailed($proposal->session_id);

        $this->assertSame(PolicyRollout::FAILED, $rollout->refresh()->status);
        $this->assertSame('The updated course would not pass its checks.', $rollout->error);
        $this->assertSame(PolicyVersion::IN_REVIEW, $version->refresh()->status);
        $this->assertSame(0, Assignment::query()->where('source', 'policy_change')->count());
        $shown = collect($this->actingAs($owner, 'api')->getJson("/api/admin/policies/{$policy->id}/versions/2")->assertOk()->json('data.rollouts'))->firstWhere('status', 'failed');
        $this->assertSame('The updated course would not pass its checks.', $shown['error']);

        $this->approveVersion($owner, $policy->id, 2)->assertOk();
        $this->assertSame(PolicyVersion::APPROVED, $version->refresh()->status);
        $this->assertSame(['applied', 'applied'], PolicyRollout::query()->orderBy('id')->pluck('status')->all());
    }

    public function testLoweringTheClassNeedsAReasonAndRaisingDoesNot(): void
    {
        [$owner, $policy, , , $version] = $this->inReview();

        $this->approveVersion($owner, $policy->id, 2, ['change_class' => 'minor'])->assertStatus(422)
            ->assertJsonPath('message', 'The changes suggest a major change. To approve it as minor, say why.');
        $this->assertSame(PolicyVersion::IN_REVIEW, $version->refresh()->status);
        $this->approveVersion($owner, $policy->id, 2, ['change_class' => 'minor', 'reason' => '  '])->assertStatus(422);

        $this->approveVersion($owner, $policy->id, 2, ['change_class' => 'major'])->assertOk();
        $this->assertSame('major', $version->refresh()->change_class);
        $this->assertNull($version->class_reason);
    }

    public function testApprovalWaitsForEveryDecisionOfTheOwner(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);
        $this->submitChange($owner, $policy->id)->assertCreated();

        $this->approveVersion($owner, $policy->id, 2)->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Engineering: ') && str_contains($m, 'still wait for your decision') && str_contains($m, 'Sales: '));
        $this->assertSame(0, Assignment::query()->where('source', 'policy_change')->count());
    }

    public function testAnotherOwnerAndPeopleWithoutPermissionCannotDecide(): void
    {
        [$owner, $policy] = $this->inReview();
        $stranger = $this->author();
        $stranger->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('policy_owner', 'api'));

        $this->approveVersion($stranger, $policy->id, 2)->assertForbidden();
        $this->actingAs($stranger, 'api')->postJson("/api/admin/policies/{$policy->id}/versions/2/reject", ['reason' => 'No'])->assertForbidden();
        $this->approveVersion($this->student(), $policy->id, 2)->assertForbidden();
        $this->assertSame(PolicyVersion::IN_REVIEW, PolicyVersion::query()->where('policy_id', $policy->id)->where('number', 2)->value('status'));
    }

    public function testRejectingAChangeKeepsTheCurrentVersionAndRetrainsNobody(): void
    {
        [$owner, $policy, , , $version] = $this->inReview();

        $this->actingAs($owner, 'api')->postJson("/api/admin/policies/{$policy->id}/versions/2/reject", ['reason' => 'The new limit was sent by mistake.'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $version->refresh();
        $this->assertSame(PolicyVersion::REJECTED, $version->status);
        $this->assertSame($owner->getKey(), $version->rejected_by);
        $this->assertSame('The new limit was sent by mistake.', $version->reject_reason);
        $this->assertSame(1, Policy::query()->findOrFail($policy->id)->currentVersion->number);
        $this->assertSame(['rejected', 'rejected'], PolicyRollout::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(0, Assignment::query()->where('source', 'policy_change')->count());
        $this->assertNull(FabricPDF::query()->whereNotNull('revoked_at')->first());
        $this->assertSame('policy.version.rejected', AuditEntry::query()->orderByDesc('id')->value('action'));

        // a rejected version cannot be approved, and a corrected file can be uploaded as a new version
        $this->approveVersion($owner, $policy->id, 2)->assertStatus(409);
        $this->submitChange($owner, $policy->id, 'remote-work-policy.intro.md')->assertCreated()->assertJsonPath('data.number', 3);
    }

    public function testTheInitialVersionCannotBeRejectedAndAnApprovedOneNeitherCanBe(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);

        $this->actingAs($owner, 'api')->postJson("/api/admin/policies/{$policy->id}/versions/1/reject", ['reason' => 'Not that either'])->assertStatus(409);
        $this->actingAs($owner, 'api')->postJson("/api/admin/policies/{$policy->id}/versions/1/reject", [])->assertStatus(422);
    }

    public function testAChangeInATextNoCourseCitesRollsOutAtOnceWithoutRetrainingAnyone(): void
    {
        $owner = $this->admin();
        [$policy, $engineers] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);
        $this->submitChange($owner, $policy->id, 'remote-work-policy.intro.md')->assertCreated();

        $this->approveVersion($owner, $policy->id, 2)->assertOk();

        $version = PolicyVersion::query()->where('policy_id', $policy->id)->where('number', 2)->firstOrFail();
        $this->assertSame(PolicyVersion::APPROVED, $version->status);
        $this->assertSame($version->id, $policy->refresh()->current_version_id);
        $this->assertSame('valid', $this->certificateOf($engineers[0], $policy->variants[0]->course_id)->status());
        $this->assertSame(0, Assignment::query()->where('source', 'policy_change')->count());
        $this->assertSame(0, PolicyReassignment::query()->count());
    }

    public function testALearnerSeesWhyTheyOweTheTrainingAgainAndNobodyElsesRows(): void
    {
        [$owner, $policy, $engineers, $seller] = $this->inReview();
        $this->approveVersion($owner, $policy->id, 2, ['change_class' => 'minor', 'reason' => 'Wording only.'])->assertOk();

        $mine = $this->actingAs($engineers[0], 'api')->getJson('/api/policies/my-changes')->assertOk()->json('data');

        $this->assertCount(1, $mine);
        $this->assertSame('Remote work and data handling', $mine[0]['policy']['title']);
        $this->assertSame(2, $mine[0]['version']);
        $this->assertSame('exam', $mine[0]['kind']);
        $this->assertStringStartsWith('Sections 2 changed', $mine[0]['summary']);
        $this->assertSame(Assignment::query()->where('user_id', $engineers[0]->getKey())->where('source', 'policy_change')->value('id'), $mine[0]['assignmentId']);
        $this->assertSame('assigned', $mine[0]['status']);
        // Linus never finished and was not reassigned; a person of another department sees only their own
        $this->actingAs($engineers[1], 'api')->getJson('/api/policies/my-changes')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(1, count($this->actingAs($seller, 'api')->getJson('/api/policies/my-changes')->json('data')));
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson('/api/policies/my-changes')->assertUnauthorized();
    }

    public function testEveryStepIsInTheAuditChainAndTheChainVerifies(): void
    {
        [$owner, $policy] = $this->inReview();
        $this->approveVersion($owner, $policy->id, 2)->assertOk();

        $actions = AuditEntry::query()->where('subject_type', 'policy')->orderBy('id')->pluck('action')->all();
        foreach (['policy.created', 'policy.version.approved', 'policy.version.submitted', 'policy.certificate.revoked', 'policy.reassigned', 'policy.rollout.applied'] as $expected) {
            $this->assertContains($expected, $actions);
        }
        $approved = AuditEntry::query()->where('action', 'policy.version.approved')->orderByDesc('id')->firstOrFail();
        $this->assertSame(2, $approved->version_to);
        $this->assertSame('major', $approved->data['change_class']);
        $this->assertFalse($approved->data['actor_for_owner']);
        $this->assertContains('policy.reassigned', $actions);
        $this->assertTrue(app(\Ulams\LivingCourse\Services\AuditLog::class)->verify()['ok'] ?? true);
    }
}
