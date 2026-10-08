<?php

namespace EscolaLms\Tags\Services;

use EscolaLms\Tags\Dto\TagDto;
use EscolaLms\Tags\Repository\Contracts\TagRepositoryContract;
use EscolaLms\Tags\Services\Contracts\TagServiceContract;
use Illuminate\Support\Collection;

class TagService implements TagServiceContract
{
    protected TagRepositoryContract $tagRepositoryContract;

    public function __construct(
        TagRepositoryContract $tagRepositoryContract
    )
    {
        $this->tagRepositoryContract = $tagRepositoryContract;
    }

    /**
     * @param TagDto $tagDto
     * @return Collection
     */
    public function insert(TagDto $tagDto) : Collection
    {
        $tags = collect();
        foreach ($tagDto->getTags() as $tag) {
            if (isset($tag['title']) and $tag['title']) {
                $tagData = [
                    'morphable_id' => $tagDto->getModelId(),
                    'morphable_type' => $tagDto->getModelName(),
                    'title' => $tag['title']
                ];
                $tags->push($this->tagRepositoryContract->insert($tagData));
            }
        }
        return $tags;
    }

    /**
     * @param array $tags
     * @return bool
     */
    public function removeTags(array $tags) : bool
    {
        return $this->tagRepositoryContract->deleteWhereIn('id', $tags);
    }
}