<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\AuthoringMetrics\Database\Seeders\AuthoringMetricsPermissionSeeder;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\UlamsAuthoringMetricsServiceProvider;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Tests\TestCase;

/** The model calls of proposals, from sources and from learner insights, are the maintenance cost of the course (ADR 0194). */
class MaintenanceCostTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsAuthoringMetricsServiceProvider::class];
    }

    private function proposal(string $sessionId, int $number, string $kind): Proposal
    {
        return Proposal::query()->create(['session_id' => $sessionId, 'number' => $number, 'kind' => $kind, 'status' => 'applied']);
    }

    private function aiCall(string $subjectId, int $cost, string $type = Proposal::SUBJECT_TYPE): void
    {
        AiCall::query()->create(['task' => 'update', 'driver' => 'fake', 'model_requested' => 'm', 'status' => 'ok', 'cost_micro_usd' => $cost, 'latency_ms' => 10, 'subject_type' => $type, 'subject_id' => $subjectId]);
    }

    public function testProposalCallsOfBothKindsAreMaintenanceNotBuildCost(): void
    {
        $this->seed(AuthoringMetricsPermissionSeeder::class);
        $session = $this->toApplied($this->author());
        $metrics = app(CourseMetrics::class);
        $before = $metrics->recompute((int) $session->refresh()->course_id);
        $source = $this->proposal($session->id, 1, 'source');
        $insights = $this->proposal($session->id, 2, 'insights');
        $other = $this->proposal('01ARZ3NDEKTSV4RRFFQ69G5FAV', 1, 'source');
        $this->aiCall($source->id, 300);
        $this->aiCall($insights->id, 500);
        $this->aiCall($other->id, 70000);
        $this->aiCall($session->id, 90000, 'tutor_conversation');

        $after = $metrics->recompute((int) $session->course_id);

        $this->assertSame(800, (int) $after->cost_maintenance_micro_usd);
        $this->assertSame((int) $before->cost_build_micro_usd, (int) $after->cost_build_micro_usd);
        $this->assertSame((int) $before->llm_calls + 2, (int) $after->llm_calls);
    }
}
