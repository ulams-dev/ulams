<?php

namespace EscolaLms\TopicTypes\Services\Contracts;

interface TopicTypeServiceContract
{
    public static function sanitizePath(string $path): string;

    public function fixAssetPaths(): array;

    public function fixTopicTypeColumnName(): int;
}
