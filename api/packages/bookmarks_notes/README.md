# Bookmarks and Notes
[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Bookmarks-Notes/)
[![codecov](https://codecov.io/gh/EscolaLMS/Bookmarks-Notes/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Bookmarks)
[![phpunit](https://github.com/EscolaLMS/Bookmarks-Notes/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Bookmarks-Notes/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/Bookmarks-Notes)](https://packagist.org/packages/escolalms/bookmarks_notes)
[![downloads](https://img.shields.io/packagist/v/escolalms/Bookmarks-Notes)](https://packagist.org/packages/escolalms/bookmarks_notes)
[![downloads](https://img.shields.io/packagist/l/escolalms/Bookmarks-Notes)](https://packagist.org/packages/escolalms/bookmarks_notes)

## What does it do
This package is used to manage bookmarks and notes.
Bookmarks and notes are stored in a single data model.
By convention, a note is a database entry containing a value in the value field. 

## Installing
- `composer require escolalms/bookmarks_notes`
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\Bookmarks\Database\Seeders\BookmarkPermissionSeeder"`

## Endpoints
All the endpoints are defined in swagger
[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Bookmarks-Notes/)

Test details
[![codecov](https://codecov.io/gh/EscolaLMS/Bookmarks/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/EscolaLMS/Bookmarks)
![Tests PHPUnit in environments](https://github.com/EscolaLMS/Bookmarks-Notes/actions/workflows/test.yml/badge.svg)

## Permissions
Permissions are defined in [seeder](database/seeders/BookmarkPermissionSeeder.php)
