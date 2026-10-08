<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent\Components;

use EscolaLms\Scorm\Services\Contracts\ScormServiceContract;
use Illuminate\Http\UploadedFile;
use Peopleaps\Scorm\Model\ScormScoModel;

class ScormScoHelper
{
    public static function createScorm(): ?array
    {
        $mockPath = __DIR__ . '/../../../mocks/scorm.zip';
        $tmpPath = __DIR__ . '/../../../mocks/tmp.zip';
        copy($mockPath, $tmpPath);

        $file =  new UploadedFile($tmpPath, basename($mockPath), 'application/zip', null, true);

        try {
            $scormService = app()->make(ScormServiceContract::class);
            return $scormService->uploadScormArchive($file);
        } catch (\Exception $err) {
            echo 'Error | File: ' . $err->getFile() . ' | Line: ' . $err->getLine() . ' | Message: ' . $err->getMessage();
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }

        return null;
    }

    public static function getScormSco(): ScormScoModel
    {
        $scorm = self::createScorm();

        return $scorm['model']->scos->first();
    }
}
