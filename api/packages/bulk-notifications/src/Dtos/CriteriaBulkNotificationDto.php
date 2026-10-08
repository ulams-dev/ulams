<?php

namespace EscolaLms\BulkNotifications\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
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
