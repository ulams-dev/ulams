<?php

namespace Ulams\CodeExercises\Enums;

/** When a code exercise topic completes: after a passing attempt, or after any attempt. */
enum CompletionRule: string
{
    case ON_PASS = 'on_pass';
    case ON_ATTEMPT = 'on_attempt';
}
