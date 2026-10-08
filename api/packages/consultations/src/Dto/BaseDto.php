<?php

namespace EscolaLms\Consultations\Dto;

use EscolaLms\Consultations\Dto\Traits\DtoHelper;

abstract class BaseDto
{
    use DtoHelper;

    public function __construct(array $data = [])
    {
        $this->setterByData($data);
    }
}
