# Bookmarks and Notes

## What does it do
This package is used to manage bookmarks and notes.
Bookmarks and notes are stored in a single data model.
By convention, a note is a database entry containing a value in the value field. 

## Installing
- `composer require ulams/bookmarks_notes`
- `php artisan migrate`
- `php artisan db:seed --class="Ulams\Bookmarks\Database\Seeders\BookmarkPermissionSeeder"`

## Endpoints
All the endpoints are defined in swagger

Test details
![Tests PHPUnit in environments](https://github.com/EscolaLMS/Bookmarks-Notes/actions/workflows/test.yml/badge.svg)

## Permissions
Permissions are defined in [seeder](database/seeders/BookmarkPermissionSeeder.php)
