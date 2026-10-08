<?php

namespace EscolaLms\Video\Dto;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\HasCriterion;
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
