# Ulams LMS aka Ulams Headless LMS 

Laravel Headless LMS REST API.


## Documentation

Most of the documentation is hosted at [docs.ulams.app](https://docs.ulams.app/).

## Configuration

Please not that API is based on Laravel but it's configured by environmental variables
Please don't create or edit any `.env` file but use environmental variables with `LARAVEL_` prefix, see [docker-compose.yml](docker-compose.yml) for reference.

As we want this package to be stateless and easy to scale all configuration is stored either in database or in environmental variables.

See [docs][docs] for more details.

Application is designed to be stateless - it is controlled by [environmental variables](docs/enviromental-variables.md)

## Installation from source code

If you want to use the software consider using our docker images or using [Create-LMS-App/](https://github.com/EscolaLMS/Create-LMS-App/) helper.

You should consider install with our [installation package](https://docs.ulams.app/getting-started/guide.html).

To install default docker environment either clone this repo or use

See [docs/multidomain.md][docs/multidomain.md] for more details how to quick start.

### Quick start

1. Clone this repo
2. `docker compose up -d`

## Demo

[https://api-stage.ulams.app/api/documentation](https://api-stage.ulams.app/api/documentation)

This is fully working demo. **Note** that content is regenerated every day - it's a seeder that is not persistent, every day database and files are cleared and rebuilt from skratch.

## Packages

This API consists of Laravel and multiple packages.

List of all packages is available at [packagist.org/?query=escolalms](https://packagist.org/?query=escolalms).

## Tests

Just run `phpunit` to test all the packages.

Summary code coverage from all the packages:


## Test

There are hundreds of tests in the packages and they are divided into:

### Integration packages test

Each packge contains their own php integration test this repo runs all of the

To run use `./vendor/bin/phpunit`

## Tasks

See [makefile](makefile) for all available devops tasks

## License

