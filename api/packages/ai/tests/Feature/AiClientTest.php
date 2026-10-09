<?php

namespace Ulams\Ai\Tests\Feature;

use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Models\AiCall;
use Ulams\Ai\Prompts\Prompt;
use Ulams\Ai\Tests\TestCase;

class AiClientTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['title', 'minutes'],
        'properties' => [
            'title' => ['type' => 'string', 'minLength' => 3],
            'minutes' => ['type' => 'integer', 'minimum' => 1],
        ],
    ];

    private function request(array $overrides = []): LlmRequest
    {
        return new LlmRequest(
            task: $overrides['task'] ?? 'outline',
            prompt: Prompt::inline('test/outline', 3, 'You return JSON.'),
            blocks: [ContentBlock::text('<source_document>…</source_document>', true), ContentBlock::text('Make an outline.')],
            schema: self::SCHEMA,
            subject: $overrides['subject'] ?? ['type' => 'course_builder_session', 'id' => 'S1'],
            userId: 7,
            validator: $overrides['validator'] ?? null,
            budget: $overrides['budget'] ?? [],
        );
    }

    private function client(): LlmClient
    {
        return $this->app->make(LlmClient::class);
    }

    public function testValidOutputIsReturnedAndLoggedWithCost(): void
    {
        $this->fake()->queueJson('outline', ['title' => 'Coffee', 'minutes' => 30], 'claude-sonnet-5-5', new Usage(1000, 200, 0, 5000, 0));

        $result = $this->client()->generate($this->request());

        $this->assertSame(['title' => 'Coffee', 'minutes' => 30], $result->data);
        $this->assertSame(1, $result->attempts);
        // 1000 × 2 + 200 × 10 + 5000 × 2 × 2 (1 h cache write) = 24000 micro-USD
        $this->assertSame(24000, $result->costMicroUsd);

        $call = AiCall::query()->findOrFail($result->callIds[0]);
        $this->assertSame('ok', $call->status);
        $this->assertSame('outline', $call->task);
        $this->assertSame('test/outline', $call->prompt_id);
        $this->assertSame(3, $call->prompt_version);
        $this->assertSame('default', $call->profile);
        $this->assertSame('claude-sonnet-5-5', $call->model_requested);
        $this->assertSame('claude-sonnet-5-5', $call->model_served);
        $this->assertSame(5000, $call->cache_creation_tokens);
        $this->assertSame(24000, $call->cost_micro_usd);
        $this->assertSame('course_builder_session', $call->subject_type);
        $this->assertSame('S1', $call->subject_id);
        $this->assertSame(7, $call->user_id);
    }

    public function testServedModelIsPricedWhenAFallbackAnswered(): void
    {
        $this->fake()->queueJson('outline', ['title' => 'Coffee', 'minutes' => 30], 'claude-opus-5-5', new Usage(1000, 0));
        $result = $this->client()->generate($this->request());
        $this->assertSame('claude-opus-5-5', $result->model);
        $this->assertSame(4000, $result->costMicroUsd);
        $this->assertSame('claude-sonnet-5-5', AiCall::query()->find($result->callIds[0])->model_requested);
    }

    public function testInvalidOutputIsRepairedOnce(): void
    {
        $this->fake()
            ->queueJson('outline', ['title' => 'No', 'minutes' => 0])
            ->queueJson('outline', ['title' => 'Coffee basics', 'minutes' => 20]);

        $result = $this->client()->generate($this->request());

        $this->assertSame(2, $result->attempts);
        $this->assertSame('Coffee basics', $result->data['title']);
        $sent = $this->fake()->sent();
        $this->assertCount(2, $sent);
        $turns = $sent[1]->turns;
        $repair = end($turns);
        $this->assertSame('user', $repair['role']);
        $this->assertStringContainsString('/title', $repair['text']);
        $this->assertStringContainsString('/minutes', $repair['text']);
        $this->assertSame(['invalid', 'ok'], AiCall::query()->whereIn('id', $result->callIds)->orderBy('attempt')->pluck('status')->all());
    }

    public function testOutputInvalidTwiceFailsWithErrors(): void
    {
        $this->fake()->queueJson('outline', 'not json')->queueJson('outline', ['title' => 'x']);

        try {
            $this->client()->generate($this->request());
            $this->fail('expected an exception');
        } catch (LlmException $e) {
            $this->assertSame(LlmException::INVALID_OUTPUT, $e->reason);
            $this->assertNotEmpty($e->errors);
            $this->assertCount(2, $e->callIds);
        }
    }

    public function testSemanticValidatorErrorsTriggerTheRepair(): void
    {
        $this->fake()
            ->queueJson('outline', ['title' => 'Coffee', 'minutes' => 30])
            ->queueJson('outline', ['title' => 'Coffee', 'minutes' => 45]);
        $validator = fn (array $data) => $data['minutes'] === 30 ? ['minutes must not be 30'] : [];

        $result = $this->client()->generate($this->request(['validator' => $validator]));

        $this->assertSame(45, $result->data['minutes']);
        $turns = $this->fake()->sent()[1]->turns;
        $this->assertStringContainsString('minutes must not be 30', end($turns)['text']);
    }

    public function testRefusalIsAFailedStepNeverPartialContent(): void
    {
        $this->fake()->queue('outline', new DriverResponse('{"title":"half', 'claude-sonnet-5-5', 'refusal', new Usage(10, 1), 'r1', 'cyber'));

        try {
            $this->client()->generate($this->request());
            $this->fail('expected an exception');
        } catch (LlmException $e) {
            $this->assertSame(LlmException::REFUSAL, $e->reason);
            $this->assertStringContainsString('cyber', $e->getMessage());
        }
        $this->assertSame('refused', AiCall::query()->latest('created_at')->first()->status);
    }

    public function testMaxTokensIsAFailedStep(): void
    {
        $this->fake()->queue('outline', new DriverResponse('{"title":"Cof', 'claude-sonnet-5-5', 'max_tokens', new Usage(10, 32000)));
        $this->expectExceptionObject(new LlmException(LlmException::MAX_TOKENS, 'The answer was too long and was cut off. Retry this step, or split the element into smaller parts.'));
        $this->client()->generate($this->request());
    }

    public function testSubjectTokenBudgetStopsBeforeTheCall(): void
    {
        AiCall::query()->create([
            'task' => 'lesson', 'driver' => 'fake', 'model_requested' => 'claude-sonnet-5-5', 'status' => 'ok',
            'input_tokens' => 2999000, 'output_tokens' => 0, 'subject_type' => 'course_builder_session', 'subject_id' => 'S1',
        ]);

        try {
            $this->client()->generate($this->request());
            $this->fail('expected budget exception');
        } catch (LlmException $e) {
            $this->assertSame(LlmException::BUDGET, $e->reason);
        }
        $this->assertSame([], $this->fake()->sent());
    }

    public function testCacheReadsCountAtTenPercentAgainstTheTokenBudget(): void
    {
        AiCall::query()->create([
            'task' => 'lesson', 'driver' => 'fake', 'model_requested' => 'claude-sonnet-5-5', 'status' => 'ok',
            'cache_read_tokens' => 20000000, 'subject_type' => 'course_builder_session', 'subject_id' => 'S1',
        ]);
        $this->fake()->queueJson('outline', ['title' => 'Coffee', 'minutes' => 30]);

        // 20M cache reads count as 2M tokens: still under the 3M cap
        $this->assertSame(1, $this->client()->generate($this->request())->attempts);
    }

    public function testSubjectCostBudgetCanBeOverriddenPerRequest(): void
    {
        $this->expectExceptionObject(new LlmException(LlmException::BUDGET, ''));
        try {
            $this->client()->generate($this->request(['budget' => ['cost_micro_usd' => 10]]));
        } catch (LlmException $e) {
            $this->assertStringContainsString('Budget reached', $e->getMessage());
            throw new LlmException($e->reason, '');
        }
    }

    public function testTenantMonthlyCapRefusesNewCalls(): void
    {
        config(['ai.limits.tenant_monthly_usd' => 1]);
        AiCall::query()->create([
            'task' => 'lesson', 'driver' => 'fake', 'model_requested' => 'claude-sonnet-5-5', 'status' => 'ok', 'cost_micro_usd' => 1000000,
        ]);

        try {
            $this->client()->generate($this->request(['subject' => []]));
            $this->fail('expected budget exception');
        } catch (LlmException $e) {
            $this->assertSame(LlmException::BUDGET, $e->reason);
            $this->assertStringContainsString('monthly', $e->getMessage());
        }
    }

    public function testDisabledDriverFailsWithAClearMessage(): void
    {
        $this->app->forgetInstance(LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        config(['ai.driver' => 'disabled']);

        try {
            $this->client()->generate($this->request());
            $this->fail('expected disabled');
        } catch (LlmException $e) {
            $this->assertSame(LlmException::DISABLED, $e->reason);
        }
    }

    public function testMissingCassetteFailsLoudly(): void
    {
        try {
            $this->client()->generate($this->request());
            $this->fail('expected missing cassette');
        } catch (LlmException $e) {
            $this->assertSame(LlmException::MISSING_CASSETTE, $e->reason);
            $this->assertStringContainsString('/outline/v3/', $e->getMessage());
        }
        $this->assertSame('error', AiCall::query()->latest('created_at')->first()->status);
    }
}
