<?php

namespace Ulams\TemplatesSms\Enums;

use Ulams\Core\Enums\BasicEnum;

class SmsDriversEnum extends BasicEnum
{
    const MAIL = 'mail';
    const TWILIO = 'twilio';
    const REQUESTBIN = 'requestbin';
}