<?php

namespace EscolaLms\Mattermost\Services;

use EscolaLms\Core\Models\User;
use EscolaLms\Mattermost\Enum\MattermostRoleEnum;
use EscolaLms\Mattermost\Enum\TeamNameEnum;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use Gnello\Mattermost\Driver;
use Gnello\Mattermost\Laravel\Facades\Mattermost;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

class MattermostService implements MattermostServiceContract
{
    public Driver $driver;

    public function __construct()
    {
        $this->driver = Mattermost::server('default');
    }

    private function getUsername(User $user): string
    {
        return Str::slug($user->email);
    }

    private function getData(ResponseInterface $result): mixed
    {
        return json_decode($result->getBody());
    }

    // @phpstan-ignore-next-line
    private function logResponse(ResponseInterface $result): void
    {
        if ($result->getStatusCode() < 400) {
            echo 'Everything is ok.';
            dd(json_decode($result->getBody()));
        } else {
            echo 'HTTP ERROR ' . $result->getStatusCode();
            dd(json_decode($result->getBody()));
        }
    }

    public function addUser(User $user): bool
    {
        $result = $this->getOrCreateUser($user);

        return $result->getStatusCode() < 400;
    }

    public function addUserToTeam(User $user, string $teamDisplayName = TeamNameEnum::COURSES): bool
    {
        $team = $this->getData($this->getOrCreateTeam($teamDisplayName));
        $user = $this->getData($this->getOrCreateUser($user));

        // @phpstan-ignore-next-line
        if (isset($team->id) && isset($user->id)) {
            $teams = $this->driver->getTeamModel();
            $result = $teams->addUser($team->id, [
                'user_id' => $user->id,
                'team_id' => $team->id,
            ]);

            return $result->getStatusCode() < 400;
        }

        return false;
    }

    public function addUserToChannel(User $user, string $channelDisplayName, string $teamDisplayName = TeamNameEnum::COURSES,
                                     string $channelRole = MattermostRoleEnum::MEMBER): bool
    {
        $channel = $this->getData($this->getOrCreateChannel($teamDisplayName, $channelDisplayName));
        $mmUser = $this->getData($this->getOrCreateUser($user));

        // @phpstan-ignore-next-line
        if (isset($channel->id) && isset($mmUser->id)) {
            $this->addUserToTeam($user, $teamDisplayName);
            $channels = $this->driver->getChannelModel();
            $result = $channels->addUser($channel->id, [
                'user_id' => $mmUser->id,
            ]);

            if ($result->getStatusCode() < 400) {
                $result = $channels->updateChannelRoles($channel->id, $mmUser->id, ['roles' => $channelRole]);
            }

            return $result->getStatusCode() < 400;
        }

        return false;
    }

    public function getOrCreateTeam(string $displayName): ResponseInterface
    {
        $name = Str::slug($displayName);

        $teams = $this->driver->getTeamModel();
        $result = $teams->getTeamByName($name);

        if ($result->getStatusCode() < 400) {
            return $result;
        }

        // team does not exists create

        $result = $teams->createTeam([
            'name' => $name,
            'display_name' => $name,
            'type' => 'I', // 'O' for open, 'I' for invite only
        ]);

        return $result;
    }

    public function getOrCreateChannel(string $teamDisplayName, string $channelDisplayName): ResponseInterface
    {
        $team = $this->getData($this->getOrCreateTeam($teamDisplayName));

        $channelName = Str::slug($channelDisplayName);

        // @phpstan-ignore-next-line
        if (isset($team->id)) {
            $channels = $this->driver->getChannelModel();
            $result = $channels->getChannelByName($team->id, $channelName);

            if ($result->getStatusCode() < 400) {
                return $result;
            }

            $result = $channels->createChannel([
                'team_id' => $team->id,
                'name' => $channelName,
                'display_name' => $channelDisplayName,
                'type' => 'P', // 'O' for a public channel, 'P' for a private channel
            ]);

            return $result;
        }

        // @phpstan-ignore-next-line
        return $team;
    }

    public function getOrCreateUser(User $user): ResponseInterface
    {
        //Retrieve the User Model
        $userModel = $this->driver->getUserModel();

        $result = $userModel->getUserByEmail($user->email);

        if ($result->getStatusCode() < 400) {
            return $result;
        }

        $result = $userModel->createUser([
            'email' => $user->email,
            'username' => $this->getUsername($user),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'auth_service' => 'email',
            'password' => Str::random(16),
        ]);

        return $result;
    }

    public function sendMessage(string $markdown, string $channelDisplayName, string $teamDisplayName = TeamNameEnum::COURSES): bool
    {
        $channels = $this->driver->getChannelModel();

        $channel = $channels->getChannelByNameAndTeamName(Str::slug($teamDisplayName), Str::slug($channelDisplayName));

        $channelData = $this->getData($channel);

        // @phpstan-ignore-next-line
        if ($channelData->id) {
            $result = $this->driver->getPostModel()->createPost([
                // @phpstan-ignore-next-line
                'channel_id' => $channelData->id,
                'message' => $markdown,
            ]);

            return $result->getStatusCode() < 400;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function generateUserCredentials(User $user): array
    {
        $mmUser = json_decode($this->getOrCreateUser($user)->getBody());

        $users = $this->driver->getUserModel();

        $newPassword = Str::random() . rand(0, 9) . '!';

        // @phpstan-ignore-next-line
        $result = $users->updateUserPassword($mmUser->id, [
            'new_password' => $newPassword,
        ]);

        $results = json_decode($result->getBody());

        return [
            'status' => $results,
            'user' => $mmUser,
            'password' => $newPassword,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserData(User $user): array
    {
        $server = config('mattermost.servers.default.host');

        $users = $this->driver->getUserModel();

        $result = $users->getUserByEmail($user->email);

        if ($result->getStatusCode() >= 400) {
            return [];
        }

        $userData = json_decode($result->getBody());

        $teams = $this->driver->getTeamModel();

        // @phpstan-ignore-next-line
        $result = $teams->getUserTeams($userData->id);

        $userTeamsData = json_decode($result->getBody());

        $channels = $this->driver->getChannelModel();

        // @phpstan-ignore-next-line
        foreach ($userTeamsData as $userTeamData) {
            // @phpstan-ignore-next-line
            $result = $channels->getChannelsForUser($userData->id, $userTeamData->id);
            $channelsData = json_decode($result->getBody());
            // @phpstan-ignore-next-line
            foreach ($channelsData as $channelData) {
                // @phpstan-ignore-next-line
                $channelData->url = 'https://' . $server . '/' . $userTeamData->name . '/' . $channelData->name;
            }
            // @phpstan-ignore-next-line
            $userTeamData->channels = $channelsData;
        }

        return [
            'server' => $server,
            'teams' => $userTeamsData,
        ];
    }

    public function sendUserResetPassword(User $user): bool
    {
        $this->getOrCreateUser($user);

        $users = $this->driver->getUserModel();

        $users->sendPasswordResetEmail(['email' => $user->email]);

        return true;
    }

    public function blockUser(User $user): bool
    {
        $userModel = $this->driver->getUserModel();
        $result = $userModel->getUserByEmail($user->email);

        if ($result->getStatusCode() === 200) {
            $user = $this->getData($result);
            // @phpstan-ignore-next-line
            $result = $userModel->updateUserActive($user->id, ['active' => false]);
        }

        return $result->getStatusCode() === 200;
    }

    public function deleteUser(User $user): bool
    {
        $userModel = $this->driver->getUserModel();
        $result = $userModel->getUserByEmail($user->email);

        if ($result->getStatusCode() === 200) {
            $user = $this->getData($result);
            // @phpstan-ignore-next-line
            $result = $userModel->deactivateUserAccount($user->id);
        }

        return $result->getStatusCode() === 200;
    }

    public function removeUserFromChannel(User $user, string $channelDisplayName, string $teamDisplayName = TeamNameEnum::COURSES): bool
    {
        $channelModel = $this->driver->getChannelModel();
        $mmUser = $this->getData($this->driver->getUserModel()->getUserByEmail($user->email));
        $channel = $this->getData(
            $channelModel->getChannelByNameAndTeamName(Str::slug($teamDisplayName), Str::slug($channelDisplayName))
        );

        // @phpstan-ignore-next-line
        if (isset($mmUser->id) && isset($channel->id)) {
            $response = $channelModel->removeUserFromChannel($channel->id, $mmUser->id);

            return $response->getStatusCode() === 200;
        }

        return false;
    }
}
