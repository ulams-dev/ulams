<?php

namespace Ulams\Ai;

use Illuminate\Support\ServiceProvider;
use Ulams\Ai\Console\AiUsageCommand;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Drivers\AnthropicDriver;
use Ulams\Ai\Drivers\DisabledDriver;
use Ulams\Ai\Drivers\FakeDriver;
use Ulams\Ai\Fake\CassetteStore;
use Ulams\Ai\Fake\FakeResponders;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\Ai\Services\AiClient;
use Ulams\Ai\Services\BudgetGuard;
use Ulams\Ai\Services\CostCalculator;
use Ulams\Ai\Services\JsonSchemaValidator;

/**
 * Provider-agnostic LLM layer (ADR 0009): config profiles, drivers, validation, cost log, budgets
 * and the prompt registry. It has no course knowledge.
 */
class UlamsAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai.php', 'ai');

        $this->app->singleton(FakeResponders::class);
        $this->app->singleton(PromptRegistry::class);
        $this->app->singleton(JsonSchemaValidator::class);
        $this->app->singleton(CostCalculator::class, fn () => CostCalculator::fromConfig());
        $this->app->singleton(BudgetGuard::class);

        $this->app->singleton(LlmDriver::class, fn ($app) => $this->makeDriver());
        $this->app->singleton(LlmClient::class, fn ($app) => new AiClient(
            $app->make(LlmDriver::class),
            $app->make(JsonSchemaValidator::class),
            $app->make(CostCalculator::class),
            $app->make(BudgetGuard::class),
            config('ai.record.path') ? new CassetteStore((string) config('ai.record.path')) : null,
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        if ($this->app->runningInConsole()) {
            $this->commands([AiUsageCommand::class]);
        }
    }

    /** Resolves AI_DRIVER; anthropic without an API key becomes disabled (the LMS keeps working). */
    public static function driverName(): string
    {
        $driver = (string) config('ai.driver', 'anthropic');
        if ($driver === 'anthropic' && !config('ai.api_key')) {
            return 'disabled';
        }

        return in_array($driver, ['anthropic', 'fake', 'disabled'], true) ? $driver : 'disabled';
    }

    private function makeDriver(): LlmDriver
    {
        return match (self::driverName()) {
            'anthropic' => new AnthropicDriver(
                (string) config('ai.api_key'),
                config('ai.base_url') ?: null,
                (int) config('ai.timeout', 600),
                (int) config('ai.max_retries', 2),
                (string) config('ai.fallbacks_beta'),
            ),
            'fake' => new FakeDriver(
                new CassetteStore((string) (config('ai.fake.cassettes') ?: storage_path('app/ai-cassettes'))),
                $this->app->make(FakeResponders::class),
                (string) config('ai.fake.mode', 'synthetic'),
            ),
            default => new DisabledDriver(),
        };
    }
}
