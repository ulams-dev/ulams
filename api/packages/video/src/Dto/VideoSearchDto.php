<?php

namespace Ulams\Video\Dto;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\HasCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class VideoSearchDto extends CriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('state')) {
            $criteria->push(
                new HasCriterion(
                    'topic',
                    fn($query) => $query->whereJsonContains('json->ffmpeg->state', $request->get('state')))
            );
        }

        return new static($criteria);
    }
}
