<?php

namespace Ulams\LiaScript\Import;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Finder\Finder;
use Ulams\CoursesImportExport\Strategies\Contract\TopicImportStrategy;
use Ulams\CoursesImportExport\Support\ImportPath;
use Ulams\LiaScript\Services\LiaScriptService;
use ZipArchive;

/**
 * Course import: creates a new LiaScript document from the exported folder (README.md and
 * assets, see LiaScriptTopicExportResource). The folder is repacked and goes through the normal
 * LiaScript upload (upload guard, safe extraction, Markdown checks), so an imported course gets
 * the same checks as an uploaded one. Returns the new document id (the topic's value).
 */
class LiaScriptTopicImportStrategy implements TopicImportStrategy
{
    public function __construct(private readonly LiaScriptService $service)
    {
    }

    public function make(string $path, array $data): ?int
    {
        $folder = ImportPath::resolve($path, $data['liascript_folder'] ?? null, true);
        if ($folder === null || !is_dir($folder) || !is_file($folder . DIRECTORY_SEPARATOR . 'README.md')) {
            return null;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'liascript-import-');
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

            $title = is_string($data['liascript_title'] ?? null) ? $data['liascript_title'] : null;
            $document = $this->service->create($title, null, new UploadedFile($zipPath, 'liascript.zip', 'application/zip', null, true), null);

            return $document->getKey();
        } finally {
            @unlink($zipPath);
        }
    }
}
