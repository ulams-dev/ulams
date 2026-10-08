<?php

namespace EscolaLms\TopicTypeProject\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Repositories\Contracts\ProjectSolutionRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProjectSolutionRepository extends BaseRepository implements ProjectSolutionRepositoryContract
{
    public function model(): string
    {
        return ProjectSolution::class;
    }

    public function getFieldsSearchable(): array
    {
        return [
            'topic_id',
            'user_id',
        ];
    }

    public function findById(int $id): ProjectSolution
    {
        /** @var ProjectSolution */
        return $this->model->newQuery()->with('topic.topicable')->findOrFail($id);
    }

    public function findByCriteria(array $criteria, int $perPage): LengthAwarePaginator
    {
        return $this->queryWithAppliedCriteria($criteria)
            ->with('topic.topicable')
            ->paginate($perPage);
    }
}
