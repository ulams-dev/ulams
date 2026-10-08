<?php

namespace Ulams\Files\Providers;

use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Courses\Events\CourseTutorAssigned;
use Ulams\Courses\Events\CourseTutorUnassigned;
use Ulams\Files\Enums\DirectoryNamesEnum;
use Ulams\Files\Http\Services\Contracts\FileServiceContract;
use Ulams\StationaryEvents\Events\StationaryEventAuthorAssigned;
use Ulams\StationaryEvents\Events\StationaryEventAuthorUnassigned;
use Ulams\Webinar\Events\WebinarTrainerAssigned;
use Ulams\Webinar\Events\WebinarTrainerUnassigned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen(AccountConfirmed::class, function (AccountConfirmed $event) {
            /**
             * >>> event(new Ulams\Auth\Events\AccountConfirmed(App\Models\User::find(9)));
             */
            app(FileServiceContract::class)->addUserAccessToDirectory(
                $event->user,
                DirectoryNamesEnum::AVATARS . DIRECTORY_SEPARATOR . $event->user->getKey());
        });

        Event::listen(CourseTutorAssigned::class, function (CourseTutorAssigned $event) {
            /**
             * >>> event(new Ulams\Courses\Events\CourseTutorAssigned(App\Models\User::find(9), Ulams\Courses\Models\Course::find(6)));
             */
            app(FileServiceContract::class)->addUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::COURSE . DIRECTORY_SEPARATOR . $event->getCourse()->getKey());
        });

        Event::listen(CourseTutorUnassigned::class, function (CourseTutorUnassigned $event) {
            /**
             * >>> event(new Ulams\Courses\Events\CourseTutorUnassigned(App\Models\User::find(9), Ulams\Courses\Models\Course::find(6)));
             */
            app(FileServiceContract::class)->removeUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::COURSE . DIRECTORY_SEPARATOR . $event->getCourse()->getKey());
        });

        Event::listen(WebinarTrainerAssigned::class, function (WebinarTrainerAssigned $event) {
            /**
             * >>> event(new Ulams\Webinar\Events\WebinarTrainerAssigned(App\Models\User::find(9), Ulams\Webinar\Models\Webinar::find(1)));
             */
            app(FileServiceContract::class)->addUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::WEBINAR . DIRECTORY_SEPARATOR . $event->getWebinar()->getKey());
        });

        Event::listen(WebinarTrainerUnassigned::class, function (WebinarTrainerUnassigned $event) {
            /**
             * >>> event(new Ulams\Webinar\Events\WebinarTrainerUnassigned(App\Models\User::find(9), Ulams\Webinar\Models\Webinar::find(1)));
             */
            app(FileServiceContract::class)->removeUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::WEBINAR . DIRECTORY_SEPARATOR . $event->getWebinar()->getKey());
        });

        Event::listen(StationaryEventAuthorAssigned::class, function (StationaryEventAuthorAssigned $event) {
            /**
             *
             * >>> event(new Ulams\StationaryEvents\Events\StationaryEventAuthorAssigned(App\Models\User::find(9), Ulams\StationaryEvents\Models\StationaryEvent::find(1)));
             */
            app(FileServiceContract::class)->addUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::STATIONARY_EVENT . DIRECTORY_SEPARATOR . $event->getStationaryEvent()->getKey());
        });

        Event::listen(StationaryEventAuthorUnassigned::class, function (StationaryEventAuthorUnassigned $event) {
            /**
             * >>> event(new Ulams\StationaryEvents\Events\StationaryEventAuthorUnassigned(App\Models\User::find(9), Ulams\StationaryEvents\Models\StationaryEvent::find(1)));
             */
            app(FileServiceContract::class)->removeUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::STATIONARY_EVENT . DIRECTORY_SEPARATOR . $event->getStationaryEvent()->getKey());
        });
    }
}
