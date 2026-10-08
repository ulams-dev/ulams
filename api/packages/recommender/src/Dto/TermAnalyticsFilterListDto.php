<?php

namespace EscolaLms\Recommender\Dto;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\DateCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\InCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\LikeCriterion;
use EscolaLms\Recommender\Repositories\Criteria\CategoriesCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TermAnalyticsFilterListDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('name')) {
            $criteria->push(new LikeCriterion('m.name', $request->get('name')));
        }

        if ($request->get('date_from')) {
            $criteria->push(new DateCriterion('ta.term', $request->get('date_from'), '>='));
        }

        if ($request->get('date_to')) {
            $criteria->push(new DateCriterion('ta.term', $request->get('date_to'), '<='));
        }

        if ($request->get('ids')) {
            $criteria->push(new InCriterion('model_id', $request->get('ids')));
        }

        if ($request->get('categories')) {
            $criteria->push(new CategoriesCriterion($request->get('categories')));
        }

        return new self($criteria);
    }
}
