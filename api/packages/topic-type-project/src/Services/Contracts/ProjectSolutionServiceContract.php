<?php

namespace EscolaLms\TopicTypeProject\Services\Contracts;

use EscolaLms\TopicTypeProject\Dtos\CreateProjectSolutionDto;
use EscolaLms\TopicTypeProject\Dtos\CriteriaDto;
use EscolaLms\TopicTypeProject\Dtos\GradeProjectSolutionDto;
use EscolaLms\TopicTypeProject\Dtos\PageDto;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
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
