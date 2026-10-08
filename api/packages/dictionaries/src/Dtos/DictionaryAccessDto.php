<?php

namespace EscolaLms\Dictionaries\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DictionaryAccessDto implements DtoContract, InstantiateFromRequest
{
    private Collection $users;

    public function __construct(array $users)
    {
        $this->users = collect($users);
    }

    public function toArray(): array
    {
        return $this->users
            ->mapWithKeys(fn (array $item) => [$item['user_id'] => ['end_date' => $item['end_date'] ?? null]])
            ->toArray();
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new self(
            $request->input('users'),
        );
    }
}
