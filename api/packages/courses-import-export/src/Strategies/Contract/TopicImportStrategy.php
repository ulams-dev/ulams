<?php

namespace EscolaLms\CoursesImportExport\Strategies\Contract;

interface TopicImportStrategy
{
    public function make(string $path, array $data): ?int;
}
