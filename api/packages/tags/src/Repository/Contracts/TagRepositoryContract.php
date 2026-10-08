<?php


namespace Ulams\Tags\Repository\Contracts;


use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Tags\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

interface TagRepositoryContract extends BaseRepositoryContract
{
    /**
     * @param array $tagData
     * @return Tag
     */
    public function insert(array $tagData) : Tag;

    /**
     * @param string $column
     * @param array $payload
     * @return bool|null
     */
    public function deleteWhereIn(string $column, array $payload): ?bool;

    /**
     * @return Collection
     */
    public function unique(): Collection;

    /**
     * @return Collection|null
     */
    public function uniqueTagsFromActiveCourses(): ?Collection;
}
