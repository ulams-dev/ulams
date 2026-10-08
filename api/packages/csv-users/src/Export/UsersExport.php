<?php

namespace EscolaLms\CsvUsers\Export;

use EscolaLms\Auth\Http\Resources\UserFullResource;
use EscolaLms\CsvUsers\Http\Resources\UserExportResource;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UsersExport implements FromCollection, WithHeadings
{
    private array $usersArray;
    private array $keys = [];

    public function __construct(Collection $users)
    {
        $this->usersArray = json_decode(UserExportResource::collection($users)->toJson(), true);

        foreach ($this->usersArray as $user) {
            $this->keys = array_merge($this->keys, array_keys($user));
        }

        $this->keys = array_unique($this->keys);
    }

    public function collection(): Collection
    {
        return collect($this->usersArray)->map(function ($user) {
            foreach ($this->keys as $key) {
                $result[$key] = $user[$key] ?? '';
            }

            return $result;
        });
    }

    public function headings(): array
    {
        return $this->keys;
    }
}
