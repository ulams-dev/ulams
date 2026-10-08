<?php

namespace EscolaLms\Pages\Dtos;

use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PagesFilterCriteriaDto extends CriteriaDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('title')) {
            $criteria->push(new LikeCriterion('title', $request->input('title')));
        }
        if ($request->has('slug')) {
            $criteria->push(new LikeCriterion('slug', $request->input('slug')));
        }
        if ($request->has('author_id')) {
            $criteria->push(new EqualCriterion('author_id', $request->input('author_id')));
        }


        return new self($criteria);
    }
}
