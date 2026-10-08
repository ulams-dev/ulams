<?php

namespace EscolaLms\CsvUsers\Http\Controllers;

use EscolaLms\Auth\Dtos\UserFilterCriteriaDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\CsvUsers\Enums\ExportFormatEnum;
use EscolaLms\CsvUsers\Export\UserGroupExport;
use EscolaLms\CsvUsers\Http\Controllers\Swagger\CsvGroupAPISwagger;
use EscolaLms\CsvUsers\Http\Requests\ExportUserGroupToCsvAPIRequest;
use EscolaLms\CsvUsers\Http\Requests\ImportUserGroupFromCsvAPIRequest;
use EscolaLms\CsvUsers\Import\UserGroupImport;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CsvGroupAPIController extends EscolaLmsBaseController implements CsvGroupAPISwagger
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
