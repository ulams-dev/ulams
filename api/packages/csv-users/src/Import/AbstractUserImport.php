<?php

namespace EscolaLms\CsvUsers\Import;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

abstract class AbstractUserImport
{
    protected string $returnUrl;

    public function __construct(string $returnUrl)
    {
        HeadingRowFormatter::default('none');
        $this->returnUrl = $returnUrl;
    }

    public function rules(): array
    {
        return [
            '*.email' => 'required',
            '*.password' => ['sometimes', 'string', 'min:6'],
            '*.first_name' => 'required',
            '*.last_name' => 'required',
            '*.roles' => ['nullable', 'array'],
            '*.roles.*' => ['exists:roles,name'],
            '*.permissions' => ['nullable', 'array'],
            '*.permissions.*' => ['exists:permissions,name'],
            '*.groups' => ['array'],
            '*.groups.*' => ['string:255'],
        ];
    }

    protected function prepareDataToImport(Collection $data): Collection
    {
        $arrayKeys = ['roles', 'permissions', 'groups'];

        return $data->map(function (Collection $item) use ($arrayKeys) {
            foreach ($arrayKeys as $key) {
                $item->put($key, json_decode($item->get($key), true) ?? []);
            }

            $item->put('is_active', $item->get('is_active') ?? false);
            $item->forget('created_at');
            $item->forget('updated_at');

            if ($item->has('path_avatar')) {
                $item->put('path_avatar', $item->get('path_avatar') !== null && Storage::exists($item->get('path_avatar'))
                    ? $item->get('path_avatar')
                    : null
                );
            }

            if ($item->get('password')) {
                $item->put('password', $item->get('password'));
            } else {
                $item->forget('password');
            }

            return $item;
        });
    }

    protected function validateData(Collection $data)
    {
        Validator::make($data->toArray(), $this->rules())->validate();
    }
}
