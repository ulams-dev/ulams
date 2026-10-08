<?php

namespace Ulams\Core\Tests\Mocks;

use Ulams\Core\Dtos\Contracts\DtoContract;

class UpdateDto implements DtoContract
{
    protected string $email;
    protected ?string $country;

    public function __construct(string $email, ?string $country = null)
    {
        $this->email = $email;
        $this->country = $country;
    }

    public function toArray(): array
    {
        return [
            'email' =>  $this->email,
            'country' => $this->country,
        ];
    }
}
