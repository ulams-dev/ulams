<?php

namespace EscolaLms\Categories\Dtos;

use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CategoryCriteriaFilterDto extends CriteriaDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('id')) {
            $criteria->push(new EqualCriterion('id', $request->input('id')));
        }

        if ($request->has('name')) {
            $criteria->push(new LikeCriterion('name', $request->input('name')));
        }

        if ($request->has('slug')) {
            $criteria->push(new LikeCriterion('slug', $request->input('slug')));
        }

        return new self($criteria);
    }
}
