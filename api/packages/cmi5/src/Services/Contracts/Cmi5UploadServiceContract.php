<?php

namespace Ulams\Cmi5\Services\Contracts;

use Ulams\Cmi5\Models\Cmi5;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface Cmi5UploadServiceContract
{
    public function upload(UploadedFile $file): Cmi5;
}
