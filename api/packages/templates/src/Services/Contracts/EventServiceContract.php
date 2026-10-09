<?php

namespace Ulams\Templates\Services\Contracts;

use Ulams\Templates\Models\Template;

interface EventServiceContract
{
    public function dispatchEventManuallyForUsers(array $users, Template $template, ?int $courseId = null, ?int $productId = null): bool;
}
