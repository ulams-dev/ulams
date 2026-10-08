<?php

namespace Ulams\Auth\Http\Controllers\Admin;

use Ulams\Auth\Dtos\UserUpdateSettingsDto;
use Ulams\Auth\Http\Controllers\Admin\Swagger\UserSettingsSwagger;
use Ulams\Auth\Http\Requests\Admin\UserSettingsListRequest;
use Ulams\Auth\Http\Requests\Admin\UserSettingsUpdateRequest;
use Ulams\Auth\Http\Resources\UserSettingCollection;
use Ulams\Auth\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingsController extends AbstractUserController implements UserSettingsSwagger
{
    public function listUserSettings(UserSettingsListRequest $request): JsonResponse
    {
        $user = $this->fetchRequestedUser($request);
        return $this->generateUserSettingsCollectionResponse($request, $user);
    }

    public function patchUserSettings(UserSettingsUpdateRequest $request): JsonResponse
    {
        $user = $this->fetchRequestedUser($request);
        $dto = UserUpdateSettingsDto::instantiateFromRequest($request);
        try {
            $this->userRepository->patchSettingsUsingDto($user, $dto);
            return $this->generateUserSettingsCollectionResponse($request, $user);
        } catch (\Exception $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], 400);
        }
    }

    public function putUserSettings(UserSettingsUpdateRequest $request): JsonResponse
    {
        $user = $this->fetchRequestedUser($request);
        $dto = UserUpdateSettingsDto::instantiateFromRequest($request);
        try {
            $this->userRepository->putSettingsUsingDto($user, $dto);
            return $this->generateUserSettingsCollectionResponse($request, $user);
        } catch (\Exception $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], 400);
        }
    }

    private function generateUserSettingsCollectionResponse(Request $request, User $user): JsonResponse
    {
        $user = $user->refresh();
        return $this->sendResponseForResource(UserSettingCollection::make($user->settings), __('User settings'));
    }
}
