<?php

namespace Ulams\Video\Events;

use Ulams\Courses\Models\Topic;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class ProcessVideoEvent
{
    use Dispatchable, SerializesModels;

    private ?Authenticatable $user;

    private Topic $topic;

    /**
     * @param Authenticatable|null $user
     * @param Topic $topic
     */
    public function __construct(?Authenticatable $user, Topic $topic)
    {
        $this->user = $user;
        $this->topic = $topic;
    }

    public function getTopic(): Topic
    {
        return $this->topic;
    }

    public function getUser(): ?Authenticatable
    {
        return $this->user;
    }
}
