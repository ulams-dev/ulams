<?php

namespace EscolaLms\TopicTypeGift\Dtos\Criteria;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\InCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExportQuestionsCriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('topic_gift_quiz_id')) {
            $criteria->push(new EqualCriterion('topic_gift_quiz_id', $request->get('topic_gift_quiz_id')));
        }

        if ($request->has('category_ids')) {
            $criteria->push(new InCriterion('category_id', $request->get('category_ids')));
        }

        if ($request->has('ids')) {
            $criteria->push(new InCriterion('id', $request->get('ids')));
        }

        return new static($criteria);
    }
}
