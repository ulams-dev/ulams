<?php

namespace Ulams\Ai\Fake;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\Usage;

/**
 * Recorded model responses, keyed by task, prompt version, output schema and the normalised
 * request content: `<root>/<task>/v<version>/<hash>.json`.
 *
 * Normalisation replaces generated identifiers (fragment ids `frg_…` and ULIDs) with ordinal
 * placeholders in order of first appearance, so a recording replays against a fresh database
 * with new ids. The response text is stored with the same placeholders and mapped back on replay.
 */
final class CassetteStore
{
    private const ID_PATTERN = '/\b(frg_[a-z0-9]{12})\b|\b([0-7][0-9a-hjkmnp-tv-z]{25})\b/i';

    public function __construct(private readonly string $root)
    {
    }

    public function root(): string
    {
        return $this->root;
    }

    /** @return array{hash:string,map:array<string,string>,content:string} */
    public function key(DriverRequest $request): array
    {
        $parts = [];
        foreach ($request->blocks as $block) {
            $parts[] = $block->fingerprint();
        }
        foreach ($request->turns as $turn) {
            $parts[] = $turn['role'] . ':' . $turn['text'];
        }
        [$content, $map] = self::normalise(implode("\n\u{241E}\n", $parts));
        $hash = hash('sha256', json_encode([
            $request->task,
            $request->promptVersion,
            self::canonical($request->schema),
            $content,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['hash' => substr($hash, 0, 32), 'map' => $map, 'content' => $content];
    }

    public function path(DriverRequest $request, ?string $hash = null): string
    {
        $hash ??= $this->key($request)['hash'];

        return rtrim($this->root, '/') . "/{$request->task}/v{$request->promptVersion}/{$hash}.json";
    }

    public function load(DriverRequest $request): ?DriverResponse
    {
        $key = $this->key($request);
        $file = $this->path($request, $key['hash']);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return null;
        }
        $text = self::denormalise((string) ($data['text'] ?? ''), $key['map']);

        return new DriverResponse(
            $text,
            (string) ($data['model'] ?? $request->model),
            (string) ($data['stop_reason'] ?? 'end_turn'),
            Usage::fromArray((array) ($data['usage'] ?? [])),
            'cassette:' . $key['hash'],
            $data['refusal_category'] ?? null,
        );
    }

    public function save(DriverRequest $request, DriverResponse $response): string
    {
        $key = $this->key($request);
        $file = $this->path($request, $key['hash']);
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        $reverse = array_flip($key['map']);
        $text = preg_replace_callback(self::ID_PATTERN, function (array $m) use ($reverse) {
            $id = $m[0];

            return isset($reverse[$id]) ? '{{' . $reverse[$id] . '}}' : $id;
        }, $response->text);

        file_put_contents($file, json_encode([
            'task' => $request->task,
            'prompt_id' => $request->promptId,
            'prompt_version' => $request->promptVersion,
            'hash' => $key['hash'],
            'model' => $response->model,
            'stop_reason' => $response->stopReason,
            'refusal_category' => $response->refusalCategory,
            'usage' => $response->usage->toArray(),
            'text' => $text,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        return $file;
    }

    /**
     * @return array{0:string,1:array<string,string>} normalised text and placeholder → id map
     */
    public static function normalise(string $text): array
    {
        $map = [];
        $seen = [];
        $counters = ['frg' => 0, 'id' => 0];
        $out = preg_replace_callback(self::ID_PATTERN, function (array $m) use (&$map, &$seen, &$counters) {
            $id = $m[0];
            if (!isset($seen[$id])) {
                $kind = str_starts_with($id, 'frg_') ? 'frg' : 'id';
                $token = $kind . '_' . (++$counters[$kind]);
                $seen[$id] = $token;
                $map[$token] = $id;
            }

            return '{{' . $seen[$id] . '}}';
        }, $text);

        return [(string) $out, $map];
    }

    /** @param array<string,string> $map */
    public static function denormalise(string $text, array $map): string
    {
        return (string) preg_replace_callback('/\{\{((?:frg|id)_\d+)\}\}/', fn (array $m) => $map[$m[1]] ?? $m[0], $text);
    }

    /** JSON with sorted object keys, so equal schemas hash equally. */
    public static function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value);
            }

            return array_map([self::class, 'canonical'], $value);
        }

        return $value;
    }
}
