<?php

namespace Ulams\TopicTypeProject\Events;

use Ulams\Core\Models\User;
use Ulams\TopicTypeProject\Models\Project;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectSolutionCreatedEvent extends ProjectSolutionEvent
{
}
