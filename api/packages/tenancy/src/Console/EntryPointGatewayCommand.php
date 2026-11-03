<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Throwable;
use Ulams\Tenancy\Exceptions\EntryPointException;
use Ulams\Tenancy\Services\Contracts\EntryPointGatewayContract;
use Ulams\Tenancy\Services\EntryPointService;
use Ulams\Tenancy\Services\PlatformEntryPointGateway;
use Ulams\Tenancy\Support\TenantContext;

/**
 * The platform end of {@see PlatformEntryPointGateway}: runs one entry point operation for one tenant and prints
 * the reply as a marked JSON line. Not for people (hidden); the admin API and `ulams entry-points` are the
 * interface.
 */
class EntryPointGatewayCommand extends Command
{
    protected $signature = 'ulams:entry-points:gateway {slug} {operation : list|show|create|update|delete|audit} {--payload= : base64 JSON}';

    protected $description = 'Run one entry point operation for a tenant on the platform (called by the tenant API)';

    protected $hidden = true;

    public function handle(EntryPointService $service): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run this on the platform, without a tenant host.');

            return self::FAILURE;
        }

        $payload = json_decode((string) base64_decode((string) $this->option('payload'), true), true);
        $payload = is_array($payload) ? $payload : [];
        $slug = (string) $this->argument('slug');

        try {
            $data = match ((string) $this->argument('operation')) {
                'list' => $service->list($slug),
                'show' => $service->show($slug, (string) ($payload['id'] ?? '')),
                'create' => $service->create($slug, (array) ($payload['data'] ?? []), $this->actor($payload)),
                'update' => $service->update($slug, (string) ($payload['id'] ?? ''), (array) ($payload['data'] ?? []), $this->actor($payload)),
                'delete' => $this->deleted($service, $slug, $payload),
                'audit' => $service->audit($slug, (int) ($payload['limit'] ?? 100)),
                default => throw new EntryPointException('Unknown operation.', 400),
            };
            $reply = ['ok' => true, 'data' => $data];
        } catch (EntryPointException $e) {
            $reply = ['ok' => false, 'status' => $e->status, 'message' => $e->getMessage(), 'errors' => $e->errors];
        } catch (Throwable $e) {
            report($e);
            $this->error('Entry point operation failed.');

            return self::FAILURE;
        }

        $this->line(PlatformEntryPointGateway::MARKER . json_encode($reply, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $payload */
    private function deleted(EntryPointGatewayContract $service, string $slug, array $payload): ?array
    {
        $service->delete($slug, (string) ($payload['id'] ?? ''), $this->actor($payload));

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{id: int|null, email: string|null}|null
     */
    private function actor(array $payload): ?array
    {
        $actor = $payload['actor'] ?? null;
        if (!is_array($actor)) {
            return null;
        }

        return ['id' => isset($actor['id']) ? (int) $actor['id'] : null, 'email' => isset($actor['email']) ? (string) $actor['email'] : null];
    }
}
