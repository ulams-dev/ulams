<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Ulams\LivingCourse\Models\Proposal;
use Ulams\PolicyLifecycle\Models\PolicyRollout;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Tests\Support\TrainsPeople;
use Ulams\PolicyLifecycle\Tests\TestCase;

class ChangeSubmissionTest extends TestCase
{
    use TrainsPeople;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder::class);
    }

    public function testANewFileBecomesAVersionInReviewWithAProposalPerDepartment(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);

        $response = $this->submitChange($owner, $policy->id)->assertCreated();

        $data = $response->json('data');
        $this->assertSame(2, $data['number']);
        $this->assertSame('in_review', $data['status']);
        $this->assertCount(2, $data['rollouts']);
        foreach ($data['rollouts'] as $rollout) {
            $this->assertSame('ready', $rollout['status'], json_encode($rollout));
            $this->assertNotNull($rollout['proposal']['id']);
            $this->assertGreaterThan(0, $rollout['undecided']);
        }
        $this->assertSame(['Engineering', 'Sales'], array_column($data['rollouts'], 'department'));
        // the policy in force is still version 1 until the change is approved and rolled out
        $this->assertSame(1, $this->actingAs($owner, 'api')->getJson("/api/admin/policies/{$policy->id}")->json('data.currentVersion'));
        $this->assertSame(2, Proposal::query()->count());
    }

    public function testTheSourceDiffIsBuiltFromTheStoredFragmentChangesAndListsTheChangedSections(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);
        $data = $this->submitChange($owner, $policy->id)->assertCreated()->json('data');

        $this->assertStringStartsWith('Sections 2 changed', $data['summary']);
        $this->assertStringContainsString('1.1 Approval and working hours', $data['summary']);
        $this->assertStringContainsString('2.2 Sharing and retention', $data['summary']);
        $this->assertSame(2, $data['changes']['counts']['changed']);
        $labels = array_column($data['changes']['sections'], 'label');
        $this->assertContains('§1.1 Approval and working hours', $labels);
        $this->assertNotNull($data['changes']['sections'][0]['fragmentId']);
    }

    public function testTheClassIsSuggestedFromTheProposalsWithReasons(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);
        $data = $this->submitChange($owner, $policy->id)->assertCreated()->json('data');

        $this->assertSame('major', $data['suggestion']['class']);
        $this->assertNotEmpty($data['suggestion']['reasons']);
        $this->assertStringContainsString('Lesson 1.1', implode(' ', $data['suggestion']['reasons']));
        $this->assertNull($data['suggestedClass'], 'the suggestion is stored only when the owner decides');
    }

    public function testAChangeInATextNoCourseCitesIsNoChangeForEveryDepartment(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);

        $data = $this->submitChange($owner, $policy->id, 'remote-work-policy.intro.md')->assertCreated()->json('data');

        $this->assertSame(['no_change', 'no_change'], array_column($data['rollouts'], 'status'));
        $this->assertSame([], $data['blockers']);
        $this->assertSame('minor', $data['suggestion']['class']);
        $this->assertSame(0, Proposal::query()->where('status', 'ready')->count());
    }

    public function testTheCurrentFileOrANotYetApprovedPolicyIsRefused(): void
    {
        $owner = $this->admin();
        $id = $this->createPolicy($owner);
        $this->submitChange($owner, $id)->assertStatus(409)->assertJsonPath('message', 'Approve the first version before uploading a change.');

        [$policy] = $this->trainedPolicy($owner);
        $this->submitChange($owner, $policy->id, 'remote-work-policy.md')->assertStatus(409)->assertJsonPath('message', 'This file is the current version of the policy.');
        $this->assertSame(1, PolicyVersion::query()->where('policy_id', $policy->id)->count());
    }

    public function testOnlyOneChangeWaitsForADecisionAtATime(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);
        $this->submitChange($owner, $policy->id)->assertCreated();

        $this->submitChange($owner, $policy->id, 'remote-work-policy.intro.md')->assertStatus(409)
            ->assertJsonPath('message', 'Another version is waiting for a decision. Approve or reject it first.');
        $this->assertSame(2, PolicyVersion::query()->where('policy_id', $policy->id)->count());
    }

    public function testASubmissionThatChangesNoTextLeavesNoVersionBehind(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);
        // the same words with different spacing: the normalised text is the same
        $spaced = $this->policyFile();
        file_put_contents($spaced->getRealPath(), str_replace(' ', '  ', file_get_contents($spaced->getRealPath())));

        $this->actingAs($owner, 'api')->post("/api/admin/policies/{$policy->id}/versions", ['file' => $spaced], ['Accept' => 'application/json'])
            ->assertStatus(409)->assertJsonPath('message', 'The text of this file is the same as the current version; nothing would change.');

        $this->assertSame(1, PolicyVersion::query()->where('policy_id', $policy->id)->count());
        $this->assertSame(0, PolicyRollout::query()->count());
    }

    public function testOnlyTheOwnerOfThePolicyMaySubmit(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);
        $stranger = $this->author();
        $stranger->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('policy_owner', 'api'));

        $this->submitChange($stranger, $policy->id)->assertForbidden();
        $this->submitChange($this->student(), $policy->id)->assertForbidden();
    }

    public function testTheReviewShowsWhatApprovingWillDoPerDepartment(): void
    {
        $owner = $this->admin();
        [$policy, $engineers] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);
        $this->submitChange($owner, $policy->id)->assertCreated();

        $minor = $this->actingAs($owner, 'api')->getJson("/api/admin/policies/{$policy->id}/versions/2?class=minor")->assertOk()->json('data.preflight');
        $this->assertSame('minor', $minor['changeClass']);
        $this->assertSame([1, 0], array_column($minor['departments'], 'certificatesToRevoke'));
        $this->assertSame([1, 0], array_column($minor['departments'], 'peopleToReassign'));
        $this->assertSame(['exam', 'exam'], array_column($minor['departments'], 'assigns'));
        $major = $this->actingAs($owner, 'api')->getJson("/api/admin/policies/{$policy->id}/versions/2")->assertOk()->json('data.preflight');
        $this->assertSame('major', $major['changeClass']);
        $this->assertSame(['course', 'course'], array_column($major['departments'], 'assigns'));
    }
}
