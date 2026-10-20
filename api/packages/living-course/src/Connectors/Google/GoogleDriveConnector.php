<?php

namespace Ulams\LivingCourse\Connectors\Google;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Opis\JsonSchema\Validator;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\LivingCourse\Connectors\ConnectorException;
use Ulams\LivingCourse\Connectors\FetchedFile;
use Ulams\LivingCourse\Connectors\FetchResult;
use Ulams\LivingCourse\Connectors\Git\FileLimits;
use Ulams\LivingCourse\Connectors\OAuthConnector;
use Ulams\LivingCourse\Connectors\Remote\RemoteApi;
use Ulams\LivingCourse\Connectors\WebhookDelivery;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Oauth\OAuthService;
use Ulams\LivingCourse\Services\RevisionService;

/**
 * A Google Docs file, a text, Word or PDF file, or a folder of them on Google Drive (plan 6.6). The
 * author signs in once (read-only scope); the refresh token is kept encrypted in the connection and
 * turned into a short-lived access token for every check. Changes are found by comparing the version and
 * modified time of the files, so an unchanged source costs one metadata request. Google Docs are exported
 * as Markdown, the other types are downloaded. Polling only; Drive push channels are not used.
 */
final class GoogleDriveConnector implements OAuthConnector
{
    private const API = 'https://www.googleapis.com/drive/v3';

    private const FIELDS = 'id,name,mimeType,modifiedTime,version,size,trashed';

    private const DOC = 'application/vnd.google-apps.document';

    private const FOLDER = 'application/vnd.google-apps.folder';

    private const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /** @param (callable(mixed):mixed)|null $handler Guzzle handler (tests) */
    public function __construct(private readonly mixed $handler = null, private readonly ?OAuthService $oauth = null)
    {
    }

    public function key(): string
    {
        return 'google-drive';
    }

    public function label(): string
    {
        return 'Google Drive';
    }

    public function oauthProvider(): string
    {
        return 'google';
    }

    public function configSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['fileId'],
            'properties' => [
                'fileId' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 300, 'description' => 'A Google Docs, text, Word or PDF file or a folder: its id or its address'],
            ],
        ];
    }

    public function secretFields(): array
    {
        return ['refresh_token'];
    }

    /** The id out of a pasted address (`/d/<id>`, `/folders/<id>`, `?id=<id>`) or the id itself. */
    public static function fileId(string $input): string
    {
        $input = trim($input);
        foreach (['~/(?:d|folders)/([A-Za-z0-9_-]{10,})~', '~[?&]id=([A-Za-z0-9_-]{10,})~'] as $pattern) {
            if (preg_match($pattern, $input, $m)) {
                return $m[1];
            }
        }

        return preg_match('/^[A-Za-z0-9_-]{10,100}$/', $input) ? $input : '';
    }

    public function validate(array $config, array $secrets): void
    {
        $result = (new Validator())->validate(json_decode((string) json_encode($config)), json_decode((string) json_encode($this->configSchema())));
        $id = self::fileId((string) ($config['fileId'] ?? ''));
        if (!$result->isValid() || $id === '') {
            throw new ConnectorException('Paste the address of a Google Docs file or folder, or its id.');
        }
        $token = (string) ($secrets['refresh_token'] ?? '');
        if ($token === '') {
            throw new ConnectorException('Connect your Google account first.');
        }
        $api = $this->api();
        $files = $this->listing($api, $this->access($token), $id);
        if ($files === []) {
            throw new ConnectorException('No Google Docs, text, Word or PDF file was found there. Share it with the Google account you connected.');
        }
        FileLimits::assert(array_map(fn ($f) => ['path' => $f['name'], 'size' => $f['size']], $files));
    }

    public function fetch(Connection $connection, ?Revision $latest): FetchResult
    {
        $id = self::fileId((string) (((array) $connection->config)['fileId'] ?? ''));
        $token = (string) (((array) $connection->secrets)['refresh_token'] ?? '');
        if ($id === '' || $token === '') {
            throw new ConnectorException('Connect your Google account again.');
        }
        $api = $this->api();
        $access = $this->access($token);
        $files = $this->listing($api, $access, $id);
        if ($files === []) {
            throw new ConnectorException('No Google Docs, text, Word or PDF file was found there any more. Check that it is still shared with the connected account.');
        }
        $ref = hash('sha256', implode("\n", array_map(fn ($f) => $f['id'] . ':' . $f['blob'], $files)));
        if ($latest !== null && $latest->origin_ref === $ref) {
            return FetchResult::unchanged($ref);
        }
        FileLimits::assert(array_map(fn ($f) => ['path' => $f['name'], 'size' => $f['size']], $files));

        $known = [];
        foreach ((array) ($latest?->metadata['files'] ?? []) as $f) {
            if (isset($f['blob'], $f['path'])) {
                $known[$f['path']] = $f['blob'];
            }
        }
        $limits = (array) config('living_course.connector_limits');
        $total = 0;
        $out = [];
        foreach ($files as $file) {
            $bytes = ($latest !== null && ($known[$file['path']] ?? null) === $file['blob']) ? $this->stored($latest, $file['path']) : null;
            $bytes ??= $this->content($api, $access, $file);
            if (strlen($bytes) > (int) $limits['file_bytes'] && $file['kind'] === 'markdown') {
                throw new ConnectorException(sprintf('%s is larger than %d KB, the limit for one source file.', $file['name'], (int) $limits['file_bytes'] / 1024));
            }
            $total += strlen($bytes);
            if ($total > (int) $limits['total_bytes']) {
                throw new ConnectorException(sprintf('The selected files add up to more than %d MB. Use a smaller folder.', (int) $limits['total_bytes'] / 1048576));
            }
            $out[] = new FetchedFile($file['path'], $bytes, $file['kind'], $file['blob']);
        }

        return new FetchResult(false, $ref, $out, ['fileCount' => count($out)]);
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
        return new RemoteApi($this->handler, 'Google Drive', (int) config('living_course.connector_limits.total_bytes'));
    }

    private function access(string $refreshToken): string
    {
        return ($this->oauth ?? app(OAuthService::class))->refreshGoogle($refreshToken);
    }

    /** @return array<string,string> */
    private static function headers(string $access): array
    {
        return ['Authorization' => 'Bearer ' . $access, 'Accept' => 'application/json', 'User-Agent' => 'ulams-living-course'];
    }

    /** @return array<int,string> */
    private static function errors(): array
    {
        return [
            401 => 'Google no longer accepts the saved sign-in. Connect your Google account again.',
            403 => 'The connected Google account may not read this file, or Google is limiting requests. Check the sharing and try again later.',
            404 => 'The file or folder was not found. Check the address and that it is shared with the connected Google account.',
        ];
    }

    /**
     * The files to read, in name order: the file itself, or the supported files directly inside a folder.
     *
     * @return array<int,array{id:string,name:string,path:string,mime:string,kind:string,size:?int,blob:string}>
     */
    private function listing(RemoteApi $api, string $access, string $id): array
    {
        $meta = $api->json('GET', self::API . '/files/' . rawurlencode($id), ['query' => ['fields' => self::FIELDS, 'supportsAllDrives' => 'true'], 'headers' => self::headers($access)], self::errors());
        $items = [];
        if (($meta['mimeType'] ?? '') === self::FOLDER) {
            $token = null;
            $pages = 0;
            do {
                $page = $api->json('GET', self::API . '/files', ['query' => array_filter([
                    'q' => "'" . $id . "' in parents and trashed = false", 'fields' => 'nextPageToken,files(' . self::FIELDS . ')', 'pageSize' => 200,
                    'orderBy' => 'name', 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true', 'pageToken' => $token,
                ]), 'headers' => self::headers($access)], self::errors());
                array_push($items, ...(array) ($page['files'] ?? []));
                $token = $page['nextPageToken'] ?? null;
            } while ($token !== null && ++$pages < 5);
            if ($token !== null) {
                throw new ConnectorException('The folder has more than 1000 files. Use a smaller folder.');
            }
        } elseif (empty($meta['trashed'])) {
            $items[] = $meta;
        }

        $files = [];
        $names = [];
        foreach ($items as $item) {
            if (!empty($item['trashed'])) {
                continue;
            }
            $mime = (string) ($item['mimeType'] ?? '');
            $kind = match (true) {
                $mime === self::DOC, in_array($mime, ['text/markdown', 'text/x-markdown', 'text/plain'], true) => 'markdown',
                $mime === self::DOCX => 'docx',
                $mime === 'application/pdf' => 'pdf',
                default => null,
            };
            if ($kind === null || !preg_match('/^[A-Za-z0-9_-]{10,100}$/', (string) ($item['id'] ?? ''))) {
                continue;
            }
            $path = self::path((string) ($item['name'] ?? 'Untitled'), $mime);
            $names[$path] = ($names[$path] ?? 0) + 1;
            $files[] = [
                'id' => (string) $item['id'], 'name' => $path, 'path' => $path, 'mime' => $mime, 'kind' => $kind,
                'size' => isset($item['size']) ? (int) $item['size'] : null,
                'blob' => (string) ($item['version'] ?? '') . '|' . (string) ($item['modifiedTime'] ?? ''),
            ];
        }
        foreach ($files as $n => $file) {
            if ($names[$file['path']] > 1) {
                // two files with one name: the id keeps both
                $files[$n]['path'] = $files[$n]['name'] = preg_replace('/(\.[A-Za-z0-9]+)$/', '-' . substr($file['id'], 0, 6) . '$1', $file['path']);
            }
        }
        usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    /** A file name that is safe as a path element and has the extension its content needs. */
    private static function path(string $name, string $mime): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $name));
        $name = mb_substr($name === '' ? 'Untitled' : $name, 0, 120);
        $extension = match (true) {
            $mime === self::DOC => 'md',
            $mime === self::DOCX => 'docx',
            $mime === 'application/pdf' => 'pdf',
            default => 'md',
        };
        $has = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $fits = match (true) {
            $mime === self::DOC => false,
            $mime === self::DOCX => $has === 'docx',
            $mime === 'application/pdf' => $has === 'pdf',
            default => in_array($has, ['md', 'markdown', 'txt'], true),
        };
        if (!$fits) {
            $name .= '.' . $extension;
        }

        return $name;
    }

    /** @param array{id:string,mime:string,size:?int,name:string} $file */
    private function content(RemoteApi $api, string $access, array $file): string
    {
        $url = self::API . '/files/' . rawurlencode($file['id']);
        $options = $file['mime'] === self::DOC
            ? ['query' => ['mimeType' => 'text/markdown'], 'headers' => ['Accept' => 'text/markdown'] + self::headers($access)]
            : ['query' => ['alt' => 'media', 'supportsAllDrives' => 'true'], 'headers' => ['Accept' => '*/*'] + self::headers($access)];
        $errors = [403 => 'This file cannot be read: it may be too large to export, or the connected account may not read it.'] + self::errors();

        return (string) $api->request('GET', $file['mime'] === self::DOC ? $url . '/export' : $url, $options, $errors)->getBody();
    }

    private function stored(Revision $revision, string $path): ?string
    {
        $disk = Storage::disk(SourceIngestor::disk());
        $file = RevisionService::rawFilePath($revision, $path);

        return $file !== null && $disk->exists($file) ? (string) $disk->get($file) : null;
    }
}
