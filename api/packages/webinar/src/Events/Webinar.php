<?php

namespace Ulams\Webinar\Events;

use Ulams\Core\Models\User;
use Ulams\Webinar\Models\Webinar as WebinarModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class Webinar
{
    use Dispatchable, SerializesModels;

    private User $user;
    private WebinarModel $webinar;

    public function __construct(User $user, WebinarModel $webinar)
    {
        $this->user = $user;
        $this->webinar = $webinar;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getWebinar(): WebinarModel
    {
        return $this->webinar;
    }
}
