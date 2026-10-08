<?php

namespace EscolaLms\CsvUsers\Http\Controllers;

use EscolaLms\Auth\Dtos\UserFilterCriteriaDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\CsvUsers\Enums\ExportFormatEnum;
use EscolaLms\CsvUsers\Export\UsersExport;
use EscolaLms\CsvUsers\Http\Controllers\Swagger\CsvUserAPISwagger;
use EscolaLms\CsvUsers\Http\Requests\ExportUsersToCsvAPIRequest;
use EscolaLms\CsvUsers\Http\Requests\ImportUsersFromCsvAPIRequest;
use EscolaLms\CsvUsers\Import\UsersImport;
use EscolaLms\CsvUsers\Services\Contracts\CsvUserServiceContract;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CsvUserAPIController extends EscolaLmsBaseController implements CsvUserAPISwagger
{
    protected CsvUserServiceContract $csvUserService;

    public function __construct(CsvUserServiceContract $csvUserService)
    {
        $this->csvUserService = $csvUserService;
    }

    public function export(ExportUsersToCsvAPIRequest $request): BinaryFileResponse
    {
        $userFilterDto = UserFilterCriteriaDto::instantiateFromRequest($request);
        $format = ExportFormatEnum::fromValue($request->input('format', ExportFormatEnum::CSV));

        return Excel::download(
            new UsersExport($this->csvUserService->getDataToExport($userFilterDto)),
            $format->getFilename('users'),
            $format->getWriterType()
        );
    }

    public function import(ImportUsersFromCsvAPIRequest $request): JsonResponse
    {
        Excel::import(new UsersImport($request->input('return_url')), $request->file('file'));

        return $this->sendSuccess('successful operation');
    }
}
