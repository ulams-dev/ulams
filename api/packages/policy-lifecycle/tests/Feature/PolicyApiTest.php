<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Ulams\Auth\Models\Group;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Services\AuditLog;
use Ulams\PolicyLifecycle\Enums\PolicyPermissionsEnum;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Tests\TestCase;

class PolicyApiTest extends TestCase
{
    /** A user who owns policies but manages none (the `policy_owner` permission only). */
    private function owner(): object
    {
        $user = $this->author();
        Permission::findOrCreate(PolicyPermissionsEnum::OWNER, 'api');
        $user->givePermissionTo(PolicyPermissionsEnum::OWNER);

        return $user;
    }

    public function testAnOwnerCreatesAPolicyWithAFirstVersionAndOneVariantPerDepartment(): void
    {
        $owner = $this->owner();
        $id = $this->createPolicy($owner);

        $policy = Policy::query()->findOrFail($id);
        $this->assertSame($owner->getKey(), $policy->owner_user_id);
        $this->assertSame(['totalMinutes' => 30, 'lessonMinutes' => 10, 'language' => 'en', 'tone' => 'professional'], $policy->brief);
        $version = $policy->versions->sole();
        $this->assertSame(1, $version->number);
        $this->assertSame(PolicyVersion::DRAFT, $version->status);
        $this->assertSame(PolicyVersion::INITIAL, $version->change_class);
        $this->assertSame(hash_file('sha256', __DIR__ . '/../../resources/fixtures/remote-work-policy.md'), $version->file_sha256);
        Storage::disk('course_builder_private')->assertExists($version->file_path);

        $this->assertSame(['Engineering', 'Sales'], $policy->variants->pluck('department_label')->all());
        $engineering = $policy->variants[0];
        $this->assertSame(['seniority' => 'senior', 'priorExperience' => 'expert'], $engineering->brief);
        $this->assertNotNull(Group::query()->find($engineering->group_id));
        $this->assertNull($engineering->session_id);
        $this->assertNull($engineering->course_id);
    }

    public function testCreatingAPolicyIsWrittenToTheAuditChain(): void
    {
        $owner = $this->owner();
        $id = $this->createPolicy($owner);

        $entry = AuditEntry::query()->where('action', 'policy.created')->latest('id')->firstOrFail();
        $this->assertSame('policy', $entry->subject_type);
        $this->assertSame($id, $entry->subject_id);
        $this->assertSame($owner->getKey(), $entry->actor_id);
        $this->assertSame(['Engineering', 'Sales'], $entry->data['departments']);
        $this->assertSame(1, $entry->version_to);
        $this->assertTrue(app(AuditLog::class)->verify()['ok'] ?? true);
    }

    public function testAnOwnerSeesOnlyTheirOwnPoliciesAndAManagerSeesAll(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $first = $this->createPolicy($mine, ['title' => 'Mine']);
        $second = $this->createPolicy($other, ['title' => 'Theirs']);

        $this->actingAs($mine, 'api')->getJson('/api/admin/policies')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first);
        $this->actingAs($mine, 'api')->getJson("/api/admin/policies/{$second}")->assertForbidden();
        $this->actingAs($mine, 'api')->getJson("/api/admin/policies/{$second}/dashboard")->assertForbidden();
        $this->actingAs($mine, 'api')->postJson("/api/admin/policies/{$second}/variants/generate")->assertForbidden();
        $this->actingAs($this->admin(), 'api')->getJson('/api/admin/policies')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->admin(), 'api')->getJson("/api/admin/policies/{$second}")->assertOk()->assertJsonPath('data.title', 'Theirs');
    }

    public function testPeopleWithoutAPolicyPermissionAreRefused(): void
    {
        $id = $this->createPolicy($this->admin());
        foreach ([$this->student(), $this->author()] as $user) {
            $this->actingAs($user, 'api')->getJson('/api/admin/policies')->assertForbidden();
            $this->actingAs($user, 'api')->getJson("/api/admin/policies/{$id}")->assertForbidden();
            $this->actingAs($user, 'api')->post('/api/admin/policies', $this->policyPayload(), ['Accept' => 'application/json'])->assertForbidden();
        }
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson('/api/admin/policies')->assertUnauthorized();
    }

    public function testAnOwnerCannotCreateAPolicyForSomeoneElse(): void
    {
        $owner = $this->owner();
        $other = $this->author();
        $id = $this->createPolicy($owner, ['owner_user_id' => $other->getKey()]);
        $this->assertSame($owner->getKey(), Policy::query()->findOrFail($id)->owner_user_id);

        $adminId = $this->createPolicy($this->admin(), ['owner_user_id' => $other->getKey()]);
        $this->assertSame($other->getKey(), Policy::query()->findOrFail($adminId)->owner_user_id);
    }

    public function testDepartmentsAreValidated(): void
    {
        $owner = $this->owner();
        $post = fn (array $departments) => $this->actingAs($owner, 'api')->post('/api/admin/policies', $this->policyPayload(['departments' => $departments]), ['Accept' => 'application/json']);

        $post([])->assertStatus(422);
        $post([['label' => 'Sales'], ['label' => 'sales']])->assertStatus(422)->assertJsonPath('message', 'The department "sales" is listed twice.');
        $post([['label' => 'Sales', 'seniority' => 'wizard']])->assertStatus(422);
        $post([['label' => 'Sales', 'group_id' => 999999]])->assertStatus(422);
        $this->assertSame(0, Policy::query()->count());
    }

    public function testAnExistingGroupIsUsedAsTheAudience(): void
    {
        $group = Group::query()->create(['name' => 'Sales EMEA', 'registerable' => false]);
        $id = $this->createPolicy($this->admin(), ['departments' => [['label' => 'Sales', 'group_id' => $group->getKey()]]]);

        $this->assertSame($group->getKey(), Policy::query()->findOrFail($id)->variants->sole()->group_id);
        $this->assertSame(1, Group::query()->where('name', 'Sales EMEA')->count());
    }

    public function testAFileThatIsNotADocumentIsRejected(): void
    {
        $bad = UploadedFile::fake()->createWithContent('policy.pdf', 'this is not a pdf');

        $this->actingAs($this->admin(), 'api')->post('/api/admin/policies', $this->policyPayload(['file' => $bad]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame(0, Policy::query()->count());
    }

    public function testTheShowResponseCarriesTheCostEstimateBeforeAnythingIsGenerated(): void
    {
        $admin = $this->admin();
        $id = $this->createPolicy($admin, ['brief' => ['totalMinutes' => 60, 'lessonMinutes' => 10]]);

        $data = $this->actingAs($admin, 'api')->getJson("/api/admin/policies/{$id}")->assertOk()->json('data');
        $this->assertCount(2, $data['estimate']['perVariant']);
        $this->assertSame(6, $data['estimate']['perVariant'][0]['lessons']);
        $this->assertGreaterThan(0, $data['estimate']['totalMicroUsd']);
        $this->assertSame(['Engineering', 'Sales'], array_column($data['variants'], 'department'));
        $this->assertSame('not_started', $data['variants'][0]['stage']);
        $this->assertCount(1, $this->actingAs($admin, 'api')->getJson("/api/admin/policies/{$id}/versions")->assertOk()->json('data'));
    }
}
