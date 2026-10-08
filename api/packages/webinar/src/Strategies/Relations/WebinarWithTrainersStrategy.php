<?php

namespace Ulams\Webinar\Strategies\Relations;

use Ulams\Core\Models\User;
use Ulams\Webinar\Events\WebinarTrainerAssigned;
use Ulams\Webinar\Events\WebinarTrainerUnassigned;
use Ulams\Webinar\Strategies\Contracts\RelationStrategyContract;
use Ulams\Webinar\Models\Webinar;

class WebinarWithTrainersStrategy implements RelationStrategyContract
{
    private Webinar $webinar;
    private array $data;

    public function __construct(array $params) {
        $this->webinar = $params[0];
        $this->data = $params[1] ?? [];
    }

    public function setRelation(): void
    {
        $changes = $this->webinar->trainers()->sync($this->data['trainers']);
        $this->dispatchEventForTrainersAttachedToWebinar($changes['attached']);
        $this->dispatchEventForTrainersDetachedFromWebinar($changes['detached']);
    }

    private function dispatchEventForTrainersAttachedToWebinar(array $users = []): void
    {
        foreach ($users as $attached) {
            $user = is_int($attached) ? User::find($attached) : $attached;
            if ($user) {
                event(new WebinarTrainerAssigned($user, $this->webinar));
            }
        }
    }

    private function dispatchEventForTrainersDetachedFromWebinar(array $users = []): void
    {
        foreach ($users as $detached) {
            $user = is_int($detached) ? User::find($detached) : $detached;
            if ($user) {
                event(new WebinarTrainerUnassigned($user, $this->webinar));
            }
        }
    }
}
