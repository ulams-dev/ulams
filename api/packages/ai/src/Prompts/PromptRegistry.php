<?php

namespace Ulams\Ai\Prompts;

use InvalidArgumentException;

/**
 * Versioned prompt files owned by the packages that use them:
 * `<dir>/<task>/v<N>.md`, with a front matter block (id, version, task, schema, changelog).
 * The highest version is used unless config `ai.prompt_pins.<namespace>/<task>` pins another.
 * Old versions stay in the tree so a logged call can be reproduced.
 */
final class PromptRegistry
{
    /** @var array<string,string> namespace → directory */
    private array $paths = [];

    /** @var array<string,Prompt> */
    private array $cache = [];

    public function addPath(string $namespace, string $directory): void
    {
        $this->paths[$namespace] = rtrim($directory, '/');
    }

    public function get(string $namespace, string $task, ?int $version = null): Prompt
    {
        $version ??= config("ai.prompt_pins.{$namespace}/{$task}") ?: $this->latestVersion($namespace, $task);
        $key = "{$namespace}/{$task}@{$version}";

        return $this->cache[$key] ??= $this->load($namespace, $task, (int) $version);
    }

    /** @return int[] */
    public function versions(string $namespace, string $task): array
    {
        $dir = $this->dir($namespace) . "/{$task}";
        $versions = [];
        foreach (glob("{$dir}/v*.md") ?: [] as $file) {
            if (preg_match('/\/v(\d+)\.md$/', $file, $m)) {
                $versions[] = (int) $m[1];
            }
        }
        sort($versions);

        return $versions;
    }

    /** @return array<string,string[]> namespace/task → versions found */
    public function all(): array
    {
        $out = [];
        foreach ($this->paths as $namespace => $dir) {
            foreach (glob("{$dir}/*", GLOB_ONLYDIR) ?: [] as $taskDir) {
                $task = basename($taskDir);
                $out["{$namespace}/{$task}"] = array_map(fn ($v) => "v{$v}", $this->versions($namespace, $task));
            }
        }

        return $out;
    }

    private function latestVersion(string $namespace, string $task): int
    {
        $versions = $this->versions($namespace, $task);
        if ($versions === []) {
            throw new InvalidArgumentException("No prompt files for {$namespace}/{$task}");
        }

        return (int) end($versions);
    }

    private function load(string $namespace, string $task, int $version): Prompt
    {
        $file = $this->dir($namespace) . "/{$task}/v{$version}.md";
        if (!is_file($file)) {
            throw new InvalidArgumentException("Prompt file not found: {$file}");
        }
        [$meta, $body] = self::parse((string) file_get_contents($file));
        if ((int) ($meta['version'] ?? 0) !== $version) {
            throw new InvalidArgumentException("Prompt {$file}: front matter version must be {$version}");
        }

        return new Prompt((string) ($meta['id'] ?? "{$namespace}/{$task}"), $version, trim($body), $meta);
    }

    private function dir(string $namespace): string
    {
        if (!isset($this->paths[$namespace])) {
            throw new InvalidArgumentException("Unknown prompt namespace {$namespace}");
        }

        return $this->paths[$namespace];
    }

    /**
     * Parses a `---` front matter block of `key: value` lines.
     *
     * @return array{0:array<string,string>,1:string}
     */
    public static function parse(string $content): array
    {
        if (!preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $content, $m)) {
            return [[], $content];
        }
        $meta = [];
        foreach (preg_split('/\R/', $m[1]) ?: [] as $line) {
            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $line, $kv)) {
                $meta[$kv[1]] = trim($kv[2], " \"'");
            }
        }

        return [$meta, $m[2]];
    }
}
