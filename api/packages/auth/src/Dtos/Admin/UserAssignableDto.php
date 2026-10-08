<?php

namespace Ulams\Auth\Dtos\Admin;

use Ulams\Auth\Repositories\Criteria\AssignableByCriterion;
use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Repositories\Criteria\UserSearchCriterion;
use Illuminate\Support\Collection;

class UserAssignableDto extends CriteriaDto implements DtoContract
{
    public static function instantiateFromArray(array $array): self
    {
        $criteria = new Collection();

        if (key_exists('assignable_by', $array) && !is_null($array['assignable_by'])) {
            $criteria->push(new AssignableByCriterion($array['assignable_by']));
        }

        if (key_exists('search', $array) && !is_null($array['search'])) {
            $criteria->push(new UserSearchCriterion($array['search']));
        }

        return new self($criteria);
    }
}
