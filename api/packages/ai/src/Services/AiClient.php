<?php

namespace Ulams\Ai\Services;

use Illuminate\Support\Facades\Log;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Drivers\DisabledDriver;
use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Dto\LlmResult;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Exceptions\DriverException;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Fake\CassetteStore;
use Ulams\Ai\Models\AiCall;

/**
 * The LLM client used by every package: resolves the task profile, checks budgets, calls the
 * driver, validates the structured output (JSON Schema + the request's semantic validator), makes
 * one repair attempt with the errors, and logs every attempt to ai_calls with its cost.
 */
final class AiClient implements LlmClient
{
    public const MAX_ATTEMPTS = 2;

    public function __construct(
        private readonly LlmDriver $driver,
        private readonly JsonSchemaValidator $schemas,
        private readonly CostCalculator $costs,
        private readonly BudgetGuard $budgets,
        private readonly ?CassetteStore $recorder = null,
    ) {
    }

    public function enabled(): bool
    {
        return !$this->driver instanceof DisabledDriver;
    }

    public function driver(): LlmDriver
    {
        return $this->driver;
    }

    public function profileLabel(string $task): string
    {
        $profile = $this->taskConfig($task)['profile'];

        return (string) config("ai.profiles.{$profile}.label", ucfirst($profile));
    }

    public function generate(LlmRequest $request): LlmResult
    {
        if (!$this->enabled()) {
            throw new LlmException(LlmException::DISABLED, 'AI features are disabled on this installation (AI_DRIVER=disabled or no API key).');
        }

        $call = $this->resolve($request);
        $this->budgets->assertCanSpend($request, $call);

        $usage = new Usage();
        $cost = 0;
        $callIds = [];
        $errors = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $started = hrtime(true);
            try {
                $response = $this->driver->send($call);
            } catch (DriverException $e) {
                $callIds[] = $this->log($request, $call, null, $attempt, 'error', (int) ((hrtime(true) - $started) / 1e6), $e->getMessage())->id;

                throw new LlmException(
                    $e->missingCassette ? LlmException::MISSING_CASSETTE : LlmException::TRANSPORT,
                    $e->missingCassette ? $e->getMessage() : 'The AI provider could not be reached. Try again in a moment.',
                    [$e->getMessage()],
                    $callIds,
                    $e,
                );
            }
            $latency = (int) ((hrtime(true) - $started) / 1e6);
            $usage = $usage->plus($response->usage);
            $callCost = $this->costs->cost($response->model, $response->usage);
            $cost += $callCost;

            if ($response->stopReason === 'refusal') {
                $callIds[] = $this->log($request, $call, $response, $attempt, 'refused', $latency, $response->refusalCategory, $callCost)->id;

                throw new LlmException(LlmException::REFUSAL, 'The model declined this step' . ($response->refusalCategory ? " ({$response->refusalCategory})" : '') . '. Rephrase the request or adjust the source, then retry.', [], $callIds);
            }
            if ($response->stopReason === 'max_tokens') {
                $callIds[] = $this->log($request, $call, $response, $attempt, 'max_tokens', $latency, 'Output limit reached', $callCost)->id;

                throw new LlmException(LlmException::MAX_TOKENS, 'The answer was too long and was cut off. Retry this step, or split the element into smaller parts.', [], $callIds);
            }

            [$data, $errors] = $this->check($response->text, $request);
            $status = $errors === [] ? 'ok' : 'invalid';
            $callIds[] = $this->log($request, $call, $response, $attempt, $status, $latency, $errors === [] ? null : implode("\n", array_slice($errors, 0, 10)), $callCost)->id;
            $this->record($call, $response);

            if ($errors === []) {
                return new LlmResult($data, $response->model, $usage, $cost, $attempt, $callIds);
            }

            $call = $call->withTurns(array_merge($call->turns, [
                ['role' => 'assistant', 'text' => $response->text],
                ['role' => 'user', 'text' => self::repairInstruction($errors)],
            ]));
        }

        throw new LlmException(LlmException::INVALID_OUTPUT, 'The generated output did not pass validation twice. Retry the step.', $errors, $callIds);
    }

    /** @return array{0:array<string,mixed>,1:string[]} */
    private function check(string $text, LlmRequest $request): array
    {
        $decoded = json_decode(self::stripFence($text));
        if (!is_object($decoded)) {
            return [[], ['The output is not a JSON object.']];
        }
        $errors = $this->schemas->validate($decoded, $request->schema);
        $data = json_decode(json_encode($decoded), true) ?: [];
        if ($errors === [] && $request->validator !== null) {
            $errors = array_values(($request->validator)($data));
        }

        return [$data, $errors];
    }

    /** @param string[] $errors */
    public static function repairInstruction(array $errors): string
    {
        return "Your previous answer failed validation:\n- " . implode("\n- ", array_slice($errors, 0, 20))
            . "\nReturn the complete corrected JSON object. Keep everything that was valid.";
    }

    private static function stripFence(string $text): string
    {
        $t = trim($text);
        if (str_starts_with($t, '```')) {
            $t = (string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', $t);
        }

        return $t;
    }

    /** @return array{profile:string,effort:string,max_tokens:int} */
    public function taskConfig(string $task): array
    {
        $defaults = (array) config('ai.task_defaults', ['profile' => 'default', 'effort' => 'medium', 'max_tokens' => 8000]);
        // profile overrides (AI_TASK_<TASK>_PROFILE) are read in config/ai.php
        $config = array_merge($defaults, (array) config("ai.tasks.{$task}", []));

        return ['profile' => (string) $config['profile'], 'effort' => (string) $config['effort'], 'max_tokens' => (int) $config['max_tokens']];
    }

    private function resolve(LlmRequest $request): DriverRequest
    {
        $task = $this->taskConfig($request->task);
        $profile = (array) config("ai.profiles.{$task['profile']}", []);
        $model = (string) ($profile['model'] ?? '');
        if ($model === '') {
            throw new LlmException(LlmException::DISABLED, "No model is configured for the \"{$task['profile']}\" profile.");
        }

        return new DriverRequest(
            task: $request->task,
            promptId: $request->prompt->id,
            promptVersion: $request->prompt->version,
            profile: $task['profile'],
            model: $model,
            effort: $task['effort'],
            maxTokens: $task['max_tokens'],
            system: $request->prompt->text,
            blocks: $request->blocks,
            schema: $request->schema,
            cacheTtl: (string) config('ai.cache_ttl', '1h'),
            fallbacks: $profile['fallbacks'] ?? null,
        );
    }

    private function log(LlmRequest $request, DriverRequest $call, ?DriverResponse $response, int $attempt, string $status, int $latency, ?string $error = null, int $cost = 0): AiCall
    {
        return AiCall::query()->create([
            'task' => $request->task,
            'prompt_id' => $request->prompt->id,
            'prompt_version' => $request->prompt->version,
            'profile' => $call->profile,
            'driver' => $this->driver->name(),
            'model_requested' => $call->model,
            'model_served' => $response?->model,
            'input_tokens' => $response?->usage->inputTokens ?? 0,
            'output_tokens' => $response?->usage->outputTokens ?? 0,
            'cache_creation_tokens' => $response?->usage->cacheWriteTokens() ?? 0,
            'cache_read_tokens' => $response?->usage->cacheReadTokens ?? 0,
            'cost_micro_usd' => $cost,
            'latency_ms' => $latency,
            'stop_reason' => $response?->stopReason,
            'status' => $status,
            'attempt' => $attempt,
            'request_id' => $response?->requestId,
            'error' => $error !== null ? mb_substr($error, 0, 4000) : null,
            'subject_type' => $request->subjectType(),
            'subject_id' => $request->subjectId(),
            'user_id' => $request->userId,
        ]);
    }

    private function record(DriverRequest $call, DriverResponse $response): void
    {
        if ($this->recorder === null || !config('ai.record.enabled')) {
            return;
        }
        if (in_array($response->requestId, ['synthetic', 'queued'], true) || str_starts_with((string) $response->requestId, 'cassette:')) {
            return;
        }
        try {
            $this->recorder->save($call, $response);
        } catch (\Throwable $e) {
            Log::warning('ai: could not write cassette', ['error' => $e->getMessage()]);
        }
    }
}
