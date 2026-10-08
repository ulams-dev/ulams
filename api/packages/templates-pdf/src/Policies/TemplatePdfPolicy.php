<?php

namespace Ulams\TemplatesPdf\Policies;

use Ulams\Core\Models\User;
use Ulams\TemplatesPdf\Enums\PdfPermissionsEnum;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Illuminate\Auth\Access\HandlesAuthorization;

class TemplatePdfPolicy
{
    use HandlesAuthorization;

    public function read(User $user, FabricPDF $pdf): bool
    {
        return $pdf->user_id === $user->id || $user->can(PdfPermissionsEnum::PDF_READ_ALL);
    }

    public function list(?User $user): bool
    {
        return !is_null($user);
    }
}
