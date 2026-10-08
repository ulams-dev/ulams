<?php

namespace Ulams\Bookmarks\Dtos;

use Ulams\Bookmarks\Enums\BookmarkPermissionEnum;
use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\InCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\IsNullCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\NotNullCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('has_value')) {
            $criteria->push(
                $request->boolean('has_value')
                    ? new NotNullCriterion('value')
                    : new IsNullCriterion('value')
            );

        }
        if ($request->user()->can(BookmarkPermissionEnum::LIST_BOOKMARK) && !$request->user()->can(BookmarkPermissionEnum::LIST_BOOKMARK_OWN) && $request->get('user_id')) {
            $criteria->push(new EqualCriterion('user_id', $request->get('user_id')));
        }
        if ($request->get('bookmarkable_type') && $request->get('bookmarkable_ids')) {
            $criteria->push(new EqualCriterion('bookmarkable_type', $request->get('bookmarkable_type')));
            $criteria->push(new InCriterion('bookmarkable_id', $request->get('bookmarkable_ids')));
        }
        if ($request->get('bookmarkable_type') && $request->get('bookmarkable_id')) {
            $criteria->push(new EqualCriterion('bookmarkable_type', $request->get('bookmarkable_type')));
            $criteria->push(new EqualCriterion('bookmarkable_id', $request->get('bookmarkable_id')));
        }
        if (($request->get('bookmarkable_type') && !$request->get('bookmarkable_id')) && ($request->get('bookmarkable_type') && !$request->get('bookmarkable_ids'))) {
            $criteria[] = new EqualCriterion('bookmarkable_type', $request->get('bookmarkable_type'));
        }

        return new static($criteria);
    }
}
