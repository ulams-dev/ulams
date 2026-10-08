<?php

namespace EscolaLms\TopicTypeProject\Events;

use EscolaLms\Core\Models\User;
use EscolaLms\TopicTypeProject\Models\Project;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectSolutionCreatedEvent extends ProjectSolutionEvent
{
}
