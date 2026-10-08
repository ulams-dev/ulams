<?php

namespace EscolaLms\TopicTypeGift\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Carbon;

class QuizAttemptPolicy
{
    use HandlesAuthorization;

    public function createOwn(User $user): bool
    {
        return $user->can(TopicTypeGiftPermissionEnum::CREATE_OWN_QUIZ_ATTEMPT);
    }

    public function readOwn(User $user, QuizAttempt $attempt): bool
    {
        return $user->can(TopicTypeGiftPermissionEnum::READ_OWN_QUIZ_ATTEMPT)
            && $attempt->user_id === $user->getKey();
    }

    public function listOwn(User $user): bool
    {
        return $user->can(TopicTypeGiftPermissionEnum::LIST_OWN_QUIZ_ATTEMPT);
    }

    public function addAnswerOwn(User $user, QuizAttempt $attempt): bool
    {
        return $this->readOwn($user, $attempt)
            && ($attempt->end_at === null || $attempt->end_at > Carbon::now());
    }

    public function read(User $user, QuizAttempt $attempt): bool
    {
        return $user->can(TopicTypeGiftPermissionEnum::READ_QUIZ_ATTEMPT);
    }

    public function update(User $user, QuizAttempt $attempt): bool
    {
        return $user->can(TopicTypeGiftPermissionEnum::UPDATE_QUIZ_ATTEMPT);
    }

    public function list(User $user): bool
    {
        return $user->canAny([
            TopicTypeGiftPermissionEnum::LIST_QUIZ_ATTEMPT,
            TopicTypeGiftPermissionEnum::LIST_SELF_QUIZ_ATTEMPT,
        ]);
    }
}
