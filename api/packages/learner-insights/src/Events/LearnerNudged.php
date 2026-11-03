<?php

namespace Ulams\LearnerInsights\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ulams\Core\Models\User;

/**
 * The one event of this package that carries a user, on purpose: the notifications package stores
 * it as an in-app notification and the e-mail template (templates-email) sends it. The payload is
 * scalars only: the course, one plain-language reason and the link to continue.
 */
final class LearnerNudged
{
    use Dispatchable;
    use SerializesModels;

    /** @param array{courseId:int,courseTitle:string,reasonText:string,continueUrl:string,nudgeId:string} $data */
    public function __construct(private readonly User $user, private readonly array $data)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['user' => $this->user] + $this->data;
    }
}
