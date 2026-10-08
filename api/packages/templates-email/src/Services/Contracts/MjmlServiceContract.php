<?php

namespace EscolaLms\TemplatesEmail\Services\Contracts;

interface MjmlServiceContract
{
    public function render(string $mjml): string;
}
