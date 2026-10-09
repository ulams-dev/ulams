<?php

namespace Ulams\Ai\Tests\Unit;

use Anthropic\Beta\Messages\BetaMessage;
use Ulams\Ai\Drivers\AnthropicDriver;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Tests\TestCase;

/** Request shape only; the driver never sends anything in tests. */
class AnthropicDriverTest extends TestCase
{
    private function driver(): AnthropicDriver
    {
        config(['ai.allow_network_in_tests' => true]);

        return new AnthropicDriver('test-key', null, 30, 1, 'fallback-beta');
    }

    private function request(?string $fallbacks = 'default', array $blocks = []): DriverRequest
    {
        return new DriverRequest(
            'lesson', 'cb/lesson', 2, 'default', 'model-a', 'medium', 32000, 'SYSTEM',
            $blocks ?: [
                ContentBlock::text('<source_document untrusted="true">…</source_document>', true),
                ContentBlock::text('<brief/>', true),
                ContentBlock::text('Write lesson X.'),
            ],
            ['type' => 'object', 'additionalProperties' => false, 'properties' => ['a' => ['type' => 'string', 'maxLength' => 9]], 'required' => ['a']],
            '1h',
            $fallbacks,
        );
    }

    public function testStableBlocksGetCacheBreakpointsWithTheConfiguredTtl(): void
    {
        $params = $this->driver()->params($this->request());

        $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $params['system'][0]['cacheControl']);
        $content = $params['messages'][0]['content'];
        $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $content[0]['cacheControl']);
        $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $content[1]['cacheControl']);
        $this->assertArrayNotHasKey('cacheControl', $content[2]);
    }

    public function testNoMoreThanFourBreakpoints(): void
    {
        $blocks = array_map(fn ($i) => ContentBlock::text("b{$i}", true), range(1, 6));
        $params = $this->driver()->params($this->request('default', $blocks));
        $marked = array_filter($params['messages'][0]['content'], fn ($b) => isset($b['cacheControl']));
        $this->assertCount(3, $marked); // + the system prompt = 4
    }

    public function testStructuredOutputEffortAndNoTools(): void
    {
        $params = $this->driver()->params($this->request());

        $this->assertSame('medium', $params['outputConfig']['effort']);
        $this->assertSame('json_schema', $params['outputConfig']['format']['type']);
        $this->assertArrayNotHasKey('maxLength', $params['outputConfig']['format']['schema']['properties']['a']);
        $this->assertArrayNotHasKey('tools', $params);
        $this->assertArrayNotHasKey('toolChoice', $params);
        $this->assertSame(32000, $params['maxTokens']);
        $this->assertSame('model-a', $params['model']);
    }

    public function testFallbacksOnlyWhenTheProfileEnablesThem(): void
    {
        $with = $this->driver()->params($this->request('default'));
        $this->assertSame('default', $with['fallbacks']);
        $this->assertSame(['fallback-beta'], $with['betas']);

        $without = $this->driver()->params($this->request(null));
        $this->assertArrayNotHasKey('fallbacks', $without);
        $this->assertArrayNotHasKey('betas', $without);
    }

    public function testPdfBlocksAreNativeDocuments(): void
    {
        $params = $this->driver()->params($this->request(null, [ContentBlock::pdf(base64_encode('%PDF-1.4'), true), ContentBlock::text('go')]));
        $doc = $params['messages'][0]['content'][0];
        $this->assertSame('document', $doc['type']);
        $this->assertSame('application/pdf', $doc['source']['mediaType']);
    }

    public function testResponseMappingReadsServedModelCacheTokensAndRefusal(): void
    {
        $message = BetaMessage::fromArray([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'model-b',
            'content' => [['type' => 'text', 'text' => '{"a":"x"}', 'citations' => null]],
            'stop_reason' => 'refusal', 'stop_sequence' => null,
            'stop_details' => ['type' => 'refusal', 'category' => 'cyber', 'explanation' => null],
            'usage' => [
                'input_tokens' => 10, 'output_tokens' => 5, 'cache_read_input_tokens' => 300, 'cache_creation_input_tokens' => 70,
                'cache_creation' => ['ephemeral_5m_input_tokens' => 20, 'ephemeral_1h_input_tokens' => 50],
            ],
        ]);
        $response = AnthropicDriver::toResponse($message, 'model-a');

        $this->assertSame('model-b', $response->model);
        $this->assertSame('refusal', $response->stopReason);
        $this->assertSame('cyber', $response->refusalCategory);
        $this->assertSame('{"a":"x"}', $response->text);
        $this->assertSame(300, $response->usage->cacheReadTokens);
        $this->assertSame(20, $response->usage->cacheWrite5mTokens);
        $this->assertSame(50, $response->usage->cacheWrite1hTokens);
    }
}
