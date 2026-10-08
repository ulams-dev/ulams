# Assign-Without-Account
[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/AssignWithoutAccount/)
[![codecov](https://codecov.io/gh/EscolaLMS/Assign-Without-Account/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/EscolaLMS/Assign-Without-Account)
[![Tests PHPUnit in environments](https://github.com/EscolaLMS/Assign-Without-Account/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Assign-Without-Account/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/assign-without-account)](https://packagist.org/packages/escolalms/assign-without-account)
[![downloads](https://img.shields.io/packagist/v/escolalms/assign-without-account)](https://packagist.org/packages/escolalms/assign-without-account)
[![downloads](https://img.shields.io/packagist/l/escolalms/assign-without-account)](https://packagist.org/packages/escolalms/assign-without-account)

## What does it do
The package is used to assign users without an account.

## Installing
- `composer require escolalms/assing-without-account`
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\AssignWithoutAccount\Database\Seeders\AssignWitoutAccountPermissionSeeder"`

## Requirements
You need to define email templates in the `Templates` package for `AssignToProduct` and `AssignToProductable` events.

## Database
1. `user-submissions` 

| id | email | status | created\_at | updated\_at | morphable\_type | morphable\_id |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | test@email.com | sent | 2022-04-15 07:36:12 | 2022-04-15 07:36:12 | App\\Models\\Course | 1 |
| 2 | test@email.com | accepted | 2022-04-15 07:36:30 | 2022-04-15 07:36:30 | EscolaLms\\Cart\\Models\\Product | 2 |

## Example
1. Create user submission like this:
```http request
POST /api/admin/user-submissions
```
```json
{
    "email": "test@email.com",
    "morphable_id": 2,
    "morphable_type": "EscolaLms\\Cart\\Models\\Product"
}
```
2. Create a new account.
3. The product is assigned to your account.


## Endpoints
All the endpoints are defined in swagger
[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/AssignWithoutAccount/)

## Tests
Run `./vendor/bin/phpunit` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

Test details 
[![codecov](https://codecov.io/gh/EscolaLMS/Assign-Without-Account/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/EscolaLMS/Assign-Without-Account)
![Tests PHPUnit in environments](https://github.com/EscolaLMS/Assign-Without-Account/actions/workflows/test.yml/badge.svg)

## Events
1. `AssignToProduct` - event dispatched after assigning user to product.
2. `AssignToProductable` - event dispatched after assigning user to productable model.

## Listeners
1. `AccountRegisteredListener` - listens to newly created accounts.

## How to use this on frontend.

### Admin panel
**List of user submissions**
![List of stationary events](docs/list.png "List of user submissions")


## Permissions
Permissions are defined in [seeder](database/seeders/AssignWithoutAccountPermissionSeeder.php)
