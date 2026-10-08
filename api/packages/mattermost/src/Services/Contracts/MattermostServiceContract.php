<?php

namespace EscolaLms\Mattermost\Services\Contracts;

use EscolaLms\Core\Models\User;
use EscolaLms\Mattermost\Enum\MattermostRoleEnum;
use Psr\Http\Message\ResponseInterface;

interface MattermostServiceContract
{
    public function addUser(User $user): bool;

    public function addUserToTeam(User $user, string $teamDisplayName = "Courses"): bool;

    public function addUserToChannel(User $user, string $channelDisplayName, string $teamDisplayName = "Courses", string $channelRole = MattermostRoleEnum::MEMBER): bool;

    public function getOrCreateTeam(string $displayName): ResponseInterface;

    public function getOrCreateChannel(string $teamDisplayName, string $channelDisplayName): ResponseInterface;

    public function getOrCreateUser(User $user): ResponseInterface;

    public function sendMessage(string $markdown, string $channelDisplayName, string $teamDisplayName = "Courses"): bool;

    /**
     * @return array<string, mixed>
     */
    public function getUserData(User $user): array;

    /**
     * @return array<string, mixed>
     */
    public function generateUserCredentials(User $user): array;

    public function sendUserResetPassword(User $user): bool;

    public function blockUser(User $user): bool;

    public function deleteUser(User $user): bool;

    public function removeUserFromChannel(User $user, string $channelDisplayName, string $teamDisplayName = "Courses"): bool;
}
