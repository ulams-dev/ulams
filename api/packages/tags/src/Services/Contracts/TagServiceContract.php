<?php


namespace Ulams\Tags\Services\Contracts;


use Ulams\Tags\Dto\TagDto;
use Illuminate\Support\Collection;

interface TagServiceContract
{
    public function insert(TagDto $tagDto) : Collection;

    public function removeTags(array $tags);
}