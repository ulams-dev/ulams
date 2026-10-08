<?php

namespace EscolaLms\Files\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileHelper
{
    public static function getFilePath($fileOrString, string $destinationPath = '/'): ?string
    {
        if (is_a($fileOrString, UploadedFile::class)) {
            return $fileOrString->storePubliclyAs(
                $destinationPath,
                Str::slug(pathinfo($fileOrString->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $fileOrString->getClientOriginalExtension()
            );
        }

        if (is_string($fileOrString) && Storage::exists($fileOrString)) {
            return $fileOrString;
        }

        return null;
    }

    public static function getMimesRule(): ?string
    {
        $mimes = config('files.mimes');
        return $mimes ? 'mimes:' . $mimes : null;
    }
}
