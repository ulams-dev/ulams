<?php

namespace Ulams\Webinar\Dto;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Repositories\Criteria\UserSearchCriterion;
use Ulams\Webinar\Repositories\Criteria\WebinarUserCriterion;
use Illuminate\Support\Collection;

class WebinarUserDto extends CriteriaDto implements DtoContract
{
    public static function instantiateFromArray(array $array): self
    {
        $criteria = new Collection();

        if (key_exists('webinar_id', $array) && !is_null($array['webinar_id'])) {
            $criteria->push(new WebinarUserCriterion($array['webinar_id']));
        }

        if (key_exists('search', $array) && !is_null($array['search'])) {
            $criteria->push(new UserSearchCriterion($array['search']));
        }

        return new self($criteria);
    }
}
