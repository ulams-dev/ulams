<?php

namespace Ulams\Core\Observability;

use Illuminate\Support\Facades\Context;

/** Headers that carry the current request id to the services the API calls (H5P, PDF), so their logs share it. */
final class Propagation
{
    /** @return array<string, string> */
    public static function headers(): array
    {
        $id = Context::get('request_id');

        return is_string($id) && $id !== '' ? ['X-Request-Id' => $id] : [];
    }
}
