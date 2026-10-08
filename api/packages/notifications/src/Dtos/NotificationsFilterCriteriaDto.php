<?php

namespace EscolaLms\Notifications\Dtos;

use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\DateCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\IsNullCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationsFilterCriteriaDto extends CriteriaDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('event')) {
            $criteria->push(new EqualCriterion('event', $request->input('event')));
        }
        if (!$request->has('include_read') || !$request->input('include_read')) {
            $criteria->push(new IsNullCriterion('read_at'));
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

        return new self($criteria);
    }
}
