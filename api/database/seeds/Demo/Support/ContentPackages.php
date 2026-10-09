<?php

namespace Database\Seeders\Demo\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;
use ZipArchive;

/**
 * The interactive content packages of the demo academies (demo-content/<name>, ADR 0088) as
 * library packages of the tenant.
 *
 * The zip comes from `assets/cache/interactive/<name>.zip` (git-ignored). `make -C api demo-packages`
 * builds the zips on the host and puts them there, because the API container sees only `api/`, never
 * `demo-content/`. A zip that is missing is an error, not a skipped topic: a course without its
 * interactive would be a course without a lesson.
 *
 * A package is added once per tenant. Re-seeding with the same zip (same SHA-1, kept in the version's
 * change note) reuses it, a changed zip becomes a new version of the same package.
 */
class ContentPackages
{
    /** Library title of each package (the manifest has no single title string). */
    public const TITLES = [
        'gravity' => 'Gravity: a guided solar system',
        'poland' => 'Poland, measured',
    ];

    public static function cacheDirectory(): string
    {
        return AssetFactory::assetsPath('cache/interactive');
    }

    public static function zipPath(string $name): string
    {
        return self::cacheDirectory() . '/' . $name . '.zip';
    }

    /** The manifest of a cached zip. */
    public static function manifest(string $name): array
    {
        $path = self::zipPath($name);
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('The "%s" interactive package is missing (%s). Build it with `make -C api demo-packages`.', $name, $path));
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("$path is not a readable zip");
        }
        $json = $zip->getFromName('ulams-interactive.json');
        $zip->close();
        $manifest = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($manifest)) {
            throw new RuntimeException("$path has no readable ulams-interactive.json");
        }

        return $manifest;
    }

    /** The package in the tenant's library, uploaded or updated when the zip is new. */
    public static function package(string $name, ?int $authorId = null): InteractivePackage
    {
        $manifest = self::manifest($name);
        $path = self::zipPath($name);
        $hash = 'sha1:' . sha1_file($path);
        $title = self::TITLES[$name] ?? $name;
        $service = app(InteractivePackageServiceContract::class);
        $upload = fn () => new UploadedFile($path, $name . '.zip', 'application/zip', null, true);

        $package = InteractivePackage::query()->where('title', $title)->orderBy('id')->first();
        if ($package === null) {
            return $service->create($upload(), $title, $authorId, $hash);
        }
        $current = $package->current;
        if ($current !== null && $current->change_note === $hash) {
            return $package;
        }

        return $service->addVersion($package, $upload(), $authorId, $hash);
    }

    /** @return array<int, string> the step ids of a package, in tour order */
    public static function stepIds(string $name): array
    {
        return array_values(array_map(fn (array $s) => (string) $s['id'], self::manifest($name)['steps']));
    }
}
