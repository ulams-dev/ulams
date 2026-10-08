<?php

namespace EscolaLms\Pages\Repository;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Pages\Models\Page;
use EscolaLms\Pages\Repository\Contracts\PageRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PageRepository extends BaseRepository implements PageRepositoryContract
{
    public function model()
    {
        return Page::class;
    }

    public function getFieldsSearchable()
    {
        return [];
    }

    public function searchAndPaginate(array $search = [], ?int $perPage = null, string $orderDirection = 'asc', string $orderColumn = 'id'): LengthAwarePaginator
    {
        return $this->allQuery($search)->orderBy($orderColumn, $orderDirection)->paginate($perPage);
    }

    /**
     * @param string $slug
     * @return Page
     */
    public function getBySlug(string $slug): Page
    {
        return $this->model->newQuery()->where('slug', $slug)->firstOrNew();
    }

    /**
     * @param Page $page
     * @return Page
     */
    public function insert(Page $page): Page
    {
        return $this->createUsingModel($page);
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deletePage(int $id): bool
    {
        $page = $this->find($id);
        if (!$page) {
            return false;
        }
        try {
            return $page->delete();
        } catch (\Exception $err) {
            return false;
        }
    }

    public function save(Page $page): bool
    {
        return $page->save();
    }
}
