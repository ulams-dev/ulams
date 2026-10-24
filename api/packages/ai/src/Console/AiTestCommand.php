<?php

namespace Ulams\Ai\Console;

use Illuminate\Console\Command;
use Throwable;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Prompts\Prompt;

/**
 * One tiny call through the configured driver, for the setup wizard and operators: checks the key, the
 * model name and the network path. Logged in ai_calls like every call (task `setup_check`).
 */
class AiTestCommand extends Command
{
    public const TASK = 'setup_check';

    protected $signature = 'ai:test';

    protected $description = 'Send one minimal request to the configured AI provider and report the model, tokens and cost';

    public function handle(LlmClient $client): int
    {
        if (!$client->enabled()) {
            $this->line('AI is disabled (AI_DRIVER=disabled, or no API key): nothing to test.');

            return self::SUCCESS;
        }
        try {
            $result = $client->generate(new LlmRequest(
                task: self::TASK,
                prompt: Prompt::inline('setup/check', 1, 'You answer with the JSON object {"ok": true} and nothing else.'),
                blocks: [ContentBlock::text('Answer now.')],
                schema: ['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'boolean']]],
                subject: ['type' => 'setup_check'],
            ));
        } catch (Throwable $e) {
            $this->error('AI test failed: ' . $e->getMessage());

            return self::FAILURE;
        }
        $this->info(sprintf(
            'AI works: model %s, %d input and %d output tokens, %.6f USD.',
            $result->model,
            $result->usage->inputTokens,
            $result->usage->outputTokens,
            $result->costMicroUsd / 1_000_000,
        ));

        return self::SUCCESS;
    }
}
