<?php

namespace EscolaLms\Files\Providers;

use EscolaLms\Auth\Events\AccountConfirmed;
use EscolaLms\Courses\Events\CourseTutorAssigned;
use EscolaLms\Courses\Events\CourseTutorUnassigned;
use EscolaLms\Files\Enums\DirectoryNamesEnum;
use EscolaLms\Files\Http\Services\Contracts\FileServiceContract;
use EscolaLms\StationaryEvents\Events\StationaryEventAuthorAssigned;
use EscolaLms\StationaryEvents\Events\StationaryEventAuthorUnassigned;
use EscolaLms\Webinar\Events\WebinarTrainerAssigned;
use EscolaLms\Webinar\Events\WebinarTrainerUnassigned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen(AccountConfirmed::class, function (AccountConfirmed $event) {
            /**
             * >>> event(new EscolaLms\Auth\Events\AccountConfirmed(App\Models\User::find(9)));
             */
            app(FileServiceContract::class)->addUserAccessToDirectory(
                $event->user,
                DirectoryNamesEnum::AVATARS . DIRECTORY_SEPARATOR . $event->user->getKey());
        });

        Event::listen(CourseTutorAssigned::class, function (CourseTutorAssigned $event) {
            /**
             * >>> event(new EscolaLms\Courses\Events\CourseTutorAssigned(App\Models\User::find(9), EscolaLms\Courses\Models\Course::find(6)));
             */
            app(FileServiceContract::class)->addUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::COURSE . DIRECTORY_SEPARATOR . $event->getCourse()->getKey());
        });

        Event::listen(CourseTutorUnassigned::class, function (CourseTutorUnassigned $event) {
            /**
             * >>> event(new EscolaLms\Courses\Events\CourseTutorUnassigned(App\Models\User::find(9), EscolaLms\Courses\Models\Course::find(6)));
             */
            app(FileServiceContract::class)->removeUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::COURSE . DIRECTORY_SEPARATOR . $event->getCourse()->getKey());
        });

        Event::listen(WebinarTrainerAssigned::class, function (WebinarTrainerAssigned $event) {
            /**
             * >>> event(new EscolaLms\Webinar\Events\WebinarTrainerAssigned(App\Models\User::find(9), EscolaLms\Webinar\Models\Webinar::find(1)));
             */
            app(FileServiceContract::class)->addUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::WEBINAR . DIRECTORY_SEPARATOR . $event->getWebinar()->getKey());
        });

        Event::listen(WebinarTrainerUnassigned::class, function (WebinarTrainerUnassigned $event) {
            /**
             * >>> event(new EscolaLms\Webinar\Events\WebinarTrainerUnassigned(App\Models\User::find(9), EscolaLms\Webinar\Models\Webinar::find(1)));
             */
            app(FileServiceContract::class)->removeUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::WEBINAR . DIRECTORY_SEPARATOR . $event->getWebinar()->getKey());
        });

        Event::listen(StationaryEventAuthorAssigned::class, function (StationaryEventAuthorAssigned $event) {
            /**
             *
             * >>> event(new EscolaLms\StationaryEvents\Events\StationaryEventAuthorAssigned(App\Models\User::find(9), EscolaLms\StationaryEvents\Models\StationaryEvent::find(1)));
             */
            app(FileServiceContract::class)->addUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::STATIONARY_EVENT . DIRECTORY_SEPARATOR . $event->getStationaryEvent()->getKey());
        });

        Event::listen(StationaryEventAuthorUnassigned::class, function (StationaryEventAuthorUnassigned $event) {
            /**
             * >>> event(new EscolaLms\StationaryEvents\Events\StationaryEventAuthorUnassigned(App\Models\User::find(9), EscolaLms\StationaryEvents\Models\StationaryEvent::find(1)));
             */
            app(FileServiceContract::class)->removeUserAccessToDirectory(
                $event->getUser(),
                DirectoryNamesEnum::STATIONARY_EVENT . DIRECTORY_SEPARATOR . $event->getStationaryEvent()->getKey());
        });
    }
}
