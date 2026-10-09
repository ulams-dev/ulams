<?php

namespace Ulams\LivingCourse\Connectors;

use Illuminate\Http\Request;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;

/** New versions arrive through `POST sources/{source}/revisions`; there is nothing to fetch or schedule. */
final class UploadConnector implements SourceConnector
{
    public function key(): string
    {
        return 'upload';
    }

    public function label(): string
    {
        return 'Uploaded file';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => new \stdClass()];
    }

    public function secretFields(): array
    {
        return [];
    }

    public function validate(array $config, array $secrets): void
    {
        throw new ConnectorException('An uploaded source is created by uploading a file.');
    }

    public function fetch(Connection $connection, ?Revision $latest): FetchResult
    {
        throw new ConnectorException('An uploaded source has nothing to check: upload a new version instead.');
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function verifyWebhook(Connection $connection, Request $request): ?WebhookDelivery
    {
        return null;
    }
}
