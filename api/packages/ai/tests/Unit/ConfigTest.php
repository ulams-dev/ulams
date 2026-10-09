<?php

namespace Ulams\Ai\Tests\Unit;

use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Drivers\DisabledDriver;
use Ulams\Ai\Drivers\FakeDriver;
use Ulams\Ai\Services\AiClient;
use Ulams\Ai\Tests\TestCase;
use Ulams\Ai\UlamsAiServiceProvider;

class ConfigTest extends TestCase
{
    private function loadConfig(array $env): array
    {
        $keys = ['ANTHROPIC_API_KEY', 'ANTROPHIC_API_KEY', 'AI_TASK_OUTLINE_PROFILE'];
        foreach ($keys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
        try {
            return require __DIR__ . '/../../config/ai.php';
        } finally {
            foreach ($keys as $key) {
                putenv($key);
                unset($_ENV[$key]);
            }
        }
    }

    public function testKeyIsReadFromAnthropicApiKeyFirst(): void
    {
        $config = $this->loadConfig(['ANTHROPIC_API_KEY' => 'first', 'ANTROPHIC_API_KEY' => 'second']);
        $this->assertSame('first', $config['api_key']);
    }

    public function testLegacyKeySpellingIsAccepted(): void
    {
        $config = $this->loadConfig(['ANTROPHIC_API_KEY' => 'legacy']);
        $this->assertSame('legacy', $config['api_key']);
    }

    public function testTaskProfileCanBeSwitchedToPremiumByEnv(): void
    {
        $config = $this->loadConfig(['AI_TASK_OUTLINE_PROFILE' => 'premium']);
        $this->assertSame('premium', $config['tasks']['outline']['profile']);
        $this->assertSame('default', $this->loadConfig([])['tasks']['outline']['profile']);
    }

    public function testDefaultsNeverUsePremium(): void
    {
        $config = $this->loadConfig([]);
        foreach ($config['tasks'] as $task => $settings) {
            $this->assertNotSame('premium', $settings['profile'], $task);
        }
    }

    public function testAnthropicWithoutKeyResolvesToDisabled(): void
    {
        config(['ai.driver' => 'anthropic', 'ai.api_key' => null]);
        $this->assertSame('disabled', UlamsAiServiceProvider::driverName());
        config(['ai.driver' => 'nonsense']);
        $this->assertSame('disabled', UlamsAiServiceProvider::driverName());
        config(['ai.driver' => 'fake']);
        $this->assertSame('fake', UlamsAiServiceProvider::driverName());
    }

    public function testClientUsesTheConfiguredDriver(): void
    {
        /** @var AiClient $client */
        $client = $this->app->make(LlmClient::class);
        $this->assertInstanceOf(FakeDriver::class, $client->driver());
        $this->assertTrue($client->enabled());

        $this->app->forgetInstance(LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        config(['ai.driver' => 'disabled']);
        $client = $this->app->make(LlmClient::class);
        $this->assertInstanceOf(DisabledDriver::class, $client->driver());
        $this->assertFalse($client->enabled());
    }

    public function testProfileLabelNeverExposesTheModelId(): void
    {
        $client = $this->app->make(LlmClient::class);
        $this->assertSame('Haiku', $client->profileLabel('interview'));
        $this->assertSame('Sonnet', $client->profileLabel('outline'));
    }

    public function testAnthropicDriverRefusesToRunInTests(): void
    {
        $this->expectException(\LogicException::class);
        new \Ulams\Ai\Drivers\AnthropicDriver('key');
    }
}
