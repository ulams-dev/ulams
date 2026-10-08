<?php

namespace EscolaLms\Categories\Dtos;

use EscolaLms\Categories\Dtos\Traits\DtoHelper;

abstract class BaseDto
{
    use DtoHelper;

    public function __construct(array $data = [])
    {
        $this->setterByData($data);
    }
}