<?php

namespace Ulams\Interactive\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Models\InteractivePackageVersion;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\UploadGuard;
use Ulams\Uploads\Zip\SafeExtractor;
use Ulams\Uploads\Zip\ZipEntry;
use Ulams\Uploads\Zip\ZipInspector;
use ZipArchive;

/**
 * The package library (ADR 0086). An upload goes through the upload guard (size, type, virus scan,
 * zip safety), then the archive is read without extracting: file names against the allow-list, the
 * manifest against its schema. Only then are the files written, entry by entry, to
 * interactive/<storage_key>/v<n>/ on the package disk. Versions are immutable.
 */
class InteractivePackageService implements InteractivePackageServiceContract
{
    public const KIND = 'interactive';

    public function __construct(
        private readonly UploadGuard $guard,
        private readonly ZipInspector $inspector,
        private readonly SafeExtractor $extractor,
        private readonly ManifestValidator $validator,
    ) {
    }

    public function create(UploadedFile $zip, ?string $title, ?int $authorId, ?string $note = null): InteractivePackage
    {
        [$manifest, $entries] = $this->inspect($zip);

        return DB::transaction(function () use ($zip, $title, $authorId, $note, $manifest, $entries) {
            $package = InteractivePackage::query()->create([
                'title' => $this->title($title, $manifest),
                'current_version' => 0,
                'author_id' => $authorId,
            ]);
            $this->store($package, 1, $zip, $manifest, $entries, $authorId, $note);

            return $package->refresh();
        });
    }

    public function addVersion(InteractivePackage $package, UploadedFile $zip, ?int $authorId, ?string $note = null): InteractivePackage
    {
        [$manifest, $entries] = $this->inspect($zip);

        return DB::transaction(function () use ($package, $zip, $authorId, $note, $manifest, $entries) {
            /** @var InteractivePackage $locked */
            $locked = InteractivePackage::query()->lockForUpdate()->findOrFail($package->getKey());
            $current = $locked->version($locked->current_version);
            if ($current !== null && ($current->manifest['id'] ?? null) !== $manifest['id']) {
                throw ValidationException::withMessages(['manifest' => [sprintf('The manifest id "%s" differs from the package\'s "%s". Upload it as a new package.', $manifest['id'], $current->manifest['id'] ?? '')]]);
            }
            $this->store($locked, $locked->current_version + 1, $zip, $manifest, $entries, $authorId, $note);

            return $locked->refresh();
        });
    }

    public function rename(InteractivePackage $package, string $title): InteractivePackage
    {
        $package->update(['title' => mb_substr($title, 0, 255)]);

        return $package->refresh();
    }

    public function delete(InteractivePackage $package): void
    {
        $directory = 'interactive/' . $package->storage_key;
        $package->delete();
        $this->disk()->deleteDirectory($directory);
    }

    /**
     * Reads the archive without extracting it.
     *
     * @return array{0: array<string, mixed>, 1: ZipEntry[]}
     */
    private function inspect(UploadedFile $file): array
    {
        try {
            $this->guard->check($file, self::KIND);
            $entries = array_values(array_filter(
                $this->inspector->inspect((string) $file->getRealPath(), $this->guard->limitsFor(self::KIND)),
                fn (ZipEntry $e) => !$e->isDirectory
            ));
        } catch (UploadRejected $e) {
            throw ValidationException::withMessages(['file' => [$e->getMessage()]]);
        }

        $allowed = array_map('strtolower', (array) config('ulams_interactive.allowed_extensions', []));
        $problems = [];
        foreach ($entries as $entry) {
            foreach ((array) config('ulams_interactive.denied_names', []) as $pattern) {
                if (preg_match($pattern, $entry->path)) {
                    $problems[] = sprintf('"%s" is not allowed in a package.', $this->printable($entry->path));
                    continue 2;
                }
            }
            $extension = strtolower(pathinfo($entry->path, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed, true)) {
                $problems[] = sprintf('"%s": .%s files are not allowed in a package.', $this->printable($entry->path), $extension);
            }
        }
        if ($problems !== []) {
            throw ValidationException::withMessages(['file' => array_slice($problems, 0, 10)]);
        }

        $manifestFile = (string) config('ulams_interactive.manifest_file', 'ulams-interactive.json');
        $manifestEntry = collect($entries)->first(fn (ZipEntry $e) => $e->path === $manifestFile);
        if ($manifestEntry === null) {
            throw ValidationException::withMessages(['manifest' => ["The archive needs {$manifestFile} at its root."]]);
        }
        if ($manifestEntry->size > (int) config('ulams_interactive.max_manifest_bytes', 262144)) {
            throw ValidationException::withMessages(['manifest' => ['The manifest is too large.']]);
        }

        $zip = new ZipArchive();
        if ($zip->open((string) $file->getRealPath(), ZipArchive::RDONLY) !== true) {
            throw ValidationException::withMessages(['file' => ['The file is not a readable zip archive.']]);
        }
        try {
            // read at most one byte more than the limit: the central directory may lie about the size
            $json = (string) $zip->getFromIndex($manifestEntry->index, (int) config('ulams_interactive.max_manifest_bytes', 262144) + 1);
        } finally {
            $zip->close();
        }
        if (strlen($json) > (int) config('ulams_interactive.max_manifest_bytes', 262144)) {
            throw ValidationException::withMessages(['manifest' => ['The manifest is too large.']]);
        }

        $manifest = $this->validator->validate($json, array_map(fn (ZipEntry $e) => $e->path, $entries));

        return [$manifest, $entries];
    }

    /**
     * @param array<string, mixed> $manifest
     * @param ZipEntry[] $entries
     */
    private function store(InteractivePackage $package, int $version, UploadedFile $zip, array $manifest, array $entries, ?int $authorId, ?string $note): InteractivePackageVersion
    {
        $disk = $this->disk();
        $directory = $package->directory($version);

        try {
            $written = $this->extractor->extractToDisk((string) $zip->getRealPath(), $disk, $directory, $this->guard->limitsFor(self::KIND));
            $files = [];
            $total = 0;
            foreach ($written as $stored) {
                $relative = substr($stored, strlen($directory) + 1);
                $size = (int) $disk->size($stored);
                $total += $size;
                $files[$relative] = ['size' => $size, 'sha256' => $this->hash($disk, $stored)];
            }

            $record = InteractivePackageVersion::query()->create([
                'interactive_package_id' => $package->getKey(),
                'version' => $version,
                'manifest' => $manifest,
                'entry' => $manifest['entry'],
                'files' => $files,
                'total_bytes' => $total,
                'licence' => $manifest['licence'],
                'change_note' => $note === null ? null : mb_substr($note, 0, 500),
                'author_id' => $authorId,
            ]);
            $package->update(['current_version' => $version]);

            return $record;
        } catch (UploadRejected $e) {
            $disk->deleteDirectory($directory);
            throw ValidationException::withMessages(['file' => [$e->getMessage()]]);
        } catch (Throwable $e) {
            $disk->deleteDirectory($directory);
            throw $e;
        }
    }

    private function hash(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (!is_resource($stream)) {
            return hash('sha256', (string) $disk->get($path));
        }
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    /** @param array<string, mixed> $manifest */
    private function title(?string $title, array $manifest): string
    {
        if ($title !== null && trim($title) !== '') {
            return mb_substr(trim($title), 0, 255);
        }

        return mb_substr((string) ($manifest['title'][$manifest['defaultLocale']] ?? $manifest['id']), 0, 255);
    }

    private function printable(string $name): string
    {
        return mb_strimwidth((string) preg_replace('/[\x00-\x1F\x7F]/', '?', $name), 0, 120, '…');
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('ulams_interactive.disk') ?: config('filesystems.default'));
    }
}
