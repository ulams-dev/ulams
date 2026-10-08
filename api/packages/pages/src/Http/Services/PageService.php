<?php

namespace EscolaLms\Pages\Http\Services;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Pages\Http\Exceptions\PageAlreadyExistsException;
use EscolaLms\Pages\Http\Services\Contracts\PageServiceContract;
use EscolaLms\Pages\Models\Page;
use EscolaLms\Pages\Repository\Contracts\PageRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PageService implements PageServiceContract
{
    private PageRepositoryContract $repository;

    public function __construct(PageRepositoryContract $repository)
    {
        $this->repository = $repository;
    }

    public function search(array $search = []): LengthAwarePaginator
    {
        return $this->repository->searchAndPaginate($search);
    }

    public function list(array $criteria, OrderDto $orderDto): LengthAwarePaginator
    {
        $query = $this->repository->queryWithAppliedCriteria($criteria);
        if ($orderDto->getOrderBy()) {
            $query->orderBy($orderDto->getOrderBy(), $orderDto->getOrder() ?? 'asc');
        }

        return $query->paginate();
    }

    public function getBySlug(string $slug): Page
    {
        return $this->repository->getBySlug($slug);
    }

    public function getById(int $id): Page
    {
        return $this->repository->find($id);
    }

    /**
     * @throws PageAlreadyExistsException
     */
    public function insert(string $slug, string $title, string $content, int $userId, bool $active): Page
    {
        /** @var Page $page */
        $page = Page::factory()->newModel([
            'slug' => $slug,
            'title' => $title,
            'author_id' => $userId,
            'content' => $content,
            'active' => $active
        ]);
        $this->repository->insert($page);
        if (!$page->exists()) {
            throw new PageAlreadyExistsException($page);
        }
        return $page;
    }

    public function deleteById(int $id): bool
    {
        return $this->repository->deletePage($id);
    }

    public function update(int $id, array $data): Page
    {
        return $this->repository->update($data, $id);
    }
}
