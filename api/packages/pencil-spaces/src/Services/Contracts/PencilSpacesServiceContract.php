<?php

namespace EscolaLms\PencilSpaces\Services\Contracts;

use EscolaLms\PencilSpaces\Resource\CreatePencilSpaceResource;

interface PencilSpacesServiceContract
{
    public function getDirectLoginUrl(int $userId, string $redirectUrl = null): string;
    public function createSpace(CreatePencilSpaceResource $createSpaceResource): array;
}
