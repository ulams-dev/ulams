<?php

namespace Ulams\LiaScript\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;
use Ulams\LiaScript\Models\LiaScriptDocument;
use Ulams\LiaScript\Models\LiaScriptVersion;

/**
 * Publishes the LiaScript player and course versions to the LiaScript disk, served by the tenant
 * content origin under /liascript/ (api/docs/content-origin.md):
 *
 * - liascript/_player/index.html, player.js: our page, which provides the SCORM 1.2 API to the
 *   LiaScript build and reports slide position and status to the API;
 * - liascript/_player/build/: the LiaScript SCORM 1.2 build (BSD-3-Clause), fetched at image build
 *   time by bin/fetch-player.sh, plus config.js;
 * - liascript/<document>/v<version>/README.md and its assets (copied into the version folder so
 *   relative links resolve).
 */
class LiaScriptPlayer
{
    public const PLAYER_DIR = 'liascript/_player';

    private const RESOURCES = __DIR__ . '/../../resources/player';

    /** Folder with the fetched LiaScript build (bin/fetch-player.sh); tests point it elsewhere. */
    private function build(): string
    {
        return rtrim((string) (config('ulams_liascript.player_build_path') ?: self::RESOURCES . '/build'), '/');
    }

    public function installed(): bool
    {
        return is_file($this->build() . '/.version') && is_file($this->build() . '/index.html');
    }

    public function buildVersion(): ?string
    {
        return $this->installed() ? trim((string) file_get_contents($this->build() . '/.version')) : null;
    }

    public function publishPlayer(bool $force = false): void
    {
        $hash = sha1($this->buildVersion() . sha1_file(self::RESOURCES . '/index.html') . sha1_file(self::RESOURCES . '/player.js'));
        $cacheKey = 'liascript_player_' . $this->diskName();
        if (!$force && Cache::get($cacheKey) === $hash && $this->disk()->exists(self::PLAYER_DIR . '/build/index.html')) {
            return;
        }

        $disk = $this->disk();
        $disk->put(self::PLAYER_DIR . '/index.html', (string) file_get_contents(self::RESOURCES . '/index.html'));
        $disk->put(self::PLAYER_DIR . '/player.js', (string) file_get_contents(self::RESOURCES . '/player.js'));

        foreach ((new Finder())->files()->ignoreDotFiles(false)->in($this->build()) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $contents = (string) file_get_contents($file->getPathname());
            if ($relative === 'index.html') {
                // the exporter's step: load config.js (empty task/quiz/survey lists) before the app
                $contents = preg_replace('/<head>/i', '<head><script src="config.js"></script>', $contents, 1) ?? $contents;
            }
            $disk->put(self::PLAYER_DIR . '/build/' . $relative, $contents);
        }
        $disk->put(self::PLAYER_DIR . '/build/config.js', 'window.config_ = {"task":[],"quiz":[],"survey":[]};');

        Cache::forever($cacheKey, $hash);
    }

    /**
     * Writes a version's Markdown and copies its assets into its own folder.
     *
     * @return string path of the Markdown on the content origin, e.g. /liascript/12/v3/README.md
     */
    public function publishVersion(LiaScriptDocument $document, LiaScriptVersion $version): string
    {
        $disk = $this->disk();
        $folder = "liascript/{$document->getKey()}/v{$version->version}";
        $markdown = $folder . '/README.md';

        if (!$disk->exists($markdown)) {
            foreach ($version->assets ?? [] as $relative => $asset) {
                $target = $folder . '/' . $relative;
                if (($asset['path'] ?? null) !== $target && !$disk->exists($target) && $disk->exists((string) $asset['path'])) {
                    $disk->copy((string) $asset['path'], $target);
                }
            }
            $disk->put($markdown, $version->markdown);
        }

        return '/' . $markdown;
    }

    /**
     * Number of LiaScript sections (one per heading, outside code blocks and the header comment).
     */
    public static function sections(string $markdown): int
    {
        $markdown = (string) preg_replace('/^\s*<!--.*?-->/s', '', $markdown, 1);
        $count = 0;
        $fence = null;
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line, $m)) {
                $fence = $fence === null ? $m[1] : ($fence === $m[1] ? null : $fence);
                continue;
            }
            if ($fence === null && preg_match('/^#{1,6}\s+\S/', $line)) {
                $count++;
            }
        }

        return max(1, $count);
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    private function diskName(): string
    {
        return (string) (config('ulams_liascript.disk') ?: config('filesystems.default'));
    }
}
