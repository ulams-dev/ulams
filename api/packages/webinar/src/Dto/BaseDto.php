<?php

namespace Ulams\Webinar\Dto;

use Ulams\Webinar\Dto\Traits\DtoHelper;

abstract class BaseDto
{
    use DtoHelper;

    public function __construct(array $data = [])
    {
        $this->setterByData($data);
    }
}
