<?php

namespace Ulams\Ai\Drivers;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Exceptions\DriverException;
use Ulams\Ai\Services\JsonSchemaValidator;

/**
 * Claude through the official SDK (anthropic-ai/sdk).
 *
 * - Streamed, final message read at the end (long outputs, no HTTP timeouts).
 * - Structured outputs via `output_config.format`; the model gets no tools.
 * - Effort per task in `output_config.effort`; adaptive thinking is the models' default.
 * - Prompt caching: the system prompt is frozen per prompt version and cached; every content
 *   block marked cacheable gets a breakpoint with the configured TTL (at most four in total).
 * - Server-side refusal fallback when the profile enables it (beta header from config); the model
 *   that actually answered is returned and priced.
 * - The SDK retries 408/409/429/5xx and connection errors; a 400 fails immediately.
 */
final class AnthropicDriver implements LlmDriver
{
    private const MAX_BREAKPOINTS = 4;

    private ?Client $client = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $baseUrl = null,
        private readonly int $timeout = 600,
        private readonly int $maxRetries = 2,
        private readonly string $fallbacksBeta = 'server-side-fallback-2026-07-01',
    ) {
        if (app()->environment('testing') && !config('ai.allow_network_in_tests')) {
            throw new \LogicException('The anthropic driver must not be used in tests; use the fake driver (AI_DRIVER=fake).');
        }
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function send(DriverRequest $request): DriverResponse
    {
        $params = $this->params($request);

        try {
            $stream = $this->client()->beta->messages->createStream(...$params);
            $accumulator = MessageAccumulator::forBetaMessages();
            foreach ($stream as $event) {
                $accumulator->accumulate($event);
            }
            /** @var BetaMessage $message */
            $message = $accumulator->message();
        } catch (APIStatusException $e) {
            throw new DriverException(sprintf('Provider error %s: %s', $e->status ?? '?', $e->getMessage()), previous: $e);
        } catch (APIException $e) {
            throw new DriverException('Provider unreachable: ' . $e->getMessage(), previous: $e);
        }

        return self::toResponse($message, $request->model);
    }

    /** @return array<string,mixed> named arguments for messages->createStream() */
    public function params(DriverRequest $request): array
    {
        $breakpoints = 1; // system prompt
        $content = [];
        foreach ($request->blocks as $block) {
            $item = $block->isDocument()
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => (string) $block->data]]
                : ['type' => 'text', 'text' => $block->text];
            if ($block->cache && $breakpoints < self::MAX_BREAKPOINTS) {
                $item['cacheControl'] = ['type' => 'ephemeral', 'ttl' => $request->cacheTtl];
                $breakpoints++;
            }
            $content[] = $item;
        }
        $messages = [['role' => 'user', 'content' => $content]];
        foreach ($request->turns as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => [['type' => 'text', 'text' => $turn['text']]]];
        }

        $params = [
            'maxTokens' => $request->maxTokens,
            'messages' => $messages,
            'model' => $request->model,
            'system' => [['type' => 'text', 'text' => $request->system, 'cacheControl' => ['type' => 'ephemeral', 'ttl' => $request->cacheTtl]]],
            'outputConfig' => [
                'effort' => $request->effort,
                'format' => ['type' => 'json_schema', 'schema' => JsonSchemaValidator::forProvider($request->schema)],
            ],
            'requestOptions' => ['timeout' => (float) $this->timeout, 'maxRetries' => $this->maxRetries],
        ];
        if ($request->fallbacks !== null && $request->fallbacks !== '') {
            $params['fallbacks'] = $request->fallbacks;
            $params['betas'] = [$this->fallbacksBeta];
        }

        return $params;
    }

    public static function toResponse(BetaMessage $message, string $requested): DriverResponse
    {
        $text = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }
        $u = $message->usage;
        $write5m = $u->cacheCreation?->ephemeral5mInputTokens ?? 0;
        $write1h = $u->cacheCreation?->ephemeral1hInputTokens ?? 0;
        if ($write5m + $write1h === 0 && ($u->cacheCreationInputTokens ?? 0) > 0) {
            $write1h = (int) $u->cacheCreationInputTokens;
        }

        return new DriverResponse(
            $text,
            $message->model ?: $requested,
            (string) ($message->stopReason ?? 'end_turn'),
            new Usage((int) $u->inputTokens, (int) $u->outputTokens, (int) $write5m, (int) $write1h, (int) ($u->cacheReadInputTokens ?? 0)),
            $message->id,
            $message->stopDetails?->category,
        );
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->apiKey, baseUrl: $this->baseUrl ?: null);
    }
}
