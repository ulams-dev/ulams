<?php

namespace Ulams\TopicTypeProject\Services\Contracts;

use Ulams\TopicTypeProject\Dtos\CreateProjectSolutionDto;
use Ulams\TopicTypeProject\Dtos\CriteriaDto;
use Ulams\TopicTypeProject\Dtos\GradeProjectSolutionDto;
use Ulams\TopicTypeProject\Dtos\PageDto;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjectSolutionServiceContract
{
    public function findAll(CriteriaDto $criteriaDto, PageDto $pageDto): LengthAwarePaginator;
    public function findAllByUser(CriteriaDto $criteriaDto, PageDto $pageDto, int $userId): LengthAwarePaginator;
    public function findById(int $id): ProjectSolution;
    public function create(CreateProjectSolutionDto $dto): ProjectSolution;
    public function updateFeedback(int $id, ?string $feedback): ProjectSolution;
    public function delete(int $id): void;
    public function grade(int $id, GradeProjectSolutionDto $dto): ProjectSolution;
}
