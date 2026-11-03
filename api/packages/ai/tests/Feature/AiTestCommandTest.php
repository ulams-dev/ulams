<?php

namespace Ulams\Ai\Tests\Feature;

use Ulams\Ai\Console\AiTestCommand;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Models\AiCall;
use Ulams\Ai\Tests\TestCase;

class AiTestCommandTest extends TestCase
{
    public function testOneMinimalCallIsMadeAndLogged(): void
    {
        $this->fake()->queueJson(AiTestCommand::TASK, ['ok' => true], 'claude-haiku-5-5', new Usage(20, 5));

        $this->artisan('ai:test')->expectsOutputToContain('AI works: model claude-haiku-5-5, 20 input and 5 output tokens')->assertSuccessful();

        $call = AiCall::query()->latest('id')->firstOrFail();
        $this->assertSame(AiTestCommand::TASK, $call->task);
        $this->assertSame('ok', $call->status);
    }

    public function testAFailingProviderFailsTheCommand(): void
    {
        $this->fake()->queueJson(AiTestCommand::TASK, 'not json')->queueJson(AiTestCommand::TASK, 'still not json');

        $this->artisan('ai:test')->expectsOutputToContain('AI test failed')->assertFailed();
    }

    public function testDisabledAiIsReportedNotFailed(): void
    {
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);

        $this->artisan('ai:test')->expectsOutputToContain('AI is disabled')->assertSuccessful();
    }

    public function testTheTaskUsesASmallLightProfile(): void
    {
        $this->assertSame(['profile' => 'light', 'effort' => 'low', 'max_tokens' => 64], array_intersect_key(config('ai.tasks.' . AiTestCommand::TASK), ['profile' => 1, 'effort' => 1, 'max_tokens' => 1]));
    }
}
