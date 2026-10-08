<?php


namespace EscolaLms\Tags\Services\Contracts;


use EscolaLms\Tags\Dto\TagDto;
use Illuminate\Support\Collection;

interface TagServiceContract
{
    public function insert(TagDto $tagDto) : Collection;

    public function removeTags(array $tags);
}