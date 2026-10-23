<?php

namespace Tests\Integrations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Core\Tests\CreatesUsers;
use Tests\TestCase;
use Ulams\Reporting\Services\DatasetRegistry;

/**
 * The report builder's dataset registry in the whole application (ADR 0194, RB-1): every package that owns report
 * data registers it, and an admin of a tenant is offered all five, a tutor none of them.
 */
class ReportingDatasetsTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    public function testTheApplicationRegistersTheSixDatasets(): void
    {
        $keys = $this->app->make(DatasetRegistry::class)->keys();

        $this->assertEqualsCanonicalizing(['assignments', 'certificates', 'audit', 'authoring', 'ai_usage', 'policy_training'], $keys);
    }

    public function testAnAdminIsOfferedEveryDatasetWithItsColumns(): void
    {
        $data = $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/reporting/datasets')->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(['assignments', 'certificates', 'audit', 'authoring', 'ai_usage', 'policy_training'], array_column($data, 'key'));
        $byKey = array_column($data, null, 'key');
        $this->assertSame(['compliance_report', 'compliance_report', 'compliance_report', 'authoring_metrics_read', 'reporting_ai_usage', 'policy_owner'], [
            $byKey['assignments']['permission'], $byKey['certificates']['permission'], $byKey['audit']['permission'], $byKey['authoring']['permission'], $byKey['ai_usage']['permission'], $byKey['policy_training']['permission'],
        ]);
        $personal = collect($byKey['assignments']['columns'])->where('personal', true)->pluck('name')->all();
        $this->assertSame(['user_name', 'user_email'], $personal);
        $this->assertSame('user_id', $byKey['assignments']['subject_column']);
        foreach ($data as $dataset) {
            $this->assertNotEmpty($dataset['columns']);
            $this->assertNotEmpty($dataset['date_columns']);
        }
    }

    public function testATutorIsOfferedNoDatasetAndAStudentIsRefused(): void
    {
        $this->actingAs($this->makeInstructor(), 'api')->getJson('/api/admin/reporting/datasets')->assertOk()->assertJsonPath('data', []);
        $this->actingAs($this->makeStudent(), 'api')->getJson('/api/admin/reporting/datasets')->assertForbidden();
    }

    public function testADatasetIsOfferedOnlyToWhoHoldsItsPermission(): void
    {
        $tutor = $this->makeInstructor();
        $tutor->givePermissionTo('compliance_report');

        $data = $this->actingAs($tutor, 'api')->getJson('/api/admin/reporting/datasets')->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(['assignments', 'certificates', 'audit'], array_column($data, 'key'));
    }
}
