<?php

namespace EscolaLms\Recommender\Dto;

use EscolaLms\Recommender\Dto\Traits\DtoHelper;

abstract class BaseDto
{
    use DtoHelper;

    public function __construct(array $data = [])
    {
        $this->setterByData($data);
    }
}
