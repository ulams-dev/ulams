<?php


namespace Ulams\Courses\ValueObjects\Contracts;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Courses\ValueObjects\ValueObject;

interface ValueObjectContract extends DtoContract
{
    public static function make(): ValueObject;
}