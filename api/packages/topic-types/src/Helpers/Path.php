<?php

namespace EscolaLms\TopicTypes\Helpers;

class Path
{
    public function sanitizePathForExport(string $path): string
    {
        return preg_replace('/courses\/[0-9]+\//', '', $path);
    }
}
