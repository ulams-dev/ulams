<?php

namespace EscolaLms\TopicTypeProject\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\HasCriterion;
use EscolaLms\Courses\Repositories\Criteria\Primitives\OrderCriterion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('topic_id')) {
            $criteria->push(new EqualCriterion('topic_id', $request->get('topic_id')));
        }
        if ($request->get('course_id')) {
            $courseId = $request->get('course_id');
            $criteria->push(new HasCriterion('topic', function (Builder $query) use ($courseId) {
                $query->whereRelation('lesson', 'course_id', '=', $courseId);
            }));
        }
        if ($request->get('user_id')) {
            $criteria->push(new EqualCriterion('user_id', $request->get('user_id')));
        }

        $criteria->push(new OrderCriterion($request->get('order_by') ?? 'id', $request->get('order') ?? 'desc'));

        return new static($criteria);
    }
}
