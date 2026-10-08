# Courses-Import-Export

## What does it do
This package is responsible for dealing courses in `.ulam` format

#### 1. Exporting
Export do create zip package in ulam format with `content.json` and all essential assets. [Export Resource](https://github.com/EscolaLMS/Courses#adding-new-topiccontent-type) is used for this.

#### 2. Importing
Importing the courses in ulam format.

#### 3. Cloning


## Installing
- `composer require ulams/course-import-export`
- `php artisan db:seed --class="Ulams\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder"`

## Endpoints
All the endpoints are defined in swagger

## Tests
Run `./vendor/bin/phpunit` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

Test details

## Events
1. `CloneCourseStarted` - event dispatched after course cloning is started.
2. `CloneCourseFailed` - event dispatched after unsuccessful course cloning.
3. `CloneCourseFinished` - event dispatched after successfully course cloning.


## Permissions
Permissions are defined in [seeder](database/seeders/CoursesExportImportPermissionSeeder.php)
