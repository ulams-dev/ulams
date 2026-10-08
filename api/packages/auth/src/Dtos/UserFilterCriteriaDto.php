<?php

namespace Ulams\Auth\Dtos;

use Ulams\Auth\Models\User as AuthUser;
use Ulams\Auth\Repositories\Criteria\AdditionalField\AdditionalFieldBooleanCriterion;
use Ulams\Auth\Repositories\Criteria\AdditionalField\AdditionalFieldEqualsCriterion;
use Ulams\Auth\Repositories\Criteria\AdditionalField\AdditionalFieldLikeCriterion;
use Ulams\Auth\Repositories\Criteria\LastLoginToFrontCriterion;
use Ulams\Auth\Repositories\Criteria\LastLoginCriterion;
use Ulams\Core\Repositories\Criteria\PeriodCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\DoesntHasCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\HasCriterion;
use Ulams\Core\Repositories\Criteria\RoleCriterion;
use Ulams\Core\Repositories\Criteria\UserSearchCriterion;
use Carbon\Carbon;
use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\ModelFields\Enum\MetaFieldTypeEnum;
use Ulams\ModelFields\Facades\ModelFields;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Ulams\Core\Dtos\CriteriaDto;
use Illuminate\Support\Facades\Schema;

class UserFilterCriteriaDto extends CriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('search')) {
            $criteria->push(new UserSearchCriterion($request->get('search')));
        }

        if (!is_null($request->get('role'))) {
            $criteria->push(new RoleCriterion($request->get('role')));
        }

        if (!is_null($request->get('status'))) {
            $criteria->push(new EqualCriterion('is_active', $request->get('status')));
        }

        if (!is_null($request->get('onboarding'))) {
            $criteria->push(
                $request->get('onboarding') ? new HasCriterion('interests') : new DoesntHasCriterion('interests')
            );
        }

        if ($request->get('from') || $request->get('to')) {
            $criteria->push(new PeriodCriterion(new Carbon($request->get('from') ?? 0), new Carbon($request->get('to') ?? null)));
        }

        if (Schema::hasTable('notifications')) {
            if ($request->get('gt_last_login_day')) {
                $criteria->push(new LastLoginCriterion($request->get('gt_last_login_day'), '>='));
            }

            if ($request->get('lt_last_login_day')) {
                $criteria->push(new LastLoginCriterion($request->get('lt_last_login_day'), '<='));
            }
        }

        $additionalFields = ModelFields::getFieldsMetadata(AuthUser::class)->mapWithKeys(fn ($item, $key) => [$item['name'] => $item['type']]);
        $additionalSearch = $request->collect()->only($additionalFields->keys());

        $additionalSearch->each(function ($value, $key) use ($criteria, $additionalFields) {
            switch ($additionalFields->get($key)) {
                case MetaFieldTypeEnum::BOOLEAN:
                    $criteria->push(new AdditionalFieldBooleanCriterion($key, (bool)$value));
                    break;
                case MetaFieldTypeEnum::NUMBER:
                    $criteria->push(new AdditionalFieldEqualsCriterion($key, $value));
                    break;
                default:
                    $criteria->push(new AdditionalFieldLikeCriterion($key, $value));
            }
        });

        return new self($criteria);
    }
}
