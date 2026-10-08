<?php

namespace Ulams\CoursesImportExport\Strategies\Contract;

interface TopicImportStrategy
{
    public function make(string $path, array $data): ?int;
}
