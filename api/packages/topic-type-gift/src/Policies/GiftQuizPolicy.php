<?php

namespace Ulams\TopicTypeGift\Policies;

use Ulams\Auth\Models\User;
use Ulams\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Carbon;

class GiftQuizPolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(TopicTypeGiftPermissionEnum::READ_GIFT_QUIZ);
    }

    public function read(User $user, GiftQuiz $giftQuiz): bool
    {
        return $user->can(TopicTypeGiftPermissionEnum::READ_GIFT_QUIZ);
    }

    public function update(User $user, GiftQuiz $giftQuiz): bool
    {
        return $user->can(TopicTypeGiftPermissionEnum::UPDATE_GIFT_QUIZ);
    }
}
