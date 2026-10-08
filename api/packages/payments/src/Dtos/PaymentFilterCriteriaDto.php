<?php

namespace EscolaLms\Payments\Dtos;

use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\DateCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Payments\Repositories\Criteria\LikeCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PaymentFilterCriteriaDto extends CriteriaDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('status')) {
            $criteria->push(new EqualCriterion('status', $request->input('status')));
        }
        if ($request->has('user_id')) {
            $criteria->push(new EqualCriterion('user_id', $request->input('user_id')));
        }
        if ($request->has('payable_id')) {
            $criteria->push(new EqualCriterion('payable_id', $request->input('payable_id')));
        }
        if ($request->has('payable_type')) {
            $criteria->push(new EqualCriterion('payable_type', $request->input('payable_type')));
        }
        if ($request->has('date_from')) {
            $criteria->push(new DateCriterion('created_at', Carbon::parse($request->input('date_from')), '>='));
        }
        if ($request->has('date_to')) {
            $criteria->push(new DateCriterion('created_at', Carbon::parse($request->input('date_to')), '<='));
        }
        if ($request->has('order_id')) {
            $criteria->push(new LikeCriterion('order_id', $request->input('order_id')));
        }

        return new self($criteria);
    }
}
