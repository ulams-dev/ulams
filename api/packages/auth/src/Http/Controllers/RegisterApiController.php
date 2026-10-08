<?php

namespace Ulams\Auth\Http\Controllers;

use Ulams\Auth\Dtos\UserSaveDto;
use Ulams\Auth\Dtos\UserUpdateSettingsDto;
use Ulams\Auth\Enums\AuthPermissionsEnum;
use Ulams\Auth\Enums\SettingStatusEnum;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Auth\Events\AccountMustBeEnableByAdmin;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Http\Controllers\Swagger\RegisterSwagger;
use Ulams\Auth\Http\Requests\RegisterRequest;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

class RegisterApiController extends UlamsBaseController implements RegisterSwagger
{
    private UserServiceContract $userService;
    private UserGroupServiceContract $userGroupService;

    public function __construct(UserServiceContract $userService, UserGroupServiceContract $userGroupService)
    {
        $this->userService = $userService;
        $this->userGroupService = $userGroupService;
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $mustBeEnabledByAdmin = Config::get(
            UlamsAuthServiceProvider::CONFIG_KEY . '.account_must_be_enabled_by_admin', SettingStatusEnum::DISABLED
        );

        $autoVerifiedEmail = Config::get(
            UlamsAuthServiceProvider::CONFIG_KEY . '.auto_verified_email', SettingStatusEnum::DISABLED
        );

        $userSaveDto = UserSaveDto::instantiateFromRequest($request)->setRoles([UserRole::STUDENT]);
        $userSaveDto->setIsActive($mustBeEnabledByAdmin === SettingStatusEnum::DISABLED);
        $userSettingsDto = UserUpdateSettingsDto::instantiateFromRequest($request);
        $user = $this->userService->createWithSettings($userSaveDto, $userSettingsDto);
        $this->userService->updateAdditionalFieldsFromRequest($user, $request);
        $this->userGroupService->registerMemberToMultipleGroups($request->input('groups', []), $user);

        if ($mustBeEnabledByAdmin === SettingStatusEnum::ENABLED) {
            User::permission(AuthPermissionsEnum::USER_VERIFY_ACCOUNT)->get()->each(function ($admin) use ($user) {
                event(new AccountMustBeEnableByAdmin($admin, $user));
            });

            return $this->sendSuccess(__('Registered, account must be enabled by admin'));
        } else {
            if ($autoVerifiedEmail === SettingStatusEnum::ENABLED) {
                $user->markEmailAsVerified();
                event(new AccountConfirmed($user));
            } else {
                event(new AccountRegistered($user, $request->input('return_url')));
            }
        }

        return $this->sendSuccess(__('Registered'));
    }
}
