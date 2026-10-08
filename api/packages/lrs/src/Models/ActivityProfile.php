<?php

namespace Ulams\Lrs\Models;

/**
 * @property string $profile_id
 * @property string $activity_id
 */
class ActivityProfile extends XapiDocument
{
    protected $table = 'trax_xapi_activity_profiles';

    public static function documentIdColumn(): string
    {
        return 'profile_id';
    }
}
