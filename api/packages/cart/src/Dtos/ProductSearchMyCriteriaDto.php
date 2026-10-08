<?php

namespace Ulams\Cart\Dtos;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\HasCriterion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProductSearchMyCriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        $criteria->push(new HasCriterion('users', fn (Builder $query) => $query->where('user_id', $request->user()->getKey())));

        if ($request->has('type')) {
            $criteria->push(new EqualCriterion('type', $request->get('type')));
        }

        if ($request->has('active')) {
            if ($request->boolean('active'))
                $criteria->push(new HasCriterion('users', fn (Builder $query) => $query
                    ->where('user_id', $request->user()->getKey())
                    ->where(fn (Builder $query) => $query
                        ->whereDate('end_date', '>=', Carbon::now())
                        ->orWhereNull('end_date'))
                    )
                );
            else
                $criteria->push(new HasCriterion('users', fn (Builder $query) => $query
                    ->where('user_id', $request->user()->getKey())
                    ->where('end_date', '<', Carbon::now())));
        }

        return new self($criteria);
    }
}
