<?php

namespace Ulams\Interactive\Import;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Finder\Finder;
use Ulams\CoursesImportExport\Strategies\Contract\TopicImportStrategy;
use Ulams\CoursesImportExport\Support\ImportPath;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;
use ZipArchive;

/**
 * Course import: creates a new package from the exported folder. The folder is repacked and goes
 * through the normal upload (upload guard, extension allow-list, manifest schema), so an imported
 * course gets the same checks as an uploaded one. Returns the new package id (the topic's value).
 */
class InteractiveTopicImportStrategy implements TopicImportStrategy
{
    public function __construct(private readonly InteractivePackageServiceContract $service)
    {
    }

    public function make(string $path, array $data): ?int
    {
        $folder = ImportPath::resolve($path, $data['interactive_folder'] ?? null, true);
        $manifest = (string) config('ulams_interactive.manifest_file', 'ulams-interactive.json');
        if ($folder === null || !is_dir($folder) || !is_file($folder . DIRECTORY_SEPARATOR . $manifest)) {
            return null;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'interactive-import-');
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return null;
            }
            // regular files only: symlinks in the extracted import are not followed
            foreach ((new Finder())->files()->ignoreDotFiles(false)->ignoreVCS(true)->in($folder) as $file) {
                if (is_link($file->getPathname())) {
                    continue;
                }
                $zip->addFile($file->getPathname(), str_replace('\\', '/', $file->getRelativePathname()));
            }
            $zip->close();

            $title = is_string($data['interactive_title'] ?? null) ? $data['interactive_title'] : null;

            return $this->service->create(new UploadedFile($zipPath, 'interactive.zip', 'application/zip', null, true), $title, null)->getKey();
        } finally {
            @unlink($zipPath);
        }
    }
}
