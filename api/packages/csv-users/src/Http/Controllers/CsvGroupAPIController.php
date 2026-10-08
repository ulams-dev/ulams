<?php

namespace Ulams\CsvUsers\Http\Controllers;

use Ulams\Auth\Dtos\UserFilterCriteriaDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\CsvUsers\Enums\ExportFormatEnum;
use Ulams\CsvUsers\Export\UserGroupExport;
use Ulams\CsvUsers\Http\Controllers\Swagger\CsvGroupAPISwagger;
use Ulams\CsvUsers\Http\Requests\ExportUserGroupToCsvAPIRequest;
use Ulams\CsvUsers\Http\Requests\ImportUserGroupFromCsvAPIRequest;
use Ulams\CsvUsers\Import\UserGroupImport;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CsvGroupAPIController extends UlamsBaseController implements CsvGroupAPISwagger
{
    public function export(ExportUserGroupToCsvAPIRequest $request): BinaryFileResponse
    {
        $format = ExportFormatEnum::fromValue($request->input('format', ExportFormatEnum::CSV));

        return Excel::download(
            new UserGroupExport($request->getGroup()),
            $format->getFilename('group'),
            $format->getWriterType()
        );
    }

    public function import(ImportUserGroupFromCsvAPIRequest $request): JsonResponse
    {
        Excel::import(new UserGroupImport($request->input('return_url')), $request->file('file'));

        return $this->sendSuccess('successful operation');
    }
}
