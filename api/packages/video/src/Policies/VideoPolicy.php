<?php

namespace EscolaLms\Video\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\Video\Enums\VideoPermissionEnum;
use Illuminate\Auth\Access\HandlesAuthorization;

class VideoPolicy
{
    use HandlesAuthorization;

    public function states(User $user): bool
    {
        return $user->can(VideoPermissionEnum::VIDEO_PROCESS_STATES_LIST);
    }
}
