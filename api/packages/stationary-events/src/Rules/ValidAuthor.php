<?php

namespace EscolaLms\StationaryEvents\Rules;

use EscolaLms\StationaryEvents\Models\StationaryEvent;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ValidAuthor implements Rule
{
    public function passes($attribute, $value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $user = Auth::user()->find($value);

        if (is_null($user) || !$user->can('create', StationaryEvent::class)) {
            return false;
        }

        return true;
    }

    public function message()
    {
        return __('Author must be a Tutor or Admin');
    }
}
