<?php

namespace Ulams\Lrs\Models;

/**
 * @property string $state_id
 * @property string $activity_id
 * @property string $vid
 * @property string|null $registration
 */
class State extends XapiDocument
{
    protected $table = 'trax_xapi_states';

    public static function documentIdColumn(): string
    {
        return 'state_id';
    }
}
