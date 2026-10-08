<?php

namespace EscolaLms\Templates\Services\Contracts;

use EscolaLms\Templates\Models\Template;

interface EventServiceContract
{
    public function dispatchEventManuallyForUsers(array $users, Template $template, int $courseId = null, int $productId = null): bool;
}
