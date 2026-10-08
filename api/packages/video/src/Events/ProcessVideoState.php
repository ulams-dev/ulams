<?php

namespace EscolaLms\Video\Events;

use EscolaLms\Courses\Models\Topic;
use Illuminate\Contracts\Auth\Authenticatable;

class ProcessVideoState extends ProcessVideoEvent
{

    public int $percentage;

    public function __construct(?Authenticatable $user, Topic $topic, int $percentage)
    {
        parent::__construct($user, $topic);
        $this->percentage = $percentage;
    }

    public function getPercentage(): int
    {
        return $this->percentage;
    }
}
