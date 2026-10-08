<?php

namespace EscolaLms\CourseAccess\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Courses\Repositories\Criteria\Primitives\OrderCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('course_id')) {
            $criteria->push(new EqualCriterion('course_id', $request->get('course_id')));
        }
        if ($request->get('user_id')) {
            $criteria->push(new EqualCriterion('user_id', $request->get('user_id')));
        }
        if ($request->get('status')) {
            $criteria->push(new EqualCriterion('status', $request->get('status')));
        }

        $criteria->push(new OrderCriterion($request->get('order_by') ?? 'id', $request->get('order') ?? 'desc'));

        return new static($criteria);
    }
}
