<?php

namespace Ulams\Templates\Tests\Mock;

use Illuminate\Database\Eloquent\Model;
use Ulams\Core\Models\User;

/**
 * An event that carries a model with a language, the way a course event carries a course.
 */
class TestEventWithLanguage
{
    private User $user;
    private User $friend;
    private ?Model $subject;

    public function __construct(User $user, User $friend, ?string $language = null)
    {
        $this->user = $user;
        $this->friend = $friend;
        $this->subject = $language === null ? null : (new class () extends Model {
        })->forceFill(['language' => $language]);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getFriend(): User
    {
        return $this->friend;
    }
}
