<?php

namespace EscolaLms\CoursesImportExport\Services\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

interface ExportImportServiceContract
{
    public function export($courseId, bool $withUrl = true): string;

    public function import(UploadedFile $file): Model;
}
