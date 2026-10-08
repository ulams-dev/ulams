<?php

namespace EscolaLms\Templates\Facades;

use EscolaLms\Templates\Core\TemplatePreview;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\Templates\Models\Template as TemplateModel;
use EscolaLms\Templates\Services\Contracts\TemplateEventServiceContract;
use EscolaLms\Templates\Testing\TemplateFake;
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
 * @method static TemplatePreview sendPreview(\EscolaLms\Core\Models\User $user, \EscolaLms\Templates\Models\Template $template)
 * @method static   TemplateModel processTemplateAfterSaving(\EscolaLms\Templates\Models\Template $template)
 * @method static      Collection listAssignableTemplates(?string $assignableClass = null, ?string $eventClass = null, ?string $channelClass = null)
 * 
 * @see \EscolaLms\Templates\Services\TemplateEventService
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
