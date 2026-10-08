<?php

namespace EscolaLms\CsvUsers\Import;

use EscolaLms\CsvUsers\Services\Contracts\CsvUserServiceContract;
use EscolaLms\CsvUsers\Services\CsvUserGroupService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserGroupImport extends AbstractUserImport implements ToCollection, WithHeadingRow
{
    public function __construct(string $returnUrl)
    {
        parent::__construct($returnUrl);
    }

    public function collection(Collection $rows): void
    {
        $csvUserService = app(CsvUserServiceContract::class);
        $csvGroupService = app(CsvUserGroupService::class);

        $rows = $this->prepareDataToImport($rows);
        $this->validateData($rows);

        $group = $csvGroupService->saveGroupFromImport($this->prepareGroupData($rows->first()));

        $users = collect();
        foreach ($rows as $row) {
            $users->push($csvUserService->saveUserFromImport($row, $this->returnUrl));
        }

        $group->users()->saveMany($users);
    }

    private function prepareGroupData(Collection $data): Collection
    {
        return $data
            ->filter(function ($value, $key) {
                return Str::startsWith($key, 'group_');
            })
            ->mapWithKeys(function ($value, $key) {
                return [Str::after($key, 'group_') => $value];
            });
    }
}
