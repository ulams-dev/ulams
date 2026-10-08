<?php

namespace EscolaLms\TopicTypeProject\Services;

use EscolaLms\Core\Models\User;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\TopicTypeProject\Dtos\CreateProjectSolutionDto;
use EscolaLms\TopicTypeProject\Dtos\CriteriaDto;
use EscolaLms\TopicTypeProject\Dtos\GradeProjectSolutionDto;
use EscolaLms\TopicTypeProject\Dtos\PageDto;
use EscolaLms\TopicTypeProject\Events\ProjectSolutionCreatedEvent;
use EscolaLms\TopicTypeProject\Events\ProjectSolutionGradedEvent;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Repositories\Contracts\ProjectSolutionRepositoryContract;
use EscolaLms\TopicTypeProject\Services\Contracts\ProjectSolutionServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class ProjectSolutionService implements ProjectSolutionServiceContract
{
    public const DIR = 'project-solutions';

    private ProjectSolutionRepositoryContract $projectSolutionRepository;

    public function __construct(ProjectSolutionRepositoryContract $projectSolutionRepository)
    {
        $this->projectSolutionRepository = $projectSolutionRepository;
    }

    public function findAll(CriteriaDto $criteriaDto, PageDto $pageDto): LengthAwarePaginator
    {
        return $this->projectSolutionRepository->findByCriteria($criteriaDto->toArray(), $pageDto->getPerPage());
    }

    public function findAllByUser(CriteriaDto $criteriaDto, PageDto $pageDto, int $userId): LengthAwarePaginator
    {
        $criteria = $criteriaDto->toArray();
        $criteria[] = new EqualCriterion('user_id', $userId);

        return $this->projectSolutionRepository->findByCriteria($criteria, $pageDto->getPerPage());
    }

    public function findById(int $id): ProjectSolution
    {
        return $this->projectSolutionRepository->findById($id);
    }

    public function updateFeedback(int $id, ?string $feedback): ProjectSolution
    {
        /** @var ProjectSolution */
        return $this->projectSolutionRepository->update([
            'tutor_feedback' => $feedback === '' ? null : $feedback,
        ], $id);
    }

    public function create(CreateProjectSolutionDto $dto): ProjectSolution
    {
        $path = $dto->getFile()->storeAs(
            self::DIR . DIRECTORY_SEPARATOR . $dto->getTopicId() . DIRECTORY_SEPARATOR . $dto->getUserId(),
            $dto->getFile()->getClientOriginalName(),
        );

        /** @var ProjectSolution $projectSolution */
        $projectSolution = $this->projectSolutionRepository->create(array_merge($dto->toArray(), [
                'path' => $path,
            ])
        );

        $this->notify($projectSolution);

        return $projectSolution;
    }

    public function delete(int $id): void
    {
        $solution = $this->projectSolutionRepository->findById($id);
        Storage::delete($solution->path);
        $this->projectSolutionRepository->delete($id);
    }

    public function grade(int $id, GradeProjectSolutionDto $dto): ProjectSolution
    {
        $solution = $this->projectSolutionRepository->findById($id);
        $solution->update(array_merge($dto->toArray(), [
            'graded_at' => now(),
        ]));

        // A graded solution is fully graded by definition (a lecturer set the score),
        // so signal the journal right away; re-grading emits again for an idempotent upsert.
        $project = $solution->topic->topicable;
        if ($project && $project->counts_to_grade) {
            event(new ProjectSolutionGradedEvent($solution->user, $solution));
        }

        return $solution;
    }

    private function notify(ProjectSolution $projectSolution): void
    {
        $project = $projectSolution->topic->topicable;
        $notifyUsers = User::findMany($project->notify_users);

        collect($notifyUsers)->each(function ($user) use ($projectSolution) {
            event(new ProjectSolutionCreatedEvent($user, $projectSolution));
        });
    }
}
