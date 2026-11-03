<?php

namespace Ulams\Tenancy\Services;

use RuntimeException;
use Ulams\Tenancy\Exceptions\EntryPointException;
use Ulams\Tenancy\Services\Contracts\EntryPointGatewayContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;

/**
 * Entry points from a tenant process: the table is in the platform database, which a tenant process has no
 * credentials for, so every call runs `ulams:entry-points:gateway` in a platform child process (the way "new
 * site" provisions a tenant). The reply is one marked JSON line; a refusal travels as `ok: false` with its
 * HTTP status and field errors, anything else that goes wrong is a RuntimeException (the API answers 503).
 */
class PlatformEntryPointGateway implements EntryPointGatewayContract
{
    public const MARKER = 'ULAMS-JSON:';

    public function __construct(private readonly TenantCommandRunnerContract $runner)
    {
    }

    public function list(string $slug): array
    {
        return $this->call($slug, 'list');
    }

    public function show(string $slug, string $id): array
    {
        return $this->call($slug, 'show', ['id' => $id]);
    }

    public function create(string $slug, array $data, ?array $actor): array
    {
        return $this->call($slug, 'create', ['data' => $data, 'actor' => $actor]);
    }

    public function update(string $slug, string $id, array $data, ?array $actor): array
    {
        return $this->call($slug, 'update', ['id' => $id, 'data' => $data, 'actor' => $actor]);
    }

    public function delete(string $slug, string $id, ?array $actor): void
    {
        $this->call($slug, 'delete', ['id' => $id, 'actor' => $actor]);
    }

    public function audit(string $slug, int $limit = 100): array
    {
        return $this->call($slug, 'audit', ['limit' => $limit]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return mixed the `data` of the reply
     */
    private function call(string $slug, string $operation, array $payload = []): mixed
    {
        $output = $this->runner->run((string) config('ulams_tenancy.platform_command_host', 'api.localhost'), [
            'ulams:entry-points:gateway', $slug, $operation, '--payload=' . base64_encode((string) json_encode($payload)),
        ]);

        $reply = null;
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (str_starts_with($line, self::MARKER)) {
                $reply = json_decode(substr($line, strlen(self::MARKER)), true);
            }
        }
        if (!is_array($reply) || !array_key_exists('ok', $reply)) {
            throw new RuntimeException('The platform did not answer the entry point request.');
        }
        if ($reply['ok'] !== true) {
            throw new EntryPointException((string) ($reply['message'] ?? 'Refused.'), (int) ($reply['status'] ?? 422), (array) ($reply['errors'] ?? []));
        }

        return $reply['data'] ?? null;
    }
}
