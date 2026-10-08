<?php

namespace Ulams\Templates\Facades;

use Ulams\Templates\Core\TemplatePreview;
use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Models\Template as TemplateModel;
use Ulams\Templates\Services\Contracts\TemplateEventServiceContract;
use Ulams\Templates\Testing\TemplateFake;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static            void register(string $eventClass, string $channelClass, string $variableClass)
 * @method static            void handleEvent(EventWrapper $event)
 * @method static         ?string getVariableClassName(string $eventClass, string $channelClass)
 * @method static           array getRegisteredEvents()
 * @method static           array getRegisteredChannels()
 * @method static           array getRegisteredEventsWithTokens()
 * @method static            bool assertEventHandled(string $eventClass, string $channelClass, ?string $variableClass = null) 
 * @method static            void createDefaultTemplatesForChannel(string $channelClass)
 * @method static TemplatePreview sendPreview(\Ulams\Core\Models\User $user, \Ulams\Templates\Models\Template $template)
 * @method static   TemplateModel processTemplateAfterSaving(\Ulams\Templates\Models\Template $template)
 * @method static      Collection listAssignableTemplates(?string $assignableClass = null, ?string $eventClass = null, ?string $channelClass = null)
 * 
 * @see \Ulams\Templates\Services\TemplateEventService
 */
class Template extends Facade
{
    /**
     * Replace the bound instance with a fake.
     */
    public static function fake()
    {
        $fake = app(TemplateFake::class);
        $fake->setRegisteredEvents(self::getRegisteredEvents());

        static::swap($fake);

        return $fake;
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TemplateEventServiceContract::class;
    }
}
