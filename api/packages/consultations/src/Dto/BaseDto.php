<?php

namespace Ulams\Consultations\Dto;

use Ulams\Consultations\Dto\Traits\DtoHelper;

abstract class BaseDto
{
    use DtoHelper;

    public function __construct(array $data = [])
    {
        $this->setterByData($data);
    }
}
