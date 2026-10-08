<?php

namespace Ulams\Permissions\Dtos;

use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RoleFilterCriteriaDto extends CriteriaDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('name')) {
            $criteria->push(new LikeCriterion('name', $request->input('name')));
        }

        return new self($criteria);
    }
}
