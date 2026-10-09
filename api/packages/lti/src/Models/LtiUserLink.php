<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tool side: platform user (`iss` via the platform row, `sub`) to ulams user.
 */
class LtiUserLink extends Model
{
    protected $table = 'lti_user_links';

    protected $fillable = ['lti_platform_id', 'sub', 'user_id'];
}
