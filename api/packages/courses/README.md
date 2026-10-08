# Courses

Courses and content package


## What does it do

This package is used for creating Course for Ulams.

## Installing

- `composer require ulams/courses`
- `php artisan migrate`
- `php artisan db:seed --class="Ulams\Courses\Database\Seeders\CoursesPermissionSeeder"`

## Schedule

- Schedules are available in ScheduleServiceProvider
  - `$schedule->job(CheckForDeadlines::class)->hourly()` - executed every hours
  - `$schedule->job(ActivateCourseJob::class)->daily()` - executed every days

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/Courses/)

## Tests

Run `./vendor/bin/phpunit  --filter 'Ulams\\Courses\\Tests'` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

## Events

- `Ulams\Courses\Events\CourseAccessFinished` => Event is dispatched when users lost access to course.
- `Ulams\Courses\Events\CourseAccessStarted` => Event is dispatched when users received access to course.
- `Ulams\Courses\Events\CourseAssigned` => Event is dispatched when admin assigned access user.
- `Ulams\Courses\Events\CourseDeadlineSoon` => Event is dispatched when course deadline is coming out.
- `Ulams\Courses\Events\CoursedPublished` => Event is dispatched when course is published.
- `Ulams\Courses\Events\CourseFinished` => Event is dispatched when course is ended.
- `Ulams\Courses\Events\CourseStarted` => Event is dispatched when course is started.
- `Ulams\Courses\Events\CourseStatusChanged` => Event is dispatched when course has a status change.
- `Ulams\Courses\Events\CourseTutorAssigned` => Event is dispatched when tutor is assigned to course.
- `Ulams\Courses\Events\CourseTutorUnassigned` => Event is dispatched when tutor is unassigned to course.
- `Ulams\Courses\Events\CourseUnassigned` => Event is dispatched when user is unassigned to course.
- `Ulams\Courses\Events\TopicFinished` => Event is dispatched when course topic is finished.



## Permissions

Permissions are defined in [seeder](database/seeders/CoursesPermissionSeeder.php)


## Model relation

The model user must be extended with the class HasCourses :

```
class User extends Ulams\Core\Models\User
{
    use HasCourses;
```

## Database relation

There is simple relation. [see docs for diagram](doc)

1. `Course` general category of the course
2. `Lesson` grouped by Course
3. `Topic` grouped by Lesson

```
Course 1 -> n Lesson
Lesson 1 -> n Topic
Topic 1 -> 1 TopicContent
```

`TopicContent` is an abstract model, this package contains some sample implementatio eg, `RichText`, `Audio`, `Video`, `H5P` and `Image`

You create any of the Content model by post to the same Topic endponit (create and update), [see docs examples](doc)

**Note** that `/api/topics` is using `form-data` - this is due to PHP nature of posting files

List of possible `TopicContent`s is availabe in the endpoint `/api/topics/types`

## Curriculum/Sylabus/Program

App user access the course by fetching `GET /api/courses/{id}/program` endpoint. This is after user purchase or has other access to the course. this endpoints renders tree of Course, Lessons, Topic with Contents essential to render whole course.

## Adding new `TopicContent` type

In the ServiceProvider register your class like

```php
use Illuminate\Support\ServiceProvider;
use Ulams\Courses\Facades\Topic;


class CustomServiceProvider extends ServiceProvider
{

    //...

    public function register()
    {
        Topic::registerContentClass(TopicContentCustom::class);
        // or
        Topic::registerContentClasses([TopicContentCustom::class, TopicAnotherContentCustom::class]);

        // also register JSON Resource for a type
        Topic::registerResourceClasses(Audio::class, [
            'client' => ClientAudioResource::class,
            'admin' => AdminAudioResource::class,
            'export' => ExportAudioResource::class,
        ]);

    }
}
```

see [UlamsCourseServiceProvider.php](src/UlamsCourseServiceProvider.php) as reference as well as [Models/TopicContent](package2/src/Models/TopicContent)

### Content

Package comes with seeder that create course with lessons and topics

```php
php artisan db:seed --class="\Ulams\Courses\Database\Seeders\CoursesSeeder"
```
