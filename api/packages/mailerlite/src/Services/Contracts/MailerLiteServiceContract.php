<?php

namespace Ulams\MailerLite\Services\Contracts;

use Ulams\Core\Models\User;

interface MailerLiteServiceContract
{
    public function getOrCreateGroup(string $name);
    public function addSubscriberToGroup(string $groupName, User $user): bool;
    public function removeSubscriberFromGroup(string $groupName, User $user): bool;
    public function deleteSubscriber(User $user): bool;
}
