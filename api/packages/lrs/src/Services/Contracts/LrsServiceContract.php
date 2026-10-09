<?php

namespace Ulams\Lrs\Services\Contracts;

interface LrsServiceContract
{
    public function launchParams(?int $courseId = null, ?int $topicId = null, ?int $auId = null): array;

    public function saveState(array $params): array;

    public function saveAgent(array $params): array;
}
