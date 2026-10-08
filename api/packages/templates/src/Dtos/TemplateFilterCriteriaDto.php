<?php

namespace EscolaLms\Templates\Dtos;

use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\DateCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\IsNullCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TemplateFilterCriteriaDto extends CriteriaDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('name')) {
            $criteria->push(new LikeCriterion('name', $request->input('name')));
        }

        if ($request->has('date_from')) {
            $criteria->push(
                new DateCriterion('created_at', Carbon::parse($request->input('date_from')), '>=')
            );
        }

        if ($request->has('date_to')) {
            $criteria->push(
                new DateCriterion('created_at', Carbon::parse($request->input('date_to')), '<=')
            );
        }

        if ($request->has('event')) {
            $criteria->push(new EqualCriterion('event', $request->input('event')));
        }

        if ($request->has('default')) {
            $criteria->push(new EqualCriterion('default', $request->input('default')));
        }

        if ($request->has('channel')) {
            $criteria->push(new EqualCriterion('channel', $request->input('channel')));
        }

        return new self($criteria);
    }
}
