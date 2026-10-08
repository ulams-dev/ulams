<?php

namespace Ulams\PencilSpaces\Services\Contracts;

use Ulams\PencilSpaces\Resource\CreatePencilSpaceResource;

interface PencilSpacesServiceContract
{
    public function getDirectLoginUrl(int $userId, string $redirectUrl = null): string;
    public function createSpace(CreatePencilSpaceResource $createSpaceResource): array;
}
