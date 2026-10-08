<?php

namespace Ulams\TemplatesSms\Facades;

use Ulams\TemplatesSms\Testing\SmsFake;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Tzsk\Sms\Sms
 * @see \Ulams\TemplatesSms\Testing\SmsFake
 */
class Sms extends Facade
{
    /**
     * Replace the bound instance with a fake.
     */
    public static function fake(): SmsFake
    {
        $fake = app(SmsFake::class);

        static::swap($fake);

        return $fake;
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'tzsk-sms';
    }
}
