<?php

namespace EscolaLms\Dictionaries\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DictionaryCriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('name')) {
            $criteria->push(new LikeCriterion('name', $request->get('name')));
        }

        if ($request->get('slug')) {
            $criteria->push(new LikeCriterion('slug', $request->get('slug')));
        }

        return new self($criteria);
    }
}
