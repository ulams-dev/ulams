<?php

namespace Ulams\Auth\Http\Controllers;

use Ulams\Auth\Dtos\UserUpdateAuthDataDto;
use Ulams\Auth\Dtos\UserUpdateDto;
use Ulams\Auth\Http\Controllers\Swagger\ProfileSwagger;
use Ulams\Auth\Http\Requests\InitProfileDeletionRequest;
use Ulams\Auth\Http\Requests\MyProfileRequest;
use Ulams\Auth\Http\Requests\ProfileDeleteRequest;
use Ulams\Auth\Http\Requests\ProfileUpdateAuthDataRequest;
use Ulams\Auth\Http\Requests\ProfileUpdatePasswordRequest;
use Ulams\Auth\Http\Requests\ProfileUpdateRequest;
use Ulams\Auth\Http\Requests\UpdateInterests;
use Ulams\Auth\Http\Requests\UploadAvatarRequest;
use Ulams\Auth\Http\Requests\UserSettingsUpdateRequest;
use Ulams\Auth\Http\Resources\UserFullResource;
use Ulams\Auth\Http\Resources\UserSettingCollection;
use Ulams\Auth\Repositories\Contracts\UserRepositoryContract;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileAPIController extends UlamsBaseController implements ProfileSwagger
{
    private UserRepositoryContract $userRepository;
    private UserServiceContract $userService;

    public function __construct(UserRepositoryContract $userRepository, UserServiceContract $userService)
    {
        $this->userRepository = $userRepository;
        $this->userService = $userService;
    }

    public function me(MyProfileRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(UserFullResource::make($this->userRepository->findByIdWithRelations($request->user()->getKey(), ['interests', 'interests.parent'])), 'My profile');
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $userUpdateDto = UserUpdateDto::instantiateFromRequest($request);

        /** @var User $user */
        $user = $this->userRepository->update(
            $userUpdateDto->toArray(),
            $request->user()->getKey(),
        );
        $this->userService->updateAdditionalFieldsFromRequest($user, $request);

        if (!is_null($user)) {
            return $this->sendResponseForResource(UserFullResource::make($user->refresh()), __('Updated profile'));
        }

        return $this->sendError(__('Profile not updated'), 422);
    }

    public function updateAuthData(ProfileUpdateAuthDataRequest $request): JsonResponse
    {
        $userUpdateDto = UserUpdateAuthDataDto::instantiateFromRequest($request);

        $user = $this->userRepository->update(
            $userUpdateDto->toArray(),
            $request->user()->getKey(),
        );

        if (!is_null($user)) {
            return $this->sendResponseForResource(UserFullResource::make($user), __('Updated email'));
        }

        return $this->sendError(__('Email not updated'), 422);
    }

    public function updatePassword(ProfileUpdatePasswordRequest $request): JsonResponse
    {
        $success = $this->userRepository->updatePassword(
            $request->user(),
            $request->input('new_password'),
        );

        if ($success) {
            return $this->sendSuccess(__('Password updated'));
        }
        return $this->sendError(__('Password not updated', 422));
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->userService->uploadAvatar(
            $request->user(),
            $request->file('avatar'),
        );

        if (!is_null($user)) {
            return $this->sendResponseForResource(UserFullResource::make($user), __('Avatar uploaded'));
        }

        return $this->sendError(__('Avatar not uploaded'), 422);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $success = $this->userService->deleteAvatar($request->user());

        if ($success) {
            return $this->sendSuccess(__('Avatar deleted'));
        }

        return $this->sendError(__('Avatar not deleted'), 422);
    }

    public function interests(UpdateInterests $request): JsonResponse
    {
        $this->userRepository->updateInterests(
            $request->user(),
            $request->input('interests'),
        );

        return $this->sendResponseForResource(UserFullResource::make($request->user()->refresh()), __('Updated user interests'));
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->sendResponseForResource(UserSettingCollection::make($user->settings), __('User settings'));
    }

    public function settingsUpdate(UserSettingsUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->userRepository->updateSettings($user, $request->getSettingsWithoutAdditionalFields());
        return $this->sendResponseForResource(UserSettingCollection::make($user->settings), __('User interests'));
    }

    public function delete(ProfileDeleteRequest $request): JsonResponse
    {
        $deleted = $this->userRepository->delete($request->user()->getKey());
        $request->user()->tokens()->get()->each(fn ($token) => $token->revoke());

        if ($deleted) {
            return $this->sendSuccess(__('User deleted'));
        }

        return $this->sendError(__('User not deleted'), 422);
    }

    public function initProfileDeletion(InitProfileDeletionRequest $request): JsonResponse
    {
        $this->userService->initProfileDeletion($request->user(), $request->getReturnUrl());

        return $this->sendSuccess(__('User deletion request created'));
    }

    public function confirmDeletionProfile(Request $request, int $userId, string $token): JsonResponse
    {
        $this->userService->confirmDeletionProfile($userId, $token);

        return $this->sendSuccess(__('User deleted'));
    }
}
