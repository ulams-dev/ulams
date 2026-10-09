<?php

namespace Ulams\LivingCourse\Connectors;

use Illuminate\Http\Request;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;

/**
 * Where the text of a source comes from (ADR 0032). A connector fetches files; it never produces
 * fragments, never sees the model and never writes the database. Plugins register more connectors
 * with `SourceConnectorRegistry::register()` from their service provider.
 */
interface SourceConnector
{
    /** Stable key stored on the connection: `upload`, `git`, `url`, or a plugin's own. */
    public function key(): string;

    /** Human name for the UI, e.g. "Git repository". */
    public function label(): string;

    /** JSON Schema (draft 2020-12) of the non-secret configuration. */
    public function configSchema(): array;

    /** Names of the write-only secret fields, e.g. `['token']`. The webhook secret is managed by Living Course. */
    public function secretFields(): array;

    /**
     * A dry run at connect time: reachability, permissions, SSRF checks.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed> $secrets
     * @throws ConnectorException with a message safe to show the author
     */
    public function validate(array $config, array $secrets): void;

    /**
     * Fetches the current state. `unchanged` when `$latest` still matches the remote.
     *
     * @throws ConnectorException
     */
    public function fetch(Connection $connection, ?Revision $latest): FetchResult;

    public function supportsWebhooks(): bool;

    /**
     * Verifies a webhook delivery and says whether it concerns this source. Null: valid but not
     * relevant (another branch, unrelated paths), to be ignored.
     *
     * @throws InvalidSignature when the signature does not match
     */
    public function verifyWebhook(Connection $connection, Request $request): ?WebhookDelivery;
}
