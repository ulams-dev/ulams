<?php

namespace Ulams\Core\Tests\Mocks;

use Ulams\Core\Dtos\Contracts\CompareDtoContract;

class CompareDto extends UpdateDto implements CompareDtoContract
{
    public function identifier(): array
    {
        return [
            'email' => $this->email
        ];
    }
}
