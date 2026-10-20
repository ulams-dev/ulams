<?php

namespace Ulams\LivingCourse\Connectors\Notion;

use Illuminate\Http\Request;
use Opis\JsonSchema\Validator;
use Ulams\LivingCourse\Connectors\ConnectorException;
use Ulams\LivingCourse\Connectors\FetchedFile;
use Ulams\LivingCourse\Connectors\FetchResult;
use Ulams\LivingCourse\Connectors\OAuthConnector;
use Ulams\LivingCourse\Connectors\Remote\RemoteApi;
use Ulams\LivingCourse\Connectors\WebhookDelivery;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;

/**
 * A Notion page, optionally with the pages nested under it (plan 6.6). Access is a Notion OAuth token
 * (or an internal integration token pasted by the author) kept encrypted in the connection; the page
 * must be shared with that integration. The text of the blocks is converted to Markdown, one file per
 * page; a change is a different `last_edited_time` on any page, so a single page costs one request to
 * check. Polling only. Page content is untrusted text: nothing in it is interpreted, images and
 * embeds are dropped and only http(s) links survive.
 */
final class NotionConnector implements OAuthConnector
{
    private const API = 'https://api.notion.com/v1';

    private const VERSION = '2022-06-28';

    private const MAX_PAGES = 100;

    private const MAX_DEPTH = 3;

    private const MAX_BLOCKS = 5000;

    /** @param (callable(mixed):mixed)|null $handler Guzzle handler (tests) */
    public function __construct(private readonly mixed $handler = null)
    {
    }

    public function key(): string
    {
        return 'notion';
    }

    public function label(): string
    {
        return 'Notion';
    }

    public function oauthProvider(): string
    {
        return 'notion';
    }

    public function configSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['pageId'],
            'properties' => [
                'pageId' => ['type' => 'string', 'minLength' => 32, 'maxLength' => 400, 'description' => 'A Notion page: its address or its id'],
                'includeChildren' => ['type' => 'boolean', 'description' => 'Also read the pages nested under it (up to three levels, 100 pages)'],
            ],
        ];
    }

    public function secretFields(): array
    {
        return ['token'];
    }

    /** The 32-character page id out of a pasted address or an id with dashes; empty when there is none. */
    public static function pageId(string $input): string
    {
        $compact = str_replace('-', '', strtok(trim($input), '?#') ?: '');
        if (preg_match_all('/[0-9a-f]{32}/i', $compact, $m) && $m[0] !== []) {
            return strtolower(end($m[0]));
        }

        return '';
    }

    public function validate(array $config, array $secrets): void
    {
        $result = (new Validator())->validate(json_decode((string) json_encode($config)), json_decode((string) json_encode($this->configSchema())));
        $id = self::pageId((string) ($config['pageId'] ?? ''));
        if (!$result->isValid() || $id === '') {
            throw new ConnectorException('Paste the address of a Notion page, or its id.');
        }
        $token = (string) ($secrets['token'] ?? '');
        if ($token === '') {
            throw new ConnectorException('Connect your Notion account first, or paste an integration token.');
        }
        $pages = $this->read($id, !empty($config['includeChildren']), $token);
        if ($pages === []) {
            throw new ConnectorException('No text was found on this page.');
        }
    }

    public function fetch(Connection $connection, ?Revision $latest): FetchResult
    {
        $c = (array) $connection->config;
        $id = self::pageId((string) ($c['pageId'] ?? ''));
        $token = (string) (((array) $connection->secrets)['token'] ?? '');
        if ($id === '' || $token === '') {
            throw new ConnectorException('Connect your Notion account again.');
        }
        $children = !empty($c['includeChildren']);
        if (!$children) {
            // one page: the check is one request, the content is read only when it changed
            $meta = $this->page($this->api(), $id, $token);
            $ref = hash('sha256', $id . ':' . $meta['edited']);
            if ($latest !== null && $latest->origin_ref === $ref) {
                return FetchResult::unchanged($ref);
            }
        }
        $pages = $this->read($id, $children, $token);
        $ref = hash('sha256', implode("\n", array_map(fn ($p) => $p['id'] . ':' . $p['edited'], $pages)));
        if ($latest !== null && $latest->origin_ref === $ref) {
            return FetchResult::unchanged($ref);
        }
        $files = array_map(fn ($p) => new FetchedFile($p['path'], $p['markdown'], 'markdown', $p['edited']), $pages);

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

    private function api(): RemoteApi
    {
        return new RemoteApi($this->handler, 'Notion', (int) config('living_course.connector_limits.total_bytes'));
    }

    /** @return array<string,string> */
    private static function headers(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Notion-Version' => self::VERSION, 'Accept' => 'application/json', 'User-Agent' => 'ulams-living-course'];
    }

    /** @return array<int,string> */
    private static function errors(): array
    {
        return [
            401 => 'Notion no longer accepts the saved access. Connect your Notion account again.',
            403 => 'The connected Notion integration may not read this page.',
            404 => 'The page was not found. Share it with the connected Notion integration (open the page, then ... , Connections).',
        ];
    }

    /** @return array{id:string,title:string,edited:string} */
    private function page(RemoteApi $api, string $id, string $token): array
    {
        $page = $api->json('GET', self::API . '/pages/' . $id, ['headers' => self::headers($token)], self::errors());
        if (!empty($page['archived']) || !empty($page['in_trash'])) {
            throw new ConnectorException('This Notion page is in the trash.');
        }
        $title = '';
        foreach ((array) ($page['properties'] ?? []) as $property) {
            if (($property['type'] ?? '') === 'title') {
                $title = BlocksToMarkdown::richText((array) ($property['title'] ?? []));
                break;
            }
        }

        return ['id' => $id, 'title' => trim(strip_tags($title)) ?: 'Untitled', 'edited' => (string) ($page['last_edited_time'] ?? '')];
    }

    /**
     * The page and, when asked, the pages under it, in reading order: Markdown with the title as its heading.
     *
     * @return array<int,array{id:string,title:string,edited:string,path:string,markdown:string}>
     */
    private function read(string $rootId, bool $children, string $token): array
    {
        $api = $this->api();
        $limits = (array) config('living_course.connector_limits');
        $queue = [[$rootId, 0]];
        $seen = [];
        $pages = [];
        $blocks = 0;
        $total = 0;
        while ($queue !== []) {
            [$id, $depth] = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if (count($seen) > min(self::MAX_PAGES, (int) $limits['files'])) {
                throw new ConnectorException(sprintf('There are more than %d pages under this one. Connect a smaller part of the workspace.', self::MAX_PAGES));
            }
            $meta = $this->page($api, $id, $token);
            $nested = [];
            $tree = $this->blocks($api, $id, $token, $nested, $blocks, 0);
            $body = BlocksToMarkdown::render($tree);
            $markdown = '# ' . $meta['title'] . ($body === '' ? '' : "\n\n" . $body) . "\n";
            if (strlen($markdown) > (int) $limits['file_bytes']) {
                throw new ConnectorException(sprintf('The page "%s" is larger than %d KB, the limit for one source file.', mb_substr($meta['title'], 0, 80), (int) $limits['file_bytes'] / 1024));
            }
            $total += strlen($markdown);
            if ($total > (int) $limits['total_bytes']) {
                throw new ConnectorException(sprintf('The pages add up to more than %d MB. Connect a smaller part of the workspace.', (int) $limits['total_bytes'] / 1048576));
            }
            $pages[] = $meta + ['markdown' => $markdown, 'path' => ''];
            if ($children && $depth < self::MAX_DEPTH) {
                foreach ($nested as $child) {
                    $queue[] = [$child, $depth + 1];
                }
            }
        }
        $used = [];
        foreach ($pages as $n => $page) {
            $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $page['title']));
            $name = mb_substr($name === '' ? 'Untitled' : $name, 0, 120);
            $path = $name . '.md';
            if (isset($used[$path])) {
                $path = $name . '-' . substr($page['id'], 0, 6) . '.md';
            }
            $used[$path] = true;
            $pages[$n]['path'] = $path;
        }
        if (count($pages) > 1) {
            usort($pages, fn ($a, $b) => strcmp($a['path'], $b['path']));
        }

        return $pages;
    }

    /**
     * The blocks under a block with their children, child pages left out (their ids are collected).
     *
     * @param string[] $childPages
     * @return array<int,array<string,mixed>>
     */
    private function blocks(RemoteApi $api, string $blockId, string $token, array &$childPages, int &$count, int $level): array
    {
        $out = [];
        $cursor = null;
        do {
            $page = $api->json('GET', self::API . '/blocks/' . $blockId . '/children', ['query' => array_filter(['page_size' => 100, 'start_cursor' => $cursor]), 'headers' => self::headers($token)], self::errors());
            foreach ((array) ($page['results'] ?? []) as $block) {
                if (++$count > self::MAX_BLOCKS) {
                    throw new ConnectorException(sprintf('The page has more than %d blocks. Split it into smaller pages.', self::MAX_BLOCKS));
                }
                $type = (string) ($block['type'] ?? '');
                $id = str_replace('-', '', (string) ($block['id'] ?? ''));
                if ($type === 'child_page') {
                    if (preg_match('/^[0-9a-f]{32}$/i', $id)) {
                        $childPages[] = strtolower($id);
                    }
                    continue;
                }
                if (!empty($block['has_children']) && $level < 6 && preg_match('/^[0-9a-f]{32}$/i', $id)) {
                    $block['children'] = $this->blocks($api, $id, $token, $childPages, $count, $level + 1);
                }
                $out[] = $block;
            }
            $cursor = !empty($page['has_more']) ? ($page['next_cursor'] ?? null) : null;
        } while ($cursor !== null);

        return $out;
    }
}
