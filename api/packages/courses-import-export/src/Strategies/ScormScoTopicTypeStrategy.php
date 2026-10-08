<?php

namespace EscolaLms\CoursesImportExport\Strategies;

use EscolaLms\CoursesImportExport\Strategies\Contract\TopicImportStrategy;
use EscolaLms\Scorm\Services\Contracts\ScormServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ScormScoTopicTypeStrategy implements TopicImportStrategy
{
    private ScormServiceContract $scormService;

    public function __construct()
    {
        $this->scormService = app(ScormServiceContract::class);
    }

    public function make(string $path, array $data): ?int
    {
        if (!$data['identifier'] || !$data['scorm_file']) {
            return null;
        }

        $filePath = $path . DIRECTORY_SEPARATOR . $data['scorm_file'];
        if (!File::exists($filePath)) {
            return null;
        }

        $file = new UploadedFile(
            $filePath,
            'export.zip',
            null,
            null,
            true
        );

        $scormData = $this->scormService->uploadScormArchive($file);

        return $this->searchScormSco($data['identifier'], $scormData['scormData']['scos']);
    }

    private function searchScormSco(string $identifier, array $data): ?int
    {
        foreach ($data as $item) {
            if ($item->identifier === $identifier) {
                return $item->id;
            }

            if ($item->scoChildren) {
                return $this->searchScormSco($identifier, $item->scoChildren);
            }
        }

        return null;
    }
}
