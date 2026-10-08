<?php

namespace Ulams\CsvUsers\Http\Controllers;

use Ulams\Auth\Dtos\UserFilterCriteriaDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\CsvUsers\Enums\ExportFormatEnum;
use Ulams\CsvUsers\Export\UsersExport;
use Ulams\CsvUsers\Http\Controllers\Swagger\CsvUserAPISwagger;
use Ulams\CsvUsers\Http\Requests\ExportUsersToCsvAPIRequest;
use Ulams\CsvUsers\Http\Requests\ImportUsersFromCsvAPIRequest;
use Ulams\CsvUsers\Import\UsersImport;
use Ulams\CsvUsers\Services\Contracts\CsvUserServiceContract;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CsvUserAPIController extends UlamsBaseController implements CsvUserAPISwagger
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
