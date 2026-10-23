<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Ulams\PolicyLifecycle\Reporting\PolicyTrainingDataset;
use Ulams\PolicyLifecycle\Tests\Support\TrainsPeople;
use Ulams\PolicyLifecycle\Tests\TestCase;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Support\ReportContext;
use Ulams\Reporting\Tests\Concerns\AssertsDatasetContract;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;

/** The owner dashboard's people as the `policy_training` dataset of the report registry. */
class PolicyTrainingDatasetTest extends TestCase
{
    use AssertsDatasetContract;
    use TrainsPeople;

    public function testItIsRegisteredAndKeepsTheDatasetContract(): void
    {
        $this->assertContains('policy_training', $this->app->make(DatasetRegistry::class)->keys());
        $this->assertDatasetContract(new PolicyTrainingDataset());
    }

    public function testOneRowPerPersonAndDepartmentWithTheVersionTheCertificateCertifies(): void
    {
        $this->seed(TemplatesPdfSeeder::class);
        $owner = $this->admin();
        [$policy, $engineers, $seller] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);

        $rows = collect(DB::select((new PolicyTrainingDataset())->baseQuery(new ReportContext($owner))->toSql(), (new PolicyTrainingDataset())->baseQuery(new ReportContext($owner))->getBindings()))->keyBy('user_id');

        $this->assertCount(3, $rows);
        $ada = $rows[$engineers[0]->getKey()];
        $this->assertSame(['Engineering', 'completed', 1, 'valid', $policy->id], [$ada->department, $ada->status, $ada->policy_version, $ada->certificate_status, $ada->policy_id]);
        $this->assertNull($rows[$engineers[1]->getKey()]->policy_version);
        $this->assertSame('Sales', $rows[$seller->getKey()]->department);
    }

    public function testAnOwnerSeesOnlyTheirOwnPoliciesAndAManagerAll(): void
    {
        $owner = $this->admin();
        [$policy] = $this->trainedPolicy($owner);
        $stranger = $this->author();
        $stranger->givePermissionTo(Permission::findOrCreate('policy_owner', 'api'));
        $count = fn ($viewer) => DB::table('users')->count() > 0 ? count(DB::select(($q = (new PolicyTrainingDataset())->baseQuery(new ReportContext($viewer)))->toSql(), $q->getBindings())) : 0;

        $this->assertSame(3, $count($owner));
        $this->assertSame(0, $count($stranger));
        $this->assertSame(0, $count(null));
        $this->assertSame('policy_owner', (new PolicyTrainingDataset())->permission());
    }
}
