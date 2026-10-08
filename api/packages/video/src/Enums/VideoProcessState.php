<?php

namespace Ulams\Video\Enums;

use Ulams\Core\Enums\BasicEnum;

class VideoProcessState extends BasicEnum
{
    const QUEUE = 'queue';
    const STARTING = 'starting';
    const CODING = 'coding';
    const FINISHED = 'finished';
    const ERROR = 'error';
}
