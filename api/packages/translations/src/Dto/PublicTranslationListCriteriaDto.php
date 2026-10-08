<?php

namespace EscolaLms\Translations\Dto;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\InCriterion;
use EscolaLms\Translations\Enum\ConstantEnum;
use EscolaLms\Translations\Repositories\Criteria\OrderCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublicTranslationListCriteriaDto extends CriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        $criteria->push(
            new OrderCriterion(
                $request->get('order_by') ?? ConstantEnum::DEFAULT_SORT,
                $request->get('order') ?? ConstantEnum::DEFAULT_SORT_DIRECTION
            )
        );
        $criteria->push(new EqualCriterion('public', true));

        if ($request->get('key')) {
            $criteria->push(new InCriterion('key', $request->get('key')));
        }
        if ($request->get('group')) {
            $criteria->push(new InCriterion('group', $request->get('group')));
        }

        return new static($criteria);
    }
}
