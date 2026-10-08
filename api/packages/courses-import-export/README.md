# Courses-Import-Export
[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Courses-Import-Export/)
[![codecov](https://codecov.io/gh/EscolaLMS/Courses-Import-Export/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Courses-Import-Export)
[![phpunit](https://github.com/EscolaLMS/Courses-Import-Export/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Courses-Import-Export/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/Courses-Import-Export)](https://packagist.org/packages/escolalms/Courses-Import-Export)
[![downloads](https://img.shields.io/packagist/v/escolalms/Courses-Import-Export)](https://packagist.org/packages/escolalms/Courses-Import-Export)
[![downloads](https://img.shields.io/packagist/l/escolalms/Courses-Import-Export)](https://packagist.org/packages/escolalms/Courses-Import-Export)
[![Maintainability](https://api.codeclimate.com/v1/badges/04a88ff03ede597fd18b/maintainability)](https://codeclimate.com/github/EscolaLMS/Courses-Import-Export/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/04a88ff03ede597fd18b/test_coverage)](https://codeclimate.com/github/EscolaLMS/Courses-Import-Export/test_coverage)

## What does it do
This package is responsible for dealing courses in `.ulam` format

#### 1. Exporting
Export do create zip package in ulam format with `content.json` and all essential assets. [Export Resource](https://github.com/EscolaLMS/Courses#adding-new-topiccontent-type) is used for this.

#### 2. Importing
Importing the courses in ulam format.

#### 3. Cloning


## Installing
- `composer require escolalms/course-import-export`
- `php artisan db:seed --class="EscolaLms\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder"`

## Endpoints
All the endpoints are defined in swagger
[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Courses-Import-Export/)

## Tests
Run `./vendor/bin/phpunit` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

Test details
[![codecov](https://codecov.io/gh/EscolaLMS/Courses-Import-Export/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Courses-Import-Export)
[![phpunit](https://github.com/EscolaLMS/Courses-Import-Export/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Courses-Import-Export/actions/workflows/test.yml)

## Events
1. `CloneCourseStarted` - event dispatched after course cloning is started.
2. `CloneCourseFailed` - event dispatched after unsuccessful course cloning.
3. `CloneCourseFinished` - event dispatched after successfully course cloning.


## Permissions
Permissions are defined in [seeder](database/seeders/CoursesExportImportPermissionSeeder.php)
