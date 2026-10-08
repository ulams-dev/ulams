<?php

namespace Ulams\Tasks\Dtos;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\DateCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\InCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Ulams\Tasks\Repositories\Criteria\RelatedIdsCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('title')) {
            $criteria->push(new LikeCriterion('title', $request->get('title')));
        }
        if ($request->get('type')) {
            $criteria->push(new EqualCriterion('type', $request->get('type')));
        }
        if ($request->get('user_id')) {
            $criteria->push(new EqualCriterion('user_id', $request->get('user_id')));
        }
        if ($request->get('created_by_id')) {
            $criteria->push(new EqualCriterion('created_by_id', $request->get('created_by_id')));
        }
        if ($request->get('related_typed_ids')) {
            $criteria->push(new RelatedIdsCriterion($request->get('related_typed_ids')));
        }
        if ($request->get('related_type') && $request->get('related_id')) {
            $criteria->push(new EqualCriterion('related_type', $request->get('related_type')));
            $criteria->push(new EqualCriterion('related_id', $request->get('related_id')));
        }
        if ($request->get('related_type') && $request->get('related_ids')) {
            $criteria->push(new EqualCriterion('related_type', $request->get('related_type')));
            $criteria->push(new InCriterion('related_id', $request->get('related_ids')));
        }
        if (($request->get('related_type') && !$request->get('related_id') && ($request->get('related_type') && !$request->get('related_ids')))) {
            $criteria[] = new EqualCriterion('related_type', $request->get('related_type'));
        }
        if ($request->get('due_date_from')) {
            $criteria->push(
                new DateCriterion(
                    'due_date',
                    new Carbon($request->get('due_date_from')),
                    '>='
                )
            );
        }
        if ($request->get('due_date_to')) {
            $criteria->push(
                new DateCriterion(
                    'due_date',
                    new Carbon($request->get('due_date_to')),
                    '<='
                )
            );
        }

        return new static($criteria);
    }
}
