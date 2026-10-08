<?php

namespace Ulams\Templates\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Models\User;
use Ulams\Templates\Dtos\TemplateFilterCriteriaDto;
use Ulams\Templates\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TemplateServiceContract
{
    public function search(array $search = [], ?int $perPage = null): LengthAwarePaginator;

    public function list(TemplateFilterCriteriaDto $criteria, OrderDto $orderDto, ?int $perPage = null): LengthAwarePaginator;
    public function getById(int $id): Template;

    public function deleteById(int $id): bool;

    public function update(int $id, array $data): Template;

    public function insert(array $data): Template;

    public function isValid(Template $template): bool;

    public function generateContentUsingVariables(Template $template, array $variables): array;

    public function previewContentUsingMockedVariables(Template $template, ?User $user = null): array;

    public function assignTemplateToModel(Template $template, int $assignable_id): Template;

    public function unassignTemplateFromModel(Template $template, int $assignable_id): Template;

    public function findTemplatesAssignedToModel(string $assignable_class, int $assignable_id): Collection;
}
