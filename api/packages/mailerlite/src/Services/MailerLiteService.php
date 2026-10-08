<?php

namespace EscolaLms\MailerLite\Services;

use EscolaLms\Core\Models\User;
use EscolaLms\MailerLite\Providers\SettingsServiceProvider;
use EscolaLms\MailerLite\Services\Contracts\MailerLiteServiceContract;
use Http\Adapter\Guzzle7\Client;
use Illuminate\Support\Facades\Config;
use MailerLiteApi\Api\Subscribers;
use MailerLiteApi\MailerLite;

class MailerLiteService implements MailerLiteServiceContract
{
    private MailerLite $mailerLiteClient;

    public function __construct(Client $guzzleClient)
    {
        $this->mailerLiteClient = new MailerLite(Config::get(SettingsServiceProvider::CONFIG_KEY . '.api_key'), $guzzleClient);
    }

    public function getOrCreateGroup(string $name)
    {
        $groupModel = $this->mailerLiteClient->groups();
        $groups = $groupModel->where(['name' => $name])->get();

        if ($groups->count() > 0) {
            return $groups->first();
        }

        return $groupModel->create(['name' => $name]);
    }

    public function addSubscriberToGroup(string $groupName, User $user): bool
    {
        $group = $this->getOrCreateGroup($groupName);

        if (isset($group->id)) {
            $response =  $this->mailerLiteClient->groups()->addSubscriber($group->id, [
                'email' => $user->email,
                'name' => $user->name,
            ]);

            return isset($response->id);
        }

        return false;
    }

    public function removeSubscriberFromGroup(string $groupName, User $user): bool
    {
        $group = $this->getOrCreateGroup($groupName);

        if (isset($group->id)) {
            $subscriberModel = $this->mailerLiteClient->subscribers();
            $subscriber = $subscriberModel->find($user->email);

            if (isset($subscriber->id)) {
                $this->mailerLiteClient->groups()->removeSubscriber($group->id, $subscriber->id);

                return true;
            }
        }

        return false;
    }

    public function deleteSubscriber(User $user): bool
    {
        $subscriberModel = $this->mailerLiteClient->subscribers();
        $subscriber = $subscriberModel->find($user->email);

        if (isset($subscriber->id)) {
            $subscriberModel->delete($subscriber->id);

            return true;
        }

        return false;
    }
}
