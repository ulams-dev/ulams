<?php

namespace EscolaLms\Video\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class VideoProcessState extends BasicEnum
{
    const QUEUE = 'queue';
    const STARTING = 'starting';
    const CODING = 'coding';
    const FINISHED = 'finished';
    const ERROR = 'error';
}
