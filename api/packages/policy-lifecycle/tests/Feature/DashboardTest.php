<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Ulams\Auth\Models\Group;
use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User as CoreUser;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Tests\Models\User;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyCertificate;
use Ulams\PolicyLifecycle\Tests\TestCase;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Models\FabricPDF;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(TemplatesPdfSeeder::class);
    }

    /**
     * An approved policy with two engineers and one salesperson enrolled by the departments' groups.
     *
     * @return array{0:Policy,1:array<int,User>,2:User}
     */
    private function trainedPolicy($owner): array
    {
        $id = $this->createPolicy($owner);
        $policy = Policy::query()->findOrFail($id);
        $engineers = [$this->named('Ada', 'Engineer'), $this->named('Linus', 'Engineer')];
        $seller = $this->named('Sam', 'Seller');
        foreach ($engineers as $user) {
            Group::query()->findOrFail($policy->variants[0]->group_id)->users()->attach($user->getKey());
        }
        Group::query()->findOrFail($policy->variants[1]->group_id)->users()->attach($seller->getKey());
        $this->generateAll($owner, $id);
        foreach ($policy->refresh()->variants as $variant) {
            $this->reviewAndPublish($owner, Session::query()->findOrFail($variant->session_id));
        }
        $this->actingAs($owner, 'api')->postJson("/api/admin/policies/{$id}/versions/1/approve")->assertOk();

        return [$policy->refresh(), $engineers, $seller];
    }

    private function named(string $first, string $last): User
    {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $user->assignRole(\Ulams\Core\Enums\UserRole::STUDENT);

        return $user;
    }

    private function finish(User $student, int $courseId): void
    {
        CourseUserPivot::query()->where('user_id', $student->getKey())->where('course_id', $courseId)->update(['finished' => true]);
        event(new CourseFinished(CoreUser::find($student->getKey()), Course::query()->findOrFail($courseId)));
    }

    private function dashboard($user, Policy $policy, string $query = '')
    {
        return $this->actingAs($user, 'api')->getJson("/api/admin/policies/{$policy->id}/dashboard{$query}");
    }

    public function testDepartmentsShowAssignedCompletedPendingAndOverdue(): void
    {
        $owner = $this->admin();
        [$policy, $engineers, $seller] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);
        Assignment::query()->where('user_id', $engineers[1]->getKey())->update(['due_at' => now()->subDay()]);

        $data = $this->dashboard($owner, $policy)->assertOk()->json('data');

        $this->assertSame(1, $data['policy']['currentVersion']);
        [$eng, $sales] = $data['departments'];
        $this->assertSame('Engineering', $eng['department']);
        $this->assertSame(['assigned' => 2, 'completed' => 1, 'pending' => 0, 'overdue' => 1], array_intersect_key($eng, array_flip(['assigned', 'completed', 'pending', 'overdue'])));
        $this->assertSame(0.5, $eng['completionRate']);
        $this->assertSame('applied', $eng['stage']);
        $this->assertSame(['assigned' => 1, 'completed' => 0, 'pending' => 1, 'overdue' => 0], array_intersect_key($sales, array_flip(['assigned', 'completed', 'pending', 'overdue'])));
        $this->assertCount(3, $data['people']);
    }

    public function testEachPersonShowsCourseVersionPolicyVersionAndCertificate(): void
    {
        $owner = $this->admin();
        [$policy, $engineers] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);

        $people = collect($this->dashboard($owner, $policy)->assertOk()->json('data.people'))->keyBy('user.name');
        $done = $people['Ada Engineer'];
        $this->assertSame('completed', $done['status']);
        $this->assertSame('Engineering', $done['department']);
        $this->assertSame(1, $done['courseVersion']);
        $this->assertSame(1, $done['policyVersion']);
        $this->assertSame('valid', $done['certificate']['status']);
        $this->assertStringStartsWith('/api/certificates/verify/', $done['certificate']['verifyPath']);
        $open = $people['Linus Engineer'];
        $this->assertSame('assigned', $open['status']);
        $this->assertNull($open['policyVersion']);
        $this->assertNull($open['certificate']);
        $this->assertSame(1, $open['courseVersion']);
        $this->assertSame(1, Release::query()->where('session_id', $policy->variants[0]->session_id)->count());
    }

    public function testACertificateIsLinkedToThePolicyVersionInForceOnlyForPolicyCourses(): void
    {
        $owner = $this->admin();
        [$policy, $engineers] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);

        $link = PolicyCertificate::query()->sole();
        $this->assertSame($policy->current_version_id, $link->policy_version_id);
        $this->assertSame($policy->variants[0]->id, $link->variant_id);
        $this->assertSame($engineers[0]->getKey(), $link->user_id);

        $other = Course::factory()->create(['status' => 'published']);
        FabricPDF::factory()->createOne(['user_id' => $engineers[1]->getKey(), 'assignable_type' => Course::class, 'assignable_id' => $other->getKey()]);
        $this->assertSame(1, PolicyCertificate::query()->count());
    }

    public function testFiltersByDepartmentAndStatus(): void
    {
        $owner = $this->admin();
        [$policy, $engineers] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);

        $sales = $this->dashboard($owner, $policy, '?variant=' . $policy->variants[1]->id)->assertOk()->json('data.people');
        $this->assertSame(['Sam Seller'], array_column(array_column($sales, 'user'), 'name'));
        $completed = $this->dashboard($owner, $policy, '?status=completed')->assertOk()->json('data.people');
        $this->assertSame(['Ada Engineer'], array_column(array_column($completed, 'user'), 'name'));
        $this->dashboard($owner, $policy, '?status=assigned')->assertOk()->assertJsonCount(2, 'data.people');
        $this->dashboard($owner, $policy, '?status=nonsense')->assertStatus(422);
        $this->dashboard($owner, $policy, '?per_page=2')->assertOk()->assertJsonCount(2, 'data.people')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);
    }

    public function testTheDashboardIsForTheOwnerOfThatPolicyOnly(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);
        $stranger = $this->author();
        $stranger->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('policy_owner', 'api'));

        $this->dashboard($stranger, $policy)->assertForbidden();
        $this->actingAs($stranger, 'api')->get("/api/admin/policies/{$policy->id}/dashboard/export")->assertForbidden();
        $this->dashboard($this->student(), $policy)->assertForbidden();
    }

    public function testTheCsvExportListsEveryMatchingPersonAndIsRecorded(): void
    {
        $owner = $this->admin();
        [$policy, $engineers] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);
        // a spreadsheet formula in a name must not run when the file is opened
        $engineers[1]->forceFill(['first_name' => '=HYPERLINK("http://evil.example")'])->save();

        $response = $this->actingAs($owner, 'api')->get("/api/admin/policies/{$policy->id}/dashboard/export?status=assigned");
        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $lines = array_values(array_filter(preg_split('/\r?\n/', ltrim($csv, "\xEF\xBB\xBF"))));
        $this->assertSame('department,user_id,name,email,status,assigned_at,due_at,completed_at,course_id,course_version,policy_version,certificate_id,certificate_status,certificate_expires_at', $lines[0]);
        $this->assertCount(3, $lines, 'two open assignments and the header');
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Ada Engineer', $csv, 'the completed person is filtered out');

        $export = DB::table('compliance_exports')->where('dataset', 'policy_training')->sole();
        $this->assertSame($owner->getKey(), (int) $export->user_id);
        $this->assertSame(2, (int) $export->row_count);
        $this->assertSame($policy->id, json_decode($export->filters, true)['policy_id']);
    }

    public function testBeforeApprovalTheDashboardIsEmptyButShowsEveryDepartmentsStage(): void
    {
        $owner = $this->admin();
        $id = $this->createPolicy($owner);
        $policy = Policy::query()->findOrFail($id);

        $data = $this->dashboard($owner, $policy)->assertOk()->json('data');

        $this->assertSame([], $data['people']);
        $this->assertNull($data['policy']['currentVersion']);
        $this->assertSame(['not_started', 'not_started'], array_column($data['departments'], 'stage'));
        $this->assertSame([0, 0], array_column($data['departments'], 'assigned'));
        $this->assertSame([null, null], array_column($data['departments'], 'completionRate'));
    }
}
