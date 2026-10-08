<?php

namespace Ulams\HeadlessH5P\Dtos;

use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Ulams\HeadlessH5P\Enums\H5PPermissionsEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ContentFilterCriteriaDto extends CriteriaDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();
        $user = auth()->user();

        if ($request->has('title')) {
            $criteria->push(new LikeCriterion('parameters->metadata->title', $request->get('title')));
        }
        if ($user->can(H5PPermissionsEnum::H5P_LIST) && $request->has('author_id')) {
            $criteria->push(new EqualCriterion('hh5p_contents.user_id', $request->input('author_id')));
        }
        if (!$user->can(H5PPermissionsEnum::H5P_LIST) && $user->can(H5PPermissionsEnum::H5P_AUTHOR_LIST)) {
            $criteria->push(new EqualCriterion('hh5p_contents.user_id', $user->getKey()));
        }
        if ($request->has('library_id')) {
            $criteria->push(new EqualCriterion('hh5p_contents.library_id', $request->input('library_id')));
        }

        return new self($criteria);
    }
}
