<?php

namespace Ulams\TopicTypes\Database\Factories\TopicContent\Components;

use Ulams\Cmi5\Models\Cmi5;
use Ulams\Cmi5\Models\Cmi5Au;
use Ulams\Cmi5\Services\Contracts\Cmi5UploadServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Cmi5AuHelper
{
    public static function uploadCmi5(): Cmi5
    {
        $service = app(Cmi5UploadServiceContract::class);
        $file = self::getCmi5UploadedFile();

        return $service->upload($file);
    }

    public static function getCmi5Au(): Cmi5Au
    {
        return self::uploadCmi5()->aus->first();
    }

    protected static function getCmi5UploadedFile(string $fileName = 'cmi5.zip'): UploadedFile
    {
        $filepath = realpath(__DIR__ . '/../../../mocks/' . $fileName);
        $storagePath = Storage::path($fileName);

        copy($filepath, $storagePath);

        return new UploadedFile($storagePath, $fileName, 'application/zip', null, true);
    }
}
