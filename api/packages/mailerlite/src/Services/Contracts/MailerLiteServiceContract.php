<?php

namespace EscolaLms\MailerLite\Services\Contracts;

use EscolaLms\Core\Models\User;

interface MailerLiteServiceContract
{
    public function getOrCreateGroup(string $name);
    public function addSubscriberToGroup(string $groupName, User $user): bool;
    public function removeSubscriberFromGroup(string $groupName, User $user): bool;
    public function deleteSubscriber(User $user): bool;
}
