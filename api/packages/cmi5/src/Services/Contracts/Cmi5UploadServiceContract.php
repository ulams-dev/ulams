<?php

namespace EscolaLms\Cmi5\Services\Contracts;

use EscolaLms\Cmi5\Models\Cmi5;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface Cmi5UploadServiceContract
{
    public function upload(UploadedFile $file): Cmi5;
}
