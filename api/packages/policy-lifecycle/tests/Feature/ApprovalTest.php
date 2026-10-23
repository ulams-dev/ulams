<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Ulams\Auth\Models\Group;
use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Models\LearningPath;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Compliance\Models\Rule;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Tests\TestCase;

class ApprovalTest extends TestCase
{
    private function approve($user, Policy $policy, int $number = 1)
    {
        return $this->actingAs($user, 'api')->postJson("/api/admin/policies/{$policy->id}/versions/{$number}/approve");
    }

    public function testAVersionCannotBeApprovedBeforeEveryDepartmentCoursePublished(): void
    {
        $owner = $this->admin();
        $id = $this->createPolicy($owner);
        $policy = Policy::query()->findOrFail($id);

        $this->approve($owner, $policy)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Engineering: the course has not been generated yet.') && str_contains($m, 'Sales: the course has not been generated yet.'));

        $this->generateAll($owner, $id);
        $this->approve($owner, $policy)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Engineering: review and apply the course in the Studio first.'));

        $this->reviewAndPublish($owner, Session::query()->findOrFail($policy->refresh()->variants[0]->session_id));
        $this->approve($owner, $policy)->assertStatus(422)->assertJsonPath('message', fn ($m) => !str_contains($m, 'Engineering') && str_contains($m, 'Sales'));
        $this->assertNull($policy->refresh()->current_version_id);
        $this->assertSame(PolicyVersion::DRAFT, $policy->versions->sole()->status);
    }

    public function testAnUnpublishedCourseBlocksApproval(): void
    {
        $owner = $this->admin();
        $policy = $this->publishedPolicy($owner);
        Course::query()->whereKey($policy->variants[1]->session->course_id ?? Session::query()->find($policy->variants[1]->session_id)->course_id)->update(['status' => CourseStatusEnum::DRAFT]);

        $this->approve($owner, $policy)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Sales: publish the course in the Studio first.'));
    }

    public function testApprovingMakesEachCourseMandatoryAndEnrolsTheGroupThroughARule(): void
    {
        $owner = $this->admin();
        $policy = $this->publishedPolicy($owner);
        $engineers = $policy->variants[0];
        $member = $this->student();
        Group::query()->findOrFail($engineers->group_id)->users()->attach($member->getKey());

        $this->approve($owner, $policy)->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.number', 1);

        $policy->refresh();
        $version = $policy->versions->sole();
        $this->assertSame($version->id, $policy->current_version_id);
        $this->assertSame($owner->getKey(), $version->approved_by);
        $this->assertNotNull($version->effective_at);

        foreach ($policy->variants as $variant) {
            $courseId = Session::query()->findOrFail($variant->session_id)->course_id;
            $this->assertSame($courseId, $variant->course_id);
            $setting = app(CourseSettings::class)->for($courseId);
            $this->assertTrue($setting->mandatory);
            $this->assertSame(30, $setting->default_due_days);
            $path = LearningPath::query()->findOrFail($variant->path_id);
            $this->assertSame([$courseId], $path->steps->pluck('course_id')->all());
            $rule = Rule::query()->findOrFail($variant->rule_id);
            $this->assertSame($path->getKey(), $rule->path_id);
            $this->assertSame(30, $rule->due_days);
            $this->assertSame([['attribute' => 'group', 'operator' => 'equals', 'value' => [Group::query()->find($variant->group_id)->name]]], $rule->conditions);
        }
        $engineers->refresh();
        $this->assertTrue(PathEnrollment::query()->where('user_id', $member->getKey())->where('path_id', $engineers->path_id)->exists());
        $assignment = Assignment::query()->where('user_id', $member->getKey())->sole();
        $this->assertSame($engineers->course_id, $assignment->course_id);
        $this->assertSame(AssignmentStatus::ASSIGNED, $assignment->status);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $assignment->due_at->timestamp, 5);
    }

    public function testAMemberWhoJoinsTheGroupLaterIsEnrolledByTheRule(): void
    {
        $owner = $this->admin();
        $policy = $this->publishedPolicy($owner);
        $this->approve($owner, $policy)->assertOk();
        $sales = $policy->refresh()->variants[1];
        $late = $this->student();

        Group::query()->findOrFail($sales->group_id)->users()->attach($late->getKey());
        $late->save();

        $this->assertSame($sales->course_id, Assignment::query()->where('user_id', $late->getKey())->sole()->course_id);
    }

    public function testAnApprovedVersionCannotBeApprovedAgain(): void
    {
        $owner = $this->admin();
        $policy = $this->publishedPolicy($owner);
        $this->approve($owner, $policy)->assertOk();
        $rules = Rule::query()->count();

        $this->approve($owner, $policy)->assertStatus(409);
        $this->approve($owner, $policy, 7)->assertNotFound();
        $this->assertSame($rules, Rule::query()->count());
    }

    public function testApprovalIsAuditedAndNoteWhenAnAdminActsForTheOwner(): void
    {
        $owner = $this->author();
        $owner->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('policy_owner', 'api'));
        $policy = $this->publishedPolicy($owner);
        $this->approve($this->admin(), $policy)->assertOk();

        $entry = AuditEntry::query()->where('action', 'policy.version.approved')->firstOrFail();
        $this->assertSame($policy->id, $entry->subject_id);
        $this->assertTrue($entry->data['actor_for_owner']);
        $this->assertSame(['Engineering', 'Sales'], $entry->data['departments']);
        $this->assertTrue(app(\Ulams\LivingCourse\Services\AuditLog::class)->verify()['ok'] ?? true);
    }

    public function testAnotherOwnerCannotApprove(): void
    {
        $owner = $this->admin();
        $policy = $this->publishedPolicy($owner);
        $other = $this->author();
        $other->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('policy_owner', 'api'));

        $this->approve($other, $policy)->assertForbidden();
        $this->assertNull($policy->refresh()->current_version_id);
    }
}
