<?php

namespace EscolaLms\TopicTypeProject\Events;

use EscolaLms\Core\Models\User;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class ProjectSolutionEvent
{
    use Dispatchable, SerializesModels;

    // Public so SerializesModels can restore them when the event is queued
    // (private typed properties on this base class stay uninitialized after deserialization).
    public User $user;

    public ProjectSolution $projectSolution;

    public function __construct(User $user, ProjectSolution $projectSolution)
    {
        $this->user = $user;
        $this->projectSolution = $projectSolution;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getProjectSolution(): ProjectSolution
    {
        return $this->projectSolution;
    }
}
