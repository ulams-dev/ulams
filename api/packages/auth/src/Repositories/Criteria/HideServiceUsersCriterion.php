<?php

namespace Ulams\Auth\Repositories\Criteria;

use Illuminate\Database\Eloquent\Builder;
use Ulams\Core\Repositories\Criteria\Criterion;

/**
 * Service users (the passwordless owners of SCIM tokens, e-mail `scim+<provider>@identity.invalid`) are not people:
 * the admin user list does not show them.
 */
class HideServiceUsersCriterion extends Criterion
{
    public function __construct()
    {
    }

    public function apply(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('email')->orWhere('email', 'NOT LIKE', '%@identity.invalid'));
    }
}
