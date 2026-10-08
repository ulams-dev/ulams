<?php

namespace Ulams\Recommender\Dto;

use Ulams\Recommender\Dto\Traits\DtoHelper;

abstract class BaseDto
{
    use DtoHelper;

    public function __construct(array $data = [])
    {
        $this->setterByData($data);
    }
}
