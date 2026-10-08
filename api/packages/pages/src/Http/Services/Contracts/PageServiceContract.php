<?php

namespace EscolaLms\Pages\Http\Services\Contracts;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Pages\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface PageServiceContract
 * @package EscolaLms\Pages\Http\Services\Contracts
 */
interface PageServiceContract
{
    public function search(array $search = []): LengthAwarePaginator;

    public function list(array $criteria, OrderDto $orderDto): LengthAwarePaginator;

    public function getBySlug(string $slug): Page;

    public function getById(int $id): Page;

    /**
     * @throws EscolaLms\Pages\Http\Exception\PageAlreadyExistsException
     */
    public function insert(string $slug, string $title, string $content, int $userId, bool $active): Page;

    public function deleteById(int $id): bool;

    public function update(int $id, array $data): Page;
}
