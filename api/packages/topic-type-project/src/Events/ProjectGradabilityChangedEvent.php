<?php

namespace EscolaLms\TopicTypeProject\Events;

use EscolaLms\TopicTypeProject\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted whenever a project's counts_to_grade flag flips, in either direction.
 * Consumed (in pcg-grades) to regenerate or delete the journal partial grade(s)
 * derived from this project.
 */
class ProjectGradabilityChangedEvent
{
    use Dispatchable, SerializesModels;

    public Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function countsToGrade(): bool
    {
        return (bool) $this->project->counts_to_grade;
    }
}
