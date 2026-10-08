<?php

namespace EscolaLms\Templates\Repository\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use EscolaLms\Templates\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TemplateRepositoryContract extends BaseRepositoryContract
{
    public function searchAndPaginate(array $search = [], ?int $perPage = null, string $orderDirection = 'asc', string $orderColumn = 'id'): LengthAwarePaginator;
    public function deleteTemplate(int $id): bool;

    public function createWithSections(array $attributes, array $sections): Template;
    public function updateWithSections(array $attributes, array $sections, int $id): Template;

    public function findTemplateDefault(string $event, string $channel): ?Template;
    public function findTemplateAssigned(string $event, string $channel, string $assigned_class, int $assigned_value): ?Template;
    public function findAllTemplatesAssigned(string $assignable_class, int $assignable_id): Collection;
}
