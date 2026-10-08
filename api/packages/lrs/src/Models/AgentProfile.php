<?php

namespace Ulams\Lrs\Models;

/**
 * @property string $profile_id
 * @property string $vid
 */
class AgentProfile extends XapiDocument
{
    protected $table = 'trax_xapi_agent_profiles';

    public static function documentIdColumn(): string
    {
        return 'profile_id';
    }
}
