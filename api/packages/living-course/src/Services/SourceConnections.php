<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Services\RunService;
use Ulams\LivingCourse\Connectors\ConnectorException;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Models\Connection;

/**
 * Connects a source through a connector (plan 11.2): validates the settings with a dry run,
 * creates the source, its connection and revision 1, and hands the session over to the builder
 * (the interview starts) exactly as an upload does. Secrets are write-only: the webhook secret
 * is returned once, here and on rotation.
 */
final class SourceConnections
{
    public function __construct(
        private readonly SourceConnectorRegistry $connectors,
        private readonly RevisionService $revisions,
        private readonly SourceSync $sync,
        private readonly AuditLog $audit,
        private readonly RunService $runs,
    ) {
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $secrets
     * @return array{connection:Connection,source:Source,webhookSecret:?string}
     * @throws ConnectorException
     */
    public function connect(Session $session, string $key, array $config, array $secrets, ?string $schedule, int $userId): array
    {
        if (!$this->connectors->isEnabled($key) || $key === 'upload') {
            throw new ConnectorException('This kind of source is not available on this installation.');
        }
        $connector = $this->connectors->get($key);
        $secrets = array_intersect_key($secrets, array_flip($connector->secretFields()));
        $connector->validate($config, $secrets);

        $webhookSecret = $connector->supportsWebhooks() ? rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=') : null;
        $schedule = in_array($schedule, ['hourly', 'daily', 'weekly', 'manual'], true) ? $schedule : 'daily';

        [$source, $connection] = DB::transaction(function () use ($session, $key, $config, $secrets, $webhookSecret, $schedule, $userId) {
            $source = Source::query()->create([
                'session_id' => $session->id,
                'original_name' => mb_substr($this->name($key, $config), 0, 255),
                'mime' => 'text/markdown',
                'size' => 0,
                'sha256' => hash('sha256', $session->id . $key . json_encode($config)),
                'path' => '',
                'status' => 'processing',
            ]);
            $connection = Connection::query()->create([
                'session_id' => $session->id,
                'source_id' => $source->id,
                'connector' => $key,
                'config' => $config,
                'secrets' => array_filter($secrets + ($webhookSecret !== null ? ['webhook_secret' => $webhookSecret] : []), fn ($v) => $v !== null && $v !== ''),
                'webhook_id' => Connection::newWebhookId(),
                'schedule' => $schedule,
                'auto_analyse' => true,
                'settings' => ['show_pending_to_learners' => false, 'notify_learners_of_updates' => true],
                'status' => 'active',
                'created_by' => $userId,
            ]);
            $this->audit->record('connection.created', [
                'session_id' => $session->id, 'subject_type' => 'connection', 'subject_id' => $connection->id, 'source_id' => $source->id, 'actor_id' => $userId,
                'data' => ['connector' => $key, 'config' => $this->safeConfig($config), 'schedule' => $schedule],
            ]);

            return [$source, $connection];
        });

        try {
            $this->sync->firstRevision($connection, $source, $connector->fetch($connection, null), $userId);
        } catch (ConnectorException|\RuntimeException $e) {
            $source->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
            throw $e instanceof ConnectorException ? $e : new ConnectorException($e->getMessage(), 0, $e);
        }
        $session->refresh();
        if ($session->title === null) {
            $session->title = mb_substr((string) ($source->metadata['title'] ?? $source->original_name), 0, 255);
            $session->save();
        }
        if ($session->stateValue('interview') === null && app(LlmClient::class)->enabled()) {
            $this->runs->start($session, 'interview', [], $userId);
        }

        return ['connection' => $connection->refresh(), 'source' => $source->refresh(), 'webhookSecret' => $webhookSecret];
    }

    /** A new webhook secret (shown once); the previous one stops working. */
    public function rotateWebhookSecret(Connection $connection, int $userId): string
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $connection->forceFill(['secrets' => array_merge((array) $connection->secrets, ['webhook_secret' => $secret])])->save();
        $this->audit->record('connection.secret_rotated', [
            'session_id' => $connection->session_id, 'subject_type' => 'connection', 'subject_id' => $connection->id, 'source_id' => $connection->source_id, 'actor_id' => $userId, 'data' => ['field' => 'webhook_secret'],
        ]);

        return $secret;
    }

    /** @param array<string,mixed> $config */
    private function name(string $key, array $config): string
    {
        return (string) ($config['repository'] ?? $config['urls'][0] ?? $key);
    }

    /** @param array<string,mixed> $config @return array<string,mixed> the settings that may appear in the audit trail */
    private function safeConfig(array $config): array
    {
        return array_diff_key($config, array_flip(['token', 'secret', 'password']));
    }
}
