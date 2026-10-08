<?php

namespace Ulams\Recommender\Dto;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\DateCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\InCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Ulams\Recommender\Repositories\Criteria\CategoriesCriterion;
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
