# Headless H5P Laravel API for Ulams LMS ecosystem

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/H5P/)
[![codecov](https://codecov.io/gh/Ulams/H5P/branch/main/graph/badge.svg?token=ci4VPQbrOI)](https://codecov.io/gh/Ulams/H5P)
[![phpunit](https://github.com/EscolaLMS/H5P/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Core/actions/workflows/test.yml)
[![Maintainability](https://api.codeclimate.com/v1/badges/6316e8dc93a06d28c6a0/maintainability)](https://codeclimate.com/github/Ulams/H5P/maintainability)

## Before you begin
This product is tightly coupled with the Ulams LMS ecosystem and is not compatible with any other Laravel application.

## Working demo

Proof of concept demo is available at [https://h5p-laravel-demo.stage.etd24.pl/](https://h5p-laravel-demo.stage.etd24.pl/).

## Features

All of the features are available thought REST API, there are no blade templates of using server side rendering H5PIntegration global js variable, this is a different approach then `moodle`, `drupal` and `wordpress` h5p plugins.

This package does provide only REST API access endpoints, this is so far only package that allows to render h5p headlessly.

The features includes:

- play all h5p content - exposed all essential data, yet player is needed
- edit all h5p content - exposed all essential data, yet editor is needed
- CRUD libraries
- CRUD content
- upload library from `.h5p` file
- upload content from `.h5p` file
- all the other h5p features like export etc

## Documentation

See [Swagger](https://ulams.github.io/H5P/) documented endpoints.

Some [tests](tests) can also be a great point of start.

To play the content you can use [Ulams H5P Player](https://github.com/EscolaLMS/H5P-player)

Demo [React source files](https://github.com/EscolaLMS/h5p-laravel-demo/blob/main/resources/js/index.tsx), are great starting point for frontend tutorial

## Install

1. `composer require ulams/headless-h5p`
2. `php artisan migrate`
3. `php artisan h5p:storage-link` see below
4. `php artisan db:seed --class="Ulams\HeadlessH5P\Database\Seeders\PermissionTableSeeder"` see below

### Storage links

You need to publish many of files to be available as public link.

`php artisan h5p:storage-link` which creates a symbolic link from `storage/app/h5` and `vendor/h5p/h5p-core` and `vendor/h5p/h5p-editor` to be accessible to public, as follows

```
public_path('h5p') => storage_path('app/h5p'),
public_path('h5p-core') => base_path().'vendor/h5p/h5p-core',
public_path('h5p-editor') => base_path().'vendor/h5p/h5p-editor',
```

### Cors

All the endpoints need to be accessible from other domains, so [CORS](https://laravel.com/docs/8.x/routing#cors) must be properly set.

Except of endpoints assets must expose CORS headers as well. You achieve that by setting `Apache/Nginx/Caddy/Whatever` settings - below is example for Nginx for wildcard global access.

```
location ~* \.(eot|ttf|woff|woff2|jpg|jpeg|gif|png|wav|mp3|mp4|mov|ogg|webv)$ {
    add_header Access-Control-Allow-Origin *;
}
```

### Authorization

Most of the endpoints require authorization, this is possible with Laravel passport

There is a [seeder](database/seeders/PermissionTableSeeder.php) to must be run in order to authorize

User model is taken from [Auth](https://github.com/EscolaLMS/Auth) package.

### Seeder

To seed content and library

```
php artisan db:seed --class="\Ulams\HeadlessH5P\Database\Seeders\ContentLibrarySeeder"
```

You can seed library and content with build-in seeders as command that are accessible with

- `php artisan h5p:seed` to add just libraries
- `php artisan h5p:seed --addContent` to add content with libraries

## Road map

- rewrite h5p core in a way like [luminare in typescript](https://github.com/lumieducation/lumi)

## Running test locally

run `./test.sh`
