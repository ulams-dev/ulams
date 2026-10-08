<?php

namespace Ulams\H5P\Database\Factories;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Ulams\H5P\Models\H5PContent;
use ZipArchive;

/**
 * Test data for `h5p.contents`.
 *
 * H5PContent is read-only through Eloquent (the H5P service owns the table), so
 * this is a plain helper that inserts rows directly. Use it in tests, factories
 * and {@see \Ulams\H5P\Testing\H5PServiceFake} only, never in application code.
 */
class H5PContentFactory
{
    public static function create(array $attributes = []): H5PContent
    {
        $mainLibrary = $attributes['main_library'] ?? 'H5P.MultiChoice';
        $version = $attributes['library_version'] ?? '1.16';
        $title = $attributes['title'] ?? 'H5P content ' . Str::random(8);
        [$major, $minor] = array_pad(explode('.', $version, 2), 2, '0');

        $row = array_merge([
            'user_id' => null,
            'title' => $title,
            'main_library' => $mainLibrary,
            'library_version' => $version,
            'metadata' => [
                'title' => $title,
                'mainLibrary' => $mainLibrary,
                'language' => 'und',
                'embedTypes' => ['div'],
                'license' => 'U',
                'preloadedDependencies' => [
                    ['machineName' => $mainLibrary, 'majorVersion' => $major, 'minorVersion' => $minor],
                ],
            ],
            'parameters' => [
                'question' => '<p>2 + 2 = ?</p>',
                'answers' => [
                    ['text' => '<div>4</div>', 'correct' => true],
                    ['text' => '<div>5</div>', 'correct' => false],
                ],
            ],
        ], $attributes);

        foreach (['metadata', 'parameters'] as $json) {
            if (!is_string($row[$json])) {
                $row[$json] = json_encode($row[$json]);
            }
        }
        if (isset($row['user_id'])) {
            $row['user_id'] = (string) $row['user_id'];
        }

        $id = DB::table('h5p.contents')->insertGetId($row);

        return H5PContent::query()->findOrFail($id);
    }

    /**
     * Creates a row from a real .h5p package (h5p.json + content/content.json).
     */
    public static function fromPackage(string $path, array $attributes = []): H5PContent
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Not a .h5p package: {$path}");
        }
        $metadata = json_decode((string) $zip->getFromName('h5p.json'), true);
        $parameters = json_decode((string) $zip->getFromName('content/content.json'), true);
        $zip->close();
        if (!is_array($metadata) || !isset($metadata['mainLibrary'])) {
            throw new RuntimeException("Invalid h5p.json in {$path}");
        }

        $main = Arr::first(
            $metadata['preloadedDependencies'] ?? [],
            fn ($dep) => ($dep['machineName'] ?? null) === $metadata['mainLibrary']
        );

        return self::create(array_merge([
            'title' => $metadata['title'] ?? '',
            'main_library' => $metadata['mainLibrary'],
            'library_version' => $main ? $main['majorVersion'] . '.' . $main['minorVersion'] : '',
            'metadata' => $metadata,
            'parameters' => $parameters ?? new \stdClass(),
        ], $attributes));
    }

    /**
     * Builds a minimal .h5p package (no libraries, no media) for a row.
     *
     * @return string path of a temporary .h5p file
     */
    public static function toPackage(H5PContent $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'h5p-fake-') . '.h5p';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('h5p.json', json_encode($content->metadata ?? new \stdClass()));
        $zip->addFromString('content/content.json', json_encode($content->parameters ?? new \stdClass()));
        $zip->close();

        return $path;
    }
}
