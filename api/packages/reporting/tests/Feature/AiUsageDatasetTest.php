<?php

namespace Ulams\Reporting\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Reporting\Datasets\AiUsageDataset;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;
use Ulams\Reporting\Tests\Concerns\AssertsDatasetContract;
use Ulams\Reporting\Tests\TestCase;

class AiUsageDatasetTest extends TestCase
{
    use AssertsDatasetContract;

    private function insertCall(array $row): void
    {
        DB::table('ai_calls')->insert($row + [
            'id' => (string) \Illuminate\Support\Str::ulid(), 'task' => 'builder.outline', 'driver' => 'fake', 'model_requested' => 'requested-model',
            'status' => 'ok', 'input_tokens' => 100, 'output_tokens' => 50, 'cost_micro_usd' => 1500, 'latency_ms' => 800, 'created_at' => '2026-10-20 09:30:00',
        ]);
    }

    public function testTheBaseQuerySelectsTheDeclaredColumns(): void
    {
        $this->assertDatasetContract(new AiUsageDataset());
    }

    public function testRowsCarryTheServedModelCostInUsdAndTheDay(): void
    {
        $this->insertCall(['model_served' => 'served-model', 'cost_micro_usd' => 284100, 'subject_type' => 'course_builder_session']);
        $this->insertCall(['model_served' => null, 'status' => 'error']);

        $rows = (new AiUsageDataset())->baseQuery(new ReportContext(null))->orderBy('c.cost_micro_usd', 'desc')->get();

        $this->assertCount(2, $rows);
        $this->assertSame('served-model', $rows[0]->model);
        $this->assertEqualsWithDelta(0.2841, (float) $rows[0]->cost_usd, 0.0000001);
        $this->assertSame('2026-10-20', $rows[0]->day);
        $this->assertSame('course_builder_session', $rows[0]->subject_type);
        $this->assertSame('requested-model', $rows[1]->model, 'the requested model stands in when none was reported as served');
        $this->assertSame('error', $rows[1]->status);
    }

    public function testNoPromptTextErrorUserOrSubjectIdIsExposed(): void
    {
        $names = array_map(fn (Column $c) => $c->name, (new AiUsageDataset())->columns());

        foreach (['error', 'user_id', 'subject_id', 'request_id', 'prompt', 'response', 'content'] as $private) {
            $this->assertNotContains($private, $names);
        }
        $this->assertSame([], array_filter((new AiUsageDataset())->columns(), fn (Column $c) => $c->personal), 'the dataset holds nothing personal');
    }

    public function testItNeedsItsOwnPermissionAndHoldsNoLearnerData(): void
    {
        $dataset = new AiUsageDataset();

        $this->assertSame('reporting_ai_usage', $dataset->permission());
        $this->assertNull($dataset->subjectColumn());
        $this->assertSame(['created_at', 'day'], $dataset->dateColumns());
    }
}
