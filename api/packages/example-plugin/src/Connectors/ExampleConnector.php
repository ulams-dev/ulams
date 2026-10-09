<?php

namespace Ulams\ExamplePlugin\Connectors;

use Illuminate\Http\Request;
use Ulams\LivingCourse\Connectors\ConnectorException;
use Ulams\LivingCourse\Connectors\FetchedFile;
use Ulams\LivingCourse\Connectors\FetchResult;
use Ulams\LivingCourse\Connectors\SourceConnector;
use Ulams\LivingCourse\Connectors\WebhookDelivery;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;

/**
 * A source connector in a plugin: the worked example of the guide "Connector plugins"
 * (`docs/living-course/connector-plugins.md`). It pretends that the pages of a remote notebook are
 * configured inline (`pages: [{path, text}]`), which keeps the example free of network code; a real
 * connector fetches them (through `Ulams\Core\Http\SafeHttp`, never a bare HTTP client).
 *
 * The contract: `fetch()` returns files and a reference (here a hash) and says `unchanged` when the
 * reference matches the latest revision; Living Course converts, fragments, diffs and proposes.
 */
final class ExampleConnector implements SourceConnector
{
    public function key(): string
    {
        return 'example';
    }

    public function label(): string
    {
        return 'Example notebook';
    }

    public function configSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['pages'],
            'properties' => [
                'pages' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 20,
                    'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['path', 'text'], 'properties' => ['path' => ['type' => 'string', 'maxLength' => 200], 'text' => ['type' => 'string', 'maxLength' => 100000]]],
                ],
            ],
        ];
    }

    public function secretFields(): array
    {
        return [];
    }

    public function validate(array $config, array $secrets): void
    {
        if (!is_array($config['pages'] ?? null) || $config['pages'] === []) {
            throw new ConnectorException('Add at least one page.');
        }
    }

    public function fetch(Connection $connection, ?Revision $latest): FetchResult
    {
        $pages = (array) ($connection->config['pages'] ?? []);
        $ref = hash('sha256', (string) json_encode($pages));
        if ($latest !== null && $latest->origin_ref === $ref) {
            return FetchResult::unchanged($ref);
        }
        $files = array_map(fn (array $p) => new FetchedFile((string) $p['path'], (string) $p['text'], 'markdown', hash('sha256', (string) $p['text'])), $pages);

        return new FetchResult(false, $ref, $files, ['fileCount' => count($files)]);
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
