<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent\Components;

use EscolaLms\HeadlessH5P\Models\H5PContent;
use EscolaLms\HeadlessH5P\Repositories\Contracts\H5PContentRepositoryContract;
use Illuminate\Http\UploadedFile;

class H5PHelper
{
    public static function createH5PContent(): ?H5PContent
    {
        $h5Path = realpath(__DIR__ . '/../../../mocks/hp5.h5p');
        $tmpPath = __DIR__ . '/../../../mocks/tmp.h5p';
        copy($h5Path, $tmpPath);

        $file =  new UploadedFile($tmpPath, basename($h5Path), 'application/zip', null, true);

        try {
            $h5pContentRepository = app()->make(H5PContentRepositoryContract::class);
            return $h5pContentRepository->upload($file);
        } catch (\Exception $err) {
            echo $err->getMessage();
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }

        return null;
    }
}
