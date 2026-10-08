<?php

namespace Ulams\Mattermost\Providers;

use Ulams\Auth\Events\AccountBlocked;
use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Auth\Events\AccountDeleted;
use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Events\CourseTutorAssigned;
use Ulams\Courses\Events\CourseTutorUnassigned;
use Ulams\Courses\Events\CourseUnassigned;
use Ulams\Mattermost\Enum\MattermostRoleEnum;
use Ulams\Mattermost\Enum\PackageStatusEnum;
use Ulams\Mattermost\Enum\TeamNameEnum;
use Ulams\Mattermost\Services\Contracts\MattermostServiceContract;
use Ulams\Webinar\Events\WebinarTrainerAssigned;
use Ulams\Webinar\Events\WebinarTrainerUnassigned;
use Ulams\Webinar\Events\WebinarUserAssigned;
use Ulams\Webinar\Events\WebinarUserUnassigned;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (Config::get(SettingsServiceProvider::CONFIG_KEY . '.package_status', PackageStatusEnum::ENABLED) !== PackageStatusEnum::ENABLED) {
            return;
        }

        Event::listen(AccountConfirmed::class, function ($event) {
            /**
             * >>> event(new Ulams\Auth\Events\AccountConfirmed(App\Models\User::find(2)));
             */
            app(MattermostServiceContract::class)->addUser($event->user);
        });

        Event::listen(CourseAssigned::class, function ($event) {
            /**
             * >>> event(new Ulams\Courses\Events\CourseAssigned(App\Models\User::find(3), Ulams\Courses\Models\Course::find(1)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->addUserToChannel($user, $course->title);
        });

        Event::listen(CourseUnassigned::class, function ($event) {
            /**
             * >>> event(new Ulams\Courses\Events\CourseUnassigned(App\Models\User::find(3), Ulams\Courses\Models\Course::find(1)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->removeUserFromChannel($user, $course->title);
        });

        Event::listen(AccountBlocked::class, function ($event) {
            /**
             * >>> event(new Ulams\Auth\Events\AccountBlocked(App\Models\User::find(10)));
             */
            app(MattermostServiceContract::class)->blockUser($event->getUser());
        });

        Event::listen(AccountDeleted::class, function ($event) {
            /**
             * >>> event(new Ulams\Auth\Events\AccountDeleted(App\Models\User::find(10)));
             */
            app(MattermostServiceContract::class)->deleteUser($event->getUser());
        });

        Event::listen(CourseTutorAssigned::class, function ($event) {
            /**
             * >>> event(new Ulams\Courses\Events\CourseTutorAssigned(App\Models\User::find(9), Ulams\Courses\Models\Course::find(6)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->addUserToChannel($user, $course->title, TeamNameEnum::COURSES, MattermostRoleEnum::CHANNEL_ADMIN);
        });

        Event::listen(CourseTutorUnassigned::class, function ($event) {
            /**
             * >>> event(new Ulams\Courses\Events\CourseTutorUnassigned(App\Models\User::find(9), Ulams\Courses\Models\Course::find(6)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->removeUserFromChannel($user, $course->title);
        });

        Event::listen(WebinarUserAssigned::class, function ($event) {
            $user = $event->getUser();
            $webinar = $event->getWebinar();;
            app(MattermostServiceContract::class)->addUserToChannel($user, $webinar->name, TeamNameEnum::WEBINARS);
        });

        Event::listen(WebinarUserUnassigned::class, function ($event) {
            $user = $event->getUser();
            $webinar = $event->getWebinar();;
            app(MattermostServiceContract::class)->removeUserFromChannel($user, $webinar->name, TeamNameEnum::WEBINARS);
        });

        Event::listen(WebinarTrainerAssigned::class, function ($event) {
            $user = $event->getUser();
            $webinar = $event->getWebinar();
            app(MattermostServiceContract::class)->addUserToChannel($user, $webinar->name, TeamNameEnum::WEBINARS, MattermostRoleEnum::CHANNEL_ADMIN);
        });

        Event::listen(WebinarTrainerUnassigned::class, function ($event) {
            $user = $event->getUser();
            $webinar = $event->getWebinar();
            app(MattermostServiceContract::class)->removeUserFromChannel($user, $webinar->name, TeamNameEnum::WEBINARS);
        });
    }
}
