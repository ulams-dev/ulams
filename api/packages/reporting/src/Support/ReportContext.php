<?php

namespace Ulams\Reporting\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who is asking. A report always runs with the viewer's own permissions (ADR 0194, Q11), so every dataset gets
 * the viewer here and applies what they may see in `baseQuery()`. The result of the scoped-admin resolver
 * (P6R-7) will be carried here too once it exists; until then a dataset's permission is the whole scope.
 */
final class ReportContext
{
    public function __construct(public readonly ?Authenticatable $viewer)
    {
    }

    public function can(string $permission): bool
    {
        return $this->viewer !== null && method_exists($this->viewer, 'can') && (bool) $this->viewer->can($permission, 'api');
    }
}
