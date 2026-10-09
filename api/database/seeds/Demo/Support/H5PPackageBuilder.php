<?php

namespace Database\Seeders\Demo\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;
use ZipArchive;

/**
 * Builds demo H5P packages with our own content on top of public sample
 * packages: the libraries come from the sample (h5p.org export or the H5P Hub
 * content-type package, the same sources as api/h5p src/cli/samples.ts), the
 * content.json and media are ours. The result is imported through the H5P
 * service exactly like an author's upload.
 *
 * Downloads are cached in Demo/assets/cache (git-ignored).
 */
class H5PPackageBuilder
{
    public const BASES = [
        'image-hotspots' => 'https://api.h5p.org/v1/content-types/H5P.ImageHotspots',
        'drag-and-drop' => 'https://h5p.org/sites/default/files/h5p/exports/drag-and-drop-712.h5p',
        'dialog-cards' => 'https://h5p.org/sites/default/files/h5p/exports/dialog-cards-620.h5p',
        'branching-scenario' => 'https://api.h5p.org/v1/content-types/H5P.BranchingScenario',
    ];

    private H5PServiceClientContract $client;

    public function __construct(H5PServiceClientContract $client)
    {
        $this->client = $client;
    }

    public static function uuid(): string
    {
        return (string) Str::uuid();
    }

    public function basePackage(string $base): string
    {
        if (!isset(self::BASES[$base])) {
            throw new RuntimeException("Unknown H5P base package $base");
        }
        $dir = AssetFactory::assetsPath('cache');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $base . '.h5p';
        if (is_file($path) && filesize($path) > 0) {
            return $path;
        }
        $response = Http::timeout(120)->withOptions(['sink' => $path])->get(self::BASES[$base]);
        if (!$response->successful()) {
            @unlink($path);
            throw new RuntimeException("Download of H5P base package $base failed with HTTP " . $response->status());
        }

        return $path;
    }

    /**
     * Writes a new .h5p: libraries from the base package, h5p.json from the base
     * with our title, content.json = base params overridden by $params.
     *
     * @param array<string, mixed>  $params top-level keys replace the base params
     * @param array<string, string> $media  path under content/ => local file
     */
    public function build(string $base, string $title, array $params, array $media, string $target): string
    {
        $source = new ZipArchive();
        if ($source->open($this->basePackage($base)) !== true) {
            throw new RuntimeException("Cannot open base package $base");
        }
        $h5pJson = json_decode((string) $source->getFromName('h5p.json'), true);
        $baseParams = json_decode((string) $source->getFromName('content/content.json'), true) ?: [];

        $out = new ZipArchive();
        if ($out->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create $target");
        }
        for ($i = 0; $i < $source->numFiles; $i++) {
            $name = (string) $source->getNameIndex($i);
            if ($name === 'h5p.json' || str_starts_with($name, 'content/') || str_ends_with($name, '/')) {
                continue;
            }
            $out->addFromString($name, (string) $source->getFromIndex($i));
        }
        $source->close();

        $h5pJson['title'] = $title;
        $h5pJson['language'] = 'en';
        $h5pJson['license'] = 'U';
        $out->addFromString('h5p.json', (string) json_encode($h5pJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $out->addFromString('content/content.json', (string) json_encode(array_replace($baseParams, $params), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        foreach ($media as $inside => $local) {
            $out->addFile($local, 'content/' . $inside);
        }
        $out->close();

        return $target;
    }

    public function upload(string $package): int
    {
        return $this->client->upload($package);
    }

    public function delete(int $id): void
    {
        try {
            $this->client->delete($id);
        } catch (\Throwable $e) {
            // content already gone or service down: nothing to clean up
        }
    }

    /** @return array<string, mixed> */
    public static function text(string $html, string $library = 'H5P.AdvancedText 1.1', string $title = 'Text'): array
    {
        return [
            'library' => $library,
            'params' => ['text' => $html],
            'subContentId' => self::uuid(),
            'metadata' => ['contentType' => 'Text', 'license' => 'U', 'title' => $title],
        ];
    }

    /** @return array<string, mixed> */
    public static function imageInfo(string $path, string $inside): array
    {
        [$w, $h] = getimagesize($path) ?: [800, 500];

        return [
            'path' => $inside,
            'mime' => str_ends_with($inside, '.jpg') ? 'image/jpeg' : 'image/png',
            'copyright' => ['license' => 'U'],
            'width' => $w,
            'height' => $h,
        ];
    }
}
