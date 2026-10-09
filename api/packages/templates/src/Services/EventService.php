<?php

namespace Ulams\Templates\Services;

use Ulams\Cart\Models\Product;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Services\Contracts\EventServiceContract;

class EventService implements EventServiceContract
{
    public function dispatchEventManuallyForUsers(array $users, Template $template, ?int $courseId = null, ?int $productId = null): bool
    {
        $channelClass = $template->channel;
        $variableClass = $template->variableClass;

        if (!$template->is_valid) {
            return false;
        }

        $course = Course::find($courseId);
        $product = Product::find($productId);

        foreach ($users as $user) {
            $user = is_int($user) ? User::find($user) : $user;

            if ($user) {
                $event = new EventWrapper(new ManuallyTriggeredEvent($user, $course, $product));
                $variables = $variableClass::variablesFromEvent($event);
                $sections = $template->generateContent($variables);
                $sections['template_id'] = $template->id;

                $channelClass::send($event, $sections);
            }
        }

        return true;
    }
}
