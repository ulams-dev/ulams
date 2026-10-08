<?php

namespace Ulams\Webinar\Tests\Mocks;

use Ulams\Youtube\Dto\Contracts\YTCdnDtoContract;
use Ulams\Youtube\Dto\Contracts\YTStreamDtoContract;

class YTStreamDtoMock implements YTStreamDtoContract
{
    public function getYTCdnDto(): YTCdnDtoContract
    {
        return new YTCdnDtoMock();
    }

}
