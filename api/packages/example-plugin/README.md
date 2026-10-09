# Example plugin

A minimal ulams module that shows the package pattern end to end. It is the worked example of
the documentation page "Extending ulams → A new API package"
(`front/docs-site/src/content/docs/extending/new-package.mdx`).

It is **not enabled**: its provider is not listed in `config/app.php`, so the application does
not load it. Its PSR-4 entries are in `api/composer.json`; it has no suite in `phpunit.xml`
(and so does not run in CI), and its tests run by path. The test case registers the provider
itself.

## What it does

| Endpoint | Auth | What it returns |
|---|---|---|
| `GET /api/example-plugin/hello?name=Ada` | none | `{ "greeting": "Hello from the example plugin, Ada", "user_id": null }` |
| `POST /api/admin/example-plugin/greetings` `{ "user_id": 5 }` | `auth:api` + permission `example-plugin_send-greeting` | the greeting sent; dispatches `Ulams\ExamplePlugin\Events\GreetingSent` |

- **Setting**: `ulams_example_plugin.greeting` (default from `EXAMPLE_PLUGIN_GREETING`) is
  registered with `AdministrableConfig::registerConfig` as public and editable, so it appears in
  `GET /api/config` and admins change it per tenant with `POST /api/admin/config`.
- **Permission**: `ExamplePluginPermissionEnum::SEND_GREETING`, given to the `admin` role by
  `ExamplePluginPermissionSeeder`.
- **Event**: `GreetingSent(User $user, string $greeting)`. Because the class is in the `Ulams\`
  namespace and carries a `User`, the notifications package stores it as a database
  notification of that user, and the templates package sends it on every channel that has a
  template registered for it.

## Layout

```
src/
  UlamsExamplePluginServiceProvider.php   config, routes, singletons
  Providers/SettingsServiceProvider.php   AdministrableConfig::registerConfig
  config.php, routes.php
  Enums/ExamplePluginPermissionEnum.php
  Events/GreetingSent.php
  Services/GreetingService.php (+ Contracts/)
  Http/Controllers/... (+ Swagger/ interfaces with the OpenAPI annotations)
  Http/Requests/Admin/SendGreetingRequest.php   authorize() checks the permission
  Http/Resources/GreetingResource.php
database/seeders/ExamplePluginPermissionSeeder.php
tests/   TestCase, API tests, tenant isolation test
```

## Enabling it

1. Add `Ulams\ExamplePlugin\UlamsExamplePluginServiceProvider::class` to the "Package Service
   Providers" in `api/config/app.php`.
2. Seed the permission, per tenant:
   `php artisan db:seed --class="Ulams\ExamplePlugin\Database\Seeders\ExamplePluginPermissionSeeder" --domain=<slug>.localhost`
3. Optional: add `base_path('packages/example-plugin/src')` to the annotation paths in
   `config/l5-swagger.php` to publish its endpoints in the OpenAPI document.

## Tests

```bash
docker compose -f api/docker-compose.yml exec api bash -c \
  "DB_HOST=postgres DB_DATABASE=test DB_USERNAME=default DB_PASSWORD=secret ./vendor/bin/phpunit packages/example-plugin/tests"
```
