<?php

namespace EscolaLms\Templates\Policies;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Enums\TemplatesPermissionsEnum;
use EscolaLms\Templates\Models\Template;
use Illuminate\Auth\Access\HandlesAuthorization;

class TemplatePolicy
{
    use HandlesAuthorization;

    public function list(?User $user): bool
    {
        return !is_null($user) && $user->can(TemplatesPermissionsEnum::TEMPLATES_LIST);
    }

    public function read(?User $user): bool
    {
        return !is_null($user) && $user->can(TemplatesPermissionsEnum::TEMPLATES_READ);
    }

    public function create(?User $user): bool
    {
        return !is_null($user) && $user->can(TemplatesPermissionsEnum::TEMPLATES_CREATE);
    }

    public function delete(?User $user, Template $template = null): bool
    {
        return !is_null($user) && $user->can(TemplatesPermissionsEnum::TEMPLATES_DELETE);
    }

    public function update(?User $user, Template $template = null): bool
    {
        return !is_null($user) && $user->can(TemplatesPermissionsEnum::TEMPLATES_UPDATE);
    }
}
