<?php

namespace Ulams\LivingCourse\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ulams\Core\Models\User;

/**
 * Base of the Living Course notifications. Every `Ulams\...` event that carries a user is stored as
 * an in-app notification; the e-mail templates are registered by the templates-email package. The
 * payload is plain scalars, so it serialises into the notification and never carries source text.
 * The events are also the payloads of the outgoing webhooks of Phase 7.3.
 */
abstract class LivingCourseEvent
{
    use Dispatchable;
    use SerializesModels;

    /** @param array<string,scalar|null> $data */
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
