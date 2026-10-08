<?php

namespace Ulams\Webinar\Events;

use Ulams\Core\Models\User;
use Ulams\Webinar\Models\Webinar as WebinarModel;

class ReminderAboutTerm extends Webinar
{
    private string $status;

    public function __construct(User $user, WebinarModel $webinar, string $status)
    {
        parent::__construct($user, $webinar);
        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }
}
