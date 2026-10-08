<?php

namespace EscolaLms\PencilSpaces\Services;

use EscolaLms\PencilSpaces\Common\PencilSpacesRestClient;
use EscolaLms\PencilSpaces\Common\RestClient;
use EscolaLms\PencilSpaces\Enums\PencilSpacesRoleEnum;
use EscolaLms\PencilSpaces\Models\PencilSpaceAccount;
use EscolaLms\PencilSpaces\Models\User;
use EscolaLms\PencilSpaces\Repositories\Contracts\UserRepositoryContract;
use EscolaLms\PencilSpaces\Resource\CreatePencilSpaceResource;
use EscolaLms\PencilSpaces\Services\Contracts\PencilSpacesServiceContract;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

class PencilSpacesService implements PencilSpacesServiceContract
{
    private UserRepositoryContract $userRepository;
    private RestClient $restClient;

    /**
     * @throws Exception
     */
    public function __construct(UserRepositoryContract $userRepository, PencilSpacesRestClient $restClient)
    {
        $this->userRepository = $userRepository;
        $this->restClient = $restClient;
    }

    /**
     * @throws RequestException
     */
    public function getDirectLoginUrl(int $userId, string $redirectUrl = null): string
    {
        $pencilSpaceAccount = $this->getPencilSpaceAccount($userId);

        return $this->restClient
            ->get("users/{$pencilSpaceAccount->pencil_space_id}/authorize", ['redirectUrl' => $redirectUrl])
            ->json('url');
    }

    /**
     * @throws RequestException
     */
    public function createSpace(CreatePencilSpaceResource $createSpaceResource): array
    {
        return $this->restClient
            ->post('spaces/create', array_merge($createSpaceResource->toArray(), [
                    'hosts' => $this->mapToPencilSpacesUsers($createSpaceResource->getHostIds(), PencilSpacesRoleEnum::TEACHER),
                    'participants' => $this->mapToPencilSpacesUsers($createSpaceResource->getParticipantIds()),
                ])
            )
            ->json();
    }

    private function mapToPencilSpacesUsers(Collection $ids, string $userRole = PencilSpacesRoleEnum::STUDENT): array
    {
        return $ids->map(function (int $id) use ($userRole) {
            $pencilSpaceAccount = $this->getPencilSpaceAccount($id, $userRole);

            return [
                'userId' => $pencilSpaceAccount->pencil_space_id,
                'email' => $pencilSpaceAccount->pencil_space_email,
            ];
        })
            ->toArray();
    }

    /**
     * @throws RequestException
     */
    private function getPencilSpaceAccount(int $userId, string $userRole = PencilSpacesRoleEnum::STUDENT): PencilSpaceAccount
    {
        $user = $this->userRepository->findById($userId);

        if (!$user->pencilSpaceAccount) {
            $user = $this->createPencilSpaceAccount($user, $userRole);
        }

        return $user->pencilSpaceAccount;
    }

    /**
     * @throws RequestException
     */
    private function createPencilSpaceAccount(User $user, string $userRole): User
    {
        $result = $this->restClient
            ->post('users/createAPIUser', [
                'name' => $user->name,
                'userRole' => $userRole,
            ])
            ->json();

        $user->pencilSpaceAccount()->updateOrCreate([], [
            'pencil_space_id' => $result['userId'],
            'pencil_space_email' => $result['email']
        ]);

        return $user->refresh();
    }
}
