<?php

namespace Ulams\Auth\Http\Controllers\Admin;

use Ulams\Auth\Dtos\Admin\UserUpdateDto;
use Ulams\Auth\Dtos\Admin\UserUpdateKeysDto;
use Ulams\Auth\Dtos\UserFilterCriteriaDto;
use Ulams\Auth\Dtos\UserSaveDto;
use Ulams\Auth\Dtos\UserUpdateSettingsDto;
use Ulams\Auth\Enums\SettingStatusEnum;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Exceptions\UserNotFoundException;
use Ulams\Auth\Http\Controllers\Admin\Swagger\UserSwagger;
use Ulams\Auth\Http\Requests\Admin\UserAvatarDeleteRequest;
use Ulams\Auth\Http\Requests\Admin\UserAvatarUploadRequest;
use Ulams\Auth\Http\Requests\Admin\UserCreateRequest;
use Ulams\Auth\Http\Requests\Admin\UserDeleteRequest;
use Ulams\Auth\Http\Requests\Admin\UserGetRequest;
use Ulams\Auth\Http\Requests\Admin\UsersListRequest;
use Ulams\Auth\Http\Requests\Admin\UserUpdateRequest;
use Ulams\Auth\Http\Resources\UserFullCollection;
use Ulams\Auth\Http\Resources\UserFullResource;
use Ulams\Auth\Models\User;
use Ulams\Core\Dtos\OrderDto;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

class UserController extends AbstractUserController implements UserSwagger
{
    public function listUsers(UsersListRequest $request): JsonResponse
    {
        $userFilterDto = UserFilterCriteriaDto::instantiateFromRequest($request);

        $paginator = $this->userService->searchAndPaginate(
            $userFilterDto,
            $request->get('fields'),
            $request->get('relations'),
            $request->except('page'),
            $request->get('per_page'),
            $request->get('page'),
            OrderDto::instantiateFromRequest($request),
        );

        return $this->sendResponseForResource(
            (new UserFullCollection($paginator))
                ->columns($request->get('fields') ?? [])
                ->columns($request->get('relations') ?? []),
            __('Users search results')
        );
    }

    public function getUser(UserGetRequest $request): JsonResponse
    {
        try {
            return $this->sendResponseForResource(UserFullResource::make($this->fetchRequestedUser($request)), __('User details'));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage(), $ex instanceof UserNotFoundException ? $ex->getCode() : 400);
        }
    }

    public function createUser(UserCreateRequest $request): JsonResponse
    {
        $userSaveDto = UserSaveDto::instantiateFromRequest($request);
        $userSettingsDto = UserUpdateSettingsDto::instantiateFromRequest($request);
        try {
            /** @var User $user */
            $user = $this->userService->createWithSettings($userSaveDto, $userSettingsDto);
            $this->userService->updateAdditionalFieldsFromRequest($user, $request);
            $this->userGroupService->addMemberToMultipleGroups($request->input('groups', []), $user);
            event(new Registered($user));
            if ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail()) {
                event(new AccountRegistered($user, $request->input(
                    'return_url',
                    Config::get(UlamsAuthServiceProvider::CONFIG_KEY . '.return_url')
                )));
            }
            return $this->sendResponseForResource(UserFullResource::make($user->refresh()), __('Created user'));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage(), 400);
        }
    }

    public function partialUpdateUser(UserUpdateRequest $request): JsonResponse
    {
        $userUpdateDto = UserUpdateDto::instantiateFromRequest($request);
        $userUpdateKeysDto = UserUpdateKeysDto::instantiateFromRequest($request);
        try {
            /** @var int $id */
            $id = $request->route('id');
            $user = $this->userService->patchUsingDto($userUpdateDto, $userUpdateKeysDto, $id);
            $this->userService->updateAdditionalFieldsFromRequest($user, $request);
            return $this->sendResponseForResource(UserFullResource::make($user), __('Updated user'));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage(), 400);
        }
    }

    public function updateUser(UserUpdateRequest $request): JsonResponse
    {
        $userUpdateDto = UserUpdateDto::instantiateFromRequest($request);
        try {
            /** @var int $id */
            $id = $request->route('id');
            $user = $this->userService->putUsingDto($userUpdateDto, $id);
            $this->userService->updateAdditionalFieldsFromRequest($user, $request);
            return $this->sendResponseForResource(UserFullResource::make($user), __('Updated user'));
        } catch (\Exception $ex) {
            return $this->sendError($ex->getMessage(), 400);
        }
    }

    public function deleteUser(UserDeleteRequest $request): JsonResponse
    {
        try {
            /** @var int $id */
            $id = $request->route('id');
            $deleted = $this->userRepository->delete($id);
            if ($deleted) {
                return $this->sendSuccess("User deleted");
            }
            return $this->sendError("User not deleted", 422);
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage(), 400);
        }
    }

    public function uploadAvatar(UserAvatarUploadRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->userService->uploadAvatar(
            $this->fetchRequestedUser($request),
            $request->file('avatar') ?? $request->get('avatar'),
        );
        if (!empty($user->path_avatar)) {
            return $this->sendResponseForResource(UserFullResource::make($user), __('Avatar uploaded'));
        }
        return $this->sendError(__('Avatar not uploaded'), 422);
    }

    public function deleteAvatar(UserAvatarDeleteRequest $request): JsonResponse
    {
        $success = $this->userService->deleteAvatar($this->fetchRequestedUser($request));
        if ($success) {
            return $this->sendSuccess(__('Avatar deleted'));
        }
        return $this->sendError(__('Avatar not deleted'), 422);
    }
}
