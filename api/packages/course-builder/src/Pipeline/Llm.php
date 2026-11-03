<?php

namespace Ulams\CourseBuilder\Pipeline;

use Closure;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Dto\LlmResult;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Models\AiCall;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;

/**
 * The builder's door to the LLM: prompt from the registry, output schema, the session as subject
 * (budget and cost log), and the running cost streamed to the UI as STATE_DELTA after each call.
 */
final class Llm
{
    public const PROMPTS = 'course-builder';

    public function __construct(
        private readonly LlmClient $client,
        private readonly PromptRegistry $prompts,
        private readonly SchemaRegistry $schemas,
        private readonly EventLog $events,
    ) {
    }

    public function enabled(): bool
    {
        return $this->client->enabled();
    }

    public function label(string $task): string
    {
        return $this->client->profileLabel($task);
    }

    /**
     * @param ContentBlock[] $blocks
     * @param array<string,mixed>|null $schema defaults to resources/schemas/outputs/<task>.json
     * @param Closure(array):string[]|null $validator
     * @param string|null $variantId the variant the call is for; defaults to the variant of the run (`input.variantId`).
     *        Its calls are logged against the variant (cost per variant), and its budget is what the session has left.
     * @throws LlmException
     */
    public function generate(Session $session, ?Run $run, string $task, array $blocks, ?Closure $validator = null, ?array $schema = null, ?string $variantId = null): LlmResult
    {
        $variantId ??= isset($run?->input['variantId']) ? (string) $run->input['variantId'] : null;
        try {
            $result = $this->client->generate(new LlmRequest(
                task: $task,
                prompt: $this->prompts->get(self::PROMPTS, $task),
                blocks: $blocks,
                schema: $schema ?? $this->schemas->get("outputs/{$task}"),
                subject: $variantId !== null ? ['type' => Variant::SUBJECT_TYPE, 'id' => $variantId] : $session->subject(),
                userId: $run?->user_id ?? $session->author_id,
                validator: $validator,
                budget: $variantId !== null ? $this->variantBudget($session, $variantId) : $session->budget(),
            ));
        } catch (LlmException $e) {
            if ($e->reason === LlmException::BUDGET) {
                $session->putState('budgetReached', true);
                $session->save();
                $this->events->stateDelta($session, $run, [['op' => 'add', 'path' => '/budgetReached', 'value' => true]]);
            }
            $this->publishCost($session, $run);

            throw $e;
        }
        $this->publishCost($session, $run);

        return $result;
    }

    /** The schema of an output (`outputs/<name>`), for tasks that share another task's schema. @return array<string,mixed> */
    public function schema(string $name): array
    {
        return $this->schemas->get($name);
    }

    /**
     * The budget guard counts what one subject spent, and a variant's calls are logged against the variant. So the
     * caps handed to it are the session's caps minus what everything else of the session spent: the session budget
     * stops the whole run, not each variant on its own.
     *
     * @return array{tokens:int,cost_micro_usd:int}
     */
    private function variantBudget(Session $session, string $variantId): array
    {
        $budget = $session->budget();
        $all = self::cost($session);
        $own = AiCall::query()->forSubject(Variant::SUBJECT_TYPE, $variantId)
            ->selectRaw('COALESCE(SUM(cost_micro_usd),0) AS c, COALESCE(SUM(input_tokens + output_tokens + cache_creation_tokens),0) AS t')->first();
        $others = ['cost' => max(0, $all['usedMicroUsd'] - (int) ($own->c ?? 0)), 'tokens' => max(0, $all['inputTokens'] + $all['outputTokens'] + $all['cacheWriteTokens'] - (int) ($own->t ?? 0))];

        return ['tokens' => max(1, $budget['tokens'] - $others['tokens']), 'cost_micro_usd' => max(1, $budget['cost_micro_usd'] - $others['cost'])];
    }

    /** @return array{usedMicroUsd:int,budgetMicroUsd:int,inputTokens:int,outputTokens:int,cacheReadTokens:int,cacheWriteTokens:int,calls:int} */
    /** @var array<string,Closure(Session):array{type:string,ids:string[]}> other subjects whose calls count for a session */
    private static array $costSubjects = [];

    /** Other packages add their call subjects to a session's running cost (Living Course: its proposals). */
    public static function extendCost(string $name, Closure $subjects): void
    {
        self::$costSubjects[$name] = $subjects;
    }

    public static function cost(Session $session): array
    {
        $extras = array_map(fn (Closure $f) => $f($session), array_values(self::$costSubjects));
        // the variants of the session are part of its running cost and of its budget (ADR 0192)
        $extras[] = ['type' => Variant::SUBJECT_TYPE, 'ids' => Variant::query()->where('session_id', $session->id)->pluck('id')->all()];
        $row = AiCall::query()
            ->where(function ($q) use ($session, $extras) {
                $q->where(fn ($w) => $w->where('subject_type', Session::SUBJECT_TYPE)->where('subject_id', $session->id));
                foreach ($extras as $extra) {
                    if ($extra['ids'] !== []) {
                        $q->orWhere(fn ($w) => $w->where('subject_type', $extra['type'])->whereIn('subject_id', $extra['ids']));
                    }
                }
            })
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(cost_micro_usd),0) AS c, COALESCE(SUM(input_tokens),0) AS i, COALESCE(SUM(output_tokens),0) AS o, COALESCE(SUM(cache_read_tokens),0) AS r, COALESCE(SUM(cache_creation_tokens),0) AS w')
            ->first();

        return [
            'usedMicroUsd' => (int) ($row->c ?? 0),
            'budgetMicroUsd' => $session->budget()['cost_micro_usd'],
            'inputTokens' => (int) ($row->i ?? 0),
            'outputTokens' => (int) ($row->o ?? 0),
            'cacheReadTokens' => (int) ($row->r ?? 0),
            'cacheWriteTokens' => (int) ($row->w ?? 0),
            'calls' => (int) ($row->n ?? 0),
        ];
    }

    private function publishCost(Session $session, ?Run $run): void
    {
        $cost = self::cost($session);
        $session->forceFill([
            'cost_micro_usd' => $cost['usedMicroUsd'],
            'tokens_used' => $cost['inputTokens'] + $cost['outputTokens'] + $cost['cacheWriteTokens'],
        ])->save();
        $this->events->stateDelta($session, $run, [['op' => 'replace', 'path' => '/cost', 'value' => $cost]]);
    }
}
