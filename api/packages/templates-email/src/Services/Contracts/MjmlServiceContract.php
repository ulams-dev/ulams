<?php

namespace Ulams\TemplatesEmail\Services\Contracts;

interface MjmlServiceContract
{
    public function render(string $mjml): string;

    /**
     * Renders MJML and also returns the validation errors reported by the renderer (empty when the
     * renderer does not report any, e.g. the plain text and binary renderers).
     *
     * @return array{html: string, errors: string[]}
     */
    public function renderWithErrors(string $mjml): array;
}
