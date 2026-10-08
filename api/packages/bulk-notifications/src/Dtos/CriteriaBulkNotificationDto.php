<?php

namespace Ulams\BulkNotifications\Dtos;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CriteriaBulkNotificationDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('channel')) {
            $criteria->push(new EqualCriterion('channel', $request->get('channel')));
        }

        return new static($criteria);
    }
}
