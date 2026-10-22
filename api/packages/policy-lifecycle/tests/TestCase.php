<?php

namespace Ulams\PolicyLifecycle\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Compliance\Database\Seeders\CompliancePermissionSeeder;
use Ulams\Compliance\UlamsComplianceServiceProvider;
use Ulams\LivingCourse\Tests\TestCase as LivingCourseTestCase;
use Ulams\PolicyLifecycle\Database\Seeders\PolicyPermissionSeeder;
use Ulams\PolicyLifecycle\UlamsPolicyLifecycleServiceProvider;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder as TemplatesPermissionTableSeeder;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Ulams\TemplatesPdf\Database\Seeders\PermissionTableSeeder as TemplatesPdfPermissionTableSeeder;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

/** The Course Builder and Living Course test case (fake driver, cassettes, users) with compliance and policies loaded. */
class TestCase extends LivingCourseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->seed(TemplatesPermissionTableSeeder::class);
        $this->seed(TemplatesPdfPermissionTableSeeder::class);
        $this->seed(CompliancePermissionSeeder::class);
        $this->seed(PolicyPermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsTemplatesServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            UlamsTemplatesPdfServiceProvider::class,
            UlamsComplianceServiceProvider::class,
            UlamsPolicyLifecycleServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ulams_templates_pdf.storage.render_on_create', false);
    }

    /** A policy document of this package (resources/fixtures), as an upload. */
    protected function policyFile(string $name = 'remote-work-policy.md'): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'plfx') . '-' . $name;
        copy(__DIR__ . '/../resources/fixtures/' . $name, $copy);

        return new UploadedFile($copy, $name, null, null, true);
    }

    /** @return array<string,mixed> */
    protected function policyPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Remote work and data handling',
            'file' => $this->policyFile(),
            'minor_due_days' => 14,
            'major_due_days' => 30,
            'brief' => ['totalMinutes' => 30, 'lessonMinutes' => 10],
            'departments' => [
                ['label' => 'Engineering', 'seniority' => 'senior', 'priorExperience' => 'expert'],
                ['label' => 'Sales', 'seniority' => 'mixed', 'priorExperience' => 'none'],
            ],
        ];
    }

    protected function createPolicy($owner, array $overrides = []): string
    {
        return $this->actingAs($owner, 'api')->post('/api/admin/policies', $this->policyPayload($overrides), ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');
    }

    protected function generateAll($owner, string $policyId): void
    {
        $this->actingAs($owner, 'api')->postJson("/api/admin/policies/{$policyId}/variants/generate")->assertStatus(202);
    }

    /** Approves the outline, generates, applies and publishes the department's course, as the owner does in the Studio. */
    protected function reviewAndPublish($owner, \Ulams\CourseBuilder\Models\Session $session): void
    {
        $session->refresh();
        $outline = $session->current_version_id;
        $this->actingAs($owner, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'threadId' => $session->id, 'runId' => 'client', 'messages' => [],
            'forwardedProps' => ['action' => ['name' => 'approve_outline', 'surfaceId' => "outline-{$outline}", 'sourceComponentId' => 'x', 'context' => ['versionId' => $outline]]],
        ])->assertStatus(202);
        $session->refresh();
        $content = $session->current_version_id;
        $this->actingAs($owner, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'threadId' => $session->id, 'runId' => 'client', 'messages' => [],
            'forwardedProps' => ['action' => ['name' => 'approve_apply', 'surfaceId' => "apply-{$content}", 'sourceComponentId' => 'x', 'context' => ['versionId' => $content]]],
        ])->assertStatus(202);
        $this->actingAs($owner, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/publish", ['acknowledgedWarnings' => true])->assertOk();
    }

    /** A policy whose department courses are applied and published (the version is not approved yet). */
    protected function publishedPolicy($owner): \Ulams\PolicyLifecycle\Models\Policy
    {
        $id = $this->createPolicy($owner);
        $this->generateAll($owner, $id);
        $policy = \Ulams\PolicyLifecycle\Models\Policy::query()->findOrFail($id);
        foreach ($policy->variants as $variant) {
            $this->reviewAndPublish($owner, \Ulams\CourseBuilder\Models\Session::query()->findOrFail($variant->session_id));
        }

        return $policy->refresh();
    }

    /** Uploads a changed policy file as the next version and returns the response. */
    protected function submitChange($user, string $policyId, string $fixture = 'remote-work-policy.v2.md')
    {
        return $this->actingAs($user, 'api')->post("/api/admin/policies/{$policyId}/versions", ['file' => $this->policyFile($fixture)], ['Accept' => 'application/json']);
    }

    /**
     * Accepts every item of every department's proposal of the version, as the owner does in the Studio. Items the model
     * could not propose a change for (kind `manual`, here the final-test questions) are the owner's to edit by hand; the
     * helper settles them as "kept as they are" so the version can be approved.
     */
    protected function acceptAllProposals($user, \Ulams\PolicyLifecycle\Models\PolicyVersion $version): void
    {
        foreach (\Ulams\PolicyLifecycle\Models\PolicyRollout::query()->where('policy_version_id', $version->id)->whereNotNull('proposal_id')->get() as $rollout) {
            $this->actingAs($user, 'api')->postJson("/api/admin/living-course/proposals/{$rollout->proposal_id}/accept-all")->assertOk();
            foreach (\Ulams\LivingCourse\Models\ProposalItem::query()->where('proposal_id', $rollout->proposal_id)->where('status', 'pending')->get() as $item) {
                $this->actingAs($user, 'api')->postJson("/api/admin/living-course/proposals/{$rollout->proposal_id}/items/{$item->id}/reject")->assertOk();
            }
        }
    }

    protected function approveVersion($user, string $policyId, int $number, array $body = [])
    {
        return $this->actingAs($user, 'api')->postJson("/api/admin/policies/{$policyId}/versions/{$number}/approve", $body);
    }
}
