<?php

namespace Ulams\CourseAccess\Dtos;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\Courses\Repositories\Criteria\Primitives\OrderCriterion;
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
