<?php

namespace EscolaLms\CsvUsers\Export;

use EscolaLms\Auth\Http\Resources\UserFullResource;
use EscolaLms\Auth\Http\Resources\UserGroupResource;
use EscolaLms\CsvUsers\Export\Sheets\GroupSheet;
use EscolaLms\CsvUsers\Http\Resources\GroupExportResource;
use EscolaLms\CsvUsers\Models\Group;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UserGroupExport implements FromCollection, WithHeadings
{
    private array $userKeys = [];
    private array $groupKeys = [];
    private array $usersArray;
    private Group $group;

    public function __construct(Group $group)
    {
        $this->group = $group;

        $this->usersArray = json_decode(UserFullResource::collection($this->group->users)->toJson(), true);

        foreach ($this->usersArray as $user) {
            $this->userKeys = array_merge($this->userKeys, array_keys($user));
        }

        $this->userKeys = array_unique($this->userKeys);

        $this->groupKeys = array_unique(array_keys(
            json_decode(GroupExportResource::make($this->group)->toJson(), true)
        ));
    }

    public function collection(): Collection
    {
        return collect($this->usersArray)->map(function ($user) {
            foreach ($this->userKeys as $key) {
                $result[$key] = $user[$key] ?? '';
            }
            foreach ($this->groupKeys as $key) {
                $result['group_' . $key] = $this->group[$key] ?? '';
            }

            return $result;
        });
    }

    public function headings(): array
    {
        return array_merge($this->userKeys, $this->groupKeys);
    }
}
