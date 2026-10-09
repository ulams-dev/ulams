<?php

namespace Ulams\Ai\Services;

use Illuminate\Support\Carbon;
use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Models\AiCall;

/**
 * Checked before every call, against an estimate of the call: tokens and cost per subject (e.g. a
 * builder session) and the tenant's monthly spend. Each tenant has its own database, so the sum
 * of ai_calls is the tenant's spend.
 */
final class BudgetGuard
{
    /** Output tokens assumed for the estimate (most calls stop far below max_tokens). */
    private const OUTPUT_ESTIMATE_CAP = 4000;

    public function __construct(private readonly CostCalculator $costs)
    {
    }

    public function assertCanSpend(LlmRequest $request, DriverRequest $call): void
    {
        $limits = (array) config('ai.limits', []);
        $output = min($call->maxTokens, self::OUTPUT_ESTIMATE_CAP);
        $input = $call->estimatedInputTokens();
        $estimatedCost = $this->costs->estimate($call->model, $input, $output);

        if ($request->subjectType() !== null && $request->subjectId() !== null) {
            $used = $this->subjectUsage($request->subjectType(), $request->subjectId());
            $tokenCap = (int) ($request->budget['tokens'] ?? $limits['subject_tokens'] ?? 3000000);
            $costCap = (int) ($request->budget['cost_micro_usd'] ?? (int) round(((float) ($limits['subject_cost_usd'] ?? 5)) * 1000000));

            if ($used['tokens'] + $input + $output > $tokenCap) {
                throw new LlmException(LlmException::BUDGET, 'Budget reached: this course has used its AI token allowance. Ask an admin to raise the limit to continue.');
            }
            if ($used['cost'] + $estimatedCost > $costCap) {
                throw new LlmException(LlmException::BUDGET, sprintf('Budget reached: this course has used $%.2f of its $%.2f AI budget. Ask an admin to raise the limit to continue.', $used['cost'] / 1000000, $costCap / 1000000));
            }
        }

        $monthlyCap = (int) round(((float) ($limits['tenant_monthly_usd'] ?? 50)) * 1000000);
        if ($monthlyCap > 0 && $this->monthlySpend() + $estimatedCost > $monthlyCap) {
            throw new LlmException(LlmException::BUDGET, 'Budget reached: this academy has used its monthly AI allowance. An admin can raise AI_LIMIT_TENANT_MONTHLY_USD.');
        }
    }

    /** @return array{tokens:int,cost:int} tokens with cache reads weighted, cost in micro-USD */
    public function subjectUsage(string $type, string $id): array
    {
        $row = AiCall::query()->forSubject($type, $id)
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens + cache_creation_tokens), 0) AS t, COALESCE(SUM(cache_read_tokens), 0) AS r, COALESCE(SUM(cost_micro_usd), 0) AS c')
            ->first();
        $weight = (float) config('ai.limits.cache_read_weight', 0.1);

        return [
            'tokens' => (int) ($row->t ?? 0) + (int) round(((int) ($row->r ?? 0)) * $weight),
            'cost' => (int) ($row->c ?? 0),
        ];
    }

    public function monthlySpend(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        return (int) AiCall::query()->where('created_at', '>=', $now->copy()->startOfMonth())->sum('cost_micro_usd');
    }
}
