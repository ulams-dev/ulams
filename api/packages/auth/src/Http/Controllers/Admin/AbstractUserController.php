<?php

namespace Ulams\Auth\Http\Controllers\Admin;

use Ulams\Auth\Exceptions\UserNotFoundException;
use Ulams\Auth\Http\Requests\Admin\AbstractUserIdInRouteRequest;
use Ulams\Auth\Models\User;
use Ulams\Auth\Repositories\Contracts\UserRepositoryContract;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;

class AbstractUserController extends UlamsBaseController
{
    protected UserRepositoryContract $userRepository;
    protected UserServiceContract $userService;
    protected UserGroupServiceContract $userGroupService;

    public function __construct(UserRepositoryContract $userRepository, UserServiceContract $userService, UserGroupServiceContract $userGroupService)
    {
        $this->userRepository = $userRepository;
        $this->userService = $userService;
        $this->userGroupService = $userGroupService;
    }

    protected function fetchRequestedUser(AbstractUserIdInRouteRequest $request): User
    {
        /** @var int $id */
        $id = $request->route('id');
        /** @var User|null $user */
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new UserNotFoundException();
        }
        return $user;
    }
}
