<?php

namespace Ulams\StationaryEvents\Events;

use Ulams\Auth\Models\User;
use Ulams\StationaryEvents\Models\StationaryEvent as StationaryEventModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class StationaryEvent
{
    use Dispatchable, SerializesModels;

    private User $user;
    private StationaryEventModel $stationaryEvent;

    public function __construct(User $user, StationaryEventModel $stationaryEvent)
    {
        $this->user = $user;
        $this->stationaryEvent = $stationaryEvent;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStationaryEvent(): StationaryEventModel
    {
        return $this->stationaryEvent;
    }
}
