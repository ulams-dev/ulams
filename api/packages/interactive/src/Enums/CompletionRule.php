<?php

namespace Ulams\Interactive\Enums;

enum CompletionRule: string
{
    case ON_OPEN = 'on_open';
    case ON_RANGE_END = 'on_range_end';
    case ON_COMPLETE = 'on_complete';
    case ON_SCORE = 'on_score';
}
