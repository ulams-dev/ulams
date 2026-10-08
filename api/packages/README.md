# Vendored packages (imported from EscolaLMS)

This directory holds the source of every former `escolalms/*` composer package the API
used. They are now plain source directories owned by this repository and are no longer
installed by composer: `api/composer.json` has no `escolalms/*` requirement, and no
directory here has a `composer.json`.

## Layout

Each `packages/<name>/` keeps the upstream package layout:

- `src/`: runtime code. Namespaces were renamed from `EscolaLms\` to `Ulams\` when the project became ulams, e.g. `Ulams\Courses\`.
- `config/`, `database/` (migrations, seeders, factories, mocks), `resources/` (views,
  lang, js): loaded by each package's service provider through `__DIR__`-relative paths.
- `tests/`: the package's PHPUnit tests. The root `phpunit.xml` runs them as one test
  suite per package.
- `README.md`, `ADMIN.md`, `docs/`, `LICENSE`: upstream documentation and licence (MIT).

Files that only served standalone package development were not copied: `.git`, `.github`,
`env/` (docker setups), `composer.json`/`composer.lock`, `phpunit.xml`, `testbench.yaml`,
`phpstan.neon`, `infection.json`, `.php-cs-fixer.php`, `.editorconfig`, `.gitignore`,
`makefile`, `docker-compose.y*ml`, and IDE files.

## How it is wired

- **Autoload**: the PSR-4 maps from each package's former `composer.json` are merged into
  `api/composer.json`.
  - Runtime namespaces (`<NS>\`, `<NS>\Database\Seeders\`, `<NS>\Database\Factories\` or
    `Database\Factories\Ulams\<X>\Models\`) are in `autoload.psr-4`.
  - `<NS>\Tests\` maps are in `autoload-dev.psr-4`. The exceptions are
    `Ulams\Courses\Tests\` and `Ulams\Webinar\Tests\`, which stay in `autoload`
    because runtime code references them. For example, `Courses\Models\H5PUserProgress`
    uses `Courses\Tests\Models\User`.
  - The `App\Exceptions\` → `tests/Exceptions` dev maps of `cmi5`, `lrs` and `topic-types`
    are not merged, because they would shadow the application's exception handler. Those
    directories are listed in `exclude-from-classmap`.
- **Service providers and facades**: composer package discovery no longer sees these
  packages. Their providers are therefore listed explicitly in `config/app.php` under
  "Package Service Providers", in the order of the former discovery manifest. The two
  facade aliases from the payments package (`Payments` and `PaymentGateway`) are in
  `config/app.php` `aliases`. Third-party packages are still auto-discovered.
- **Third-party dependencies**: the packages' `require` entries are merged into
  `api/composer.json` `require`. The `replace` block for `symfony/polyfill-php*` came from
  the former `escolalms/payments` package and is kept so that resolution stays identical.
- **Paths**: anything that used to point at `vendor/escolalms/<name>` now points at
  `packages/<name>`. This covers `config/l5-swagger.php` annotation paths, `phpunit.xml`
  and `docker/envs/phpunit.xml.*`, the CI workflows, and `deptrac.*`.

- **Versions**: `packages/versions.json` lists the imported version of each module. `GET
  /api/core/packages` (`CoreController::packages`) used to read these from composer's
  `InstalledVersions` and now reads them from this file, so its response is unchanged.

## Adding a package or changing one

Edit the code here directly. A new module needs three things:

1. Its PSR-4 entries in `api/composer.json`, followed by `composer dump-autoload`.
2. Its provider in `config/app.php`.
3. A test suite in `phpunit.xml`.

## Packages written here

- `h5p` (`ulams/h5p`, `Ulams\H5P\`): Laravel side of the H5P service in `api/h5p`
  (Lumi h5p-nodejs-library). It replaced the vendored `headless-h5p` package
  (`escolalms/headless-h5p` 0.5.8). It reads the service's `h5p.contents` table read-only,
  exposes `GET /api/admin/h5p/contents` and `DELETE /api/admin/h5p/unused`, and talks to
  the service with `H5PServiceClientContract` (`X-Internal-Token`). Its migration creates
  `h5p.contents` with the service's DDL if it is missing (PostgreSQL only).

## Provenance

These were imported from the exact versions pinned in `api/composer.lock` at the time of
vendoring. The vendored dist was verified to be byte-identical to the git source at the
commits listed below.

| Directory | Former composer name | Upstream repository | Version | Commit |
|---|---|---|---|---|
| `assign-without-account` | `escolalms/assign-without-account` | https://github.com/EscolaLMS/Assign-Without-Account | 0.1.18 | `ad8d6ae4c35b4ad8c8b6da3e9d62be248b41e0d6` |
| `auth` | `escolalms/auth` | https://github.com/EscolaLMS/Auth | 0.2.41 | `5f83ca1514f361af4226e1c079b4438ef76b6cff` |
| `bookmarks_notes` | `escolalms/bookmarks_notes` | https://github.com/EscolaLMS/Bookmarks-Notes | 0.1.3 | `4072ba5bc1598286630eb752593367261abd1017` |
| `bulk-notifications` | `escolalms/bulk-notifications` | https://github.com/EscolaLMS/Bulk-Notifications | 0.0.7 | `2ad27cd7d3ae1232e296b2ca041f06dd2bdccd29` |
| `cart` | `escolalms/cart` | https://github.com/EscolaLMS/Cart | 0.4.81 | `813b5e9416e78b6ff19f022d73a9db0efa15866c` |
| `categories` | `escolalms/categories` | https://github.com/EscolaLMS/Categories | 0.1.43 | `7dd4de422ced768be3f34f58a3cc00554dc9cd92` |
| `cmi5` | `escolalms/cmi5` | https://github.com/EscolaLMS/cmi5 | 0.1.0 | `8f0a6450c1dc1aa523e95f453ffd7cb56c7a2d59` |
| `consultation-access` | `escolalms/consultation-access` | https://github.com/EscolaLMS/Consultation-Access | 0.1.3 | `a76ddd23bf1869cf85574acc582c233274f8a8ee` |
| `consultations` | `escolalms/consultations` | https://github.com/EscolaLMS/Consultations | 0.3.11 | `1a87ba5527673415ef315c50fce31c16204caad2` |
| `core` | `escolalms/core` | https://github.com/EscolaLMS/Core | 1.3.15 | `4d4b31fed685bc05088eb5730becda71b82b26aa` |
| `course-access` | `escolalms/course-access` | https://github.com/EscolaLMS/Course-Access | 0.1.2 | `78e1235db0a924c06e163f6cdac9f1186abcd38c` |
| `courses` | `escolalms/courses` | https://github.com/EscolaLMS/Courses | 0.4.45 | `f34010128af6c282f758750afd18e71a5f429327` |
| `courses-import-export` | `escolalms/courses-import-export` | https://github.com/EscolaLMS/Courses-Import-Export | 0.1.24 | `03fddd163722e16cb3d5b4e8884d5f935c34850f` |
| `csv-users` | `escolalms/csv-users` | https://github.com/EscolaLMS/CSV-Users | 0.1.16 | `2c52b8bb7a9dac8435f1df8c4a353b2584366c6e` |
| `dictionaries` | `escolalms/dictionaries` | https://github.com/EscolaLMS/Dictionaries | 0.0.5 | `1961960fe3a1d7c3f6b6556d9df03d184e480cea` |
| `files` | `escolalms/files` | https://github.com/EscolaLMS/Files | 0.1.29 | `56adbd7217a4103652b0583e398e9ba2eec1581d` |
| `images` | `escolalms/images` | https://github.com/EscolaLMS/Images | 0.1.24 | `7919b7bd28f5403377922d673339711117d03e27` |
| `invoices` | `escolalms/invoices` | https://github.com/EscolaLMS/Invoices | 0.1.9 | `861e38a36ba403139b220dd987204e94d3426a20` |
| `jitsi` | `escolalms/jitsi` | https://github.com/EscolaLMS/Jitsi | 0.1.2 | `9a60bb6a02ee21b2dc00126b2220b88a50e43c06` |
| `lrs` | `escolalms/lrs` | https://github.com/EscolaLMS/LRS | 0.0.13 | `1a7c3061ebb7cc3fff7b98b3bae150d70e911bae` |
| `mailerlite` | `escolalms/mailerlite` | https://github.com/EscolaLMS/MailerLite | 0.4.2 | `d795ff1d13742a1c2b72920e5e0e217bfe68be8e` |
| `mattermost` | `escolalms/mattermost` | https://github.com/EscolaLMS/Mattermost | 0.1.6 | `3581d3824d49603836078681ee1c1140e2649375` |
| `model-fields` | `escolalms/model-fields` | https://github.com/EscolaLMS/model-fields | 0.1.1 | `e1fb81a83a15d3d3aded8c56c4fbeff827a4f10d` |
| `notifications` | `escolalms/notifications` | https://github.com/EscolaLMS/Notifications | 0.3.2 | `ad14133686ad53515d16c8ab4bcc3b8fc0bef21c` |
| `pages` | `escolalms/pages` | https://github.com/EscolaLMS/pages | 0.1.11 | `a2e1fd5c5e9f939c90e3cf8c9ab101aa4e1b19bf` |
| `payments` | `escolalms/payments` | https://github.com/EscolaLMS/payments | 0.2.20 | `c88c56ff661a4e26c31574d15a381ffc8a3b498d` |
| `pencil-spaces` | `escolalms/pencil-spaces` | https://github.com/EscolaLMS/Pencil-Spaces | 0.0.3 | `e8c86769386248cde2e27d31eb09855fc421ec33` |
| `permissions` | `escolalms/permissions` | https://github.com/EscolaLMS/Permissions | 0.1.11 | `e64a528c83c111f7508393124566919ea53d942d` |
| `przelewy24-php` | `escolalms/przelewy24-php` | https://github.com/EscolaLMS/przelewy24-php | 0.1.0 | `c7c09b9a5c9aa009f30edc473c3fec7786a54cf1` |
| `questionnaire` | `escolalms/questionnaire` | https://github.com/EscolaLMS/Questionnaire | 0.2.26 | `05942c2fe089f51a582ee1a14d2a027d6ef56327` |
| `reports` | `escolalms/reports` | https://github.com/EscolaLMS/Reports | 0.1.49 | `a33e7028289a0497fe991ae3740654f416ca705e` |
| `scorm` | `escolalms/scorm` | https://github.com/EscolaLMS/Scorm | 0.3.1 | `13a15e7dc1547b8c60f5c45f6fa066532e77fe5f` |
| `settings` | `escolalms/settings` | https://github.com/EscolaLMS/settings | 0.2.6 | `55ed94f4d15fb37a91ce51b05877f5ea494193bc` |
| `stationary-events` | `escolalms/stationary-events` | https://github.com/EscolaLMS/Stationary-Events | 0.1.11 | `3111201383ae9ea68b2eaa19185bbf7e7a025c9c` |
| `tags` | `escolalms/tags` | https://github.com/EscolaLMS/Tags | 0.1.22 | `8dde49c276e32bf9136a09f825825e36fec26050` |
| `tasks` | `escolalms/tasks` | https://github.com/EscolaLMS/Tasks | 0.1.1 | `6a828065495b03c6c6faa69a1c513c328e52cdc7` |
| `templates` | `escolalms/templates` | https://github.com/EscolaLMS/Templates | 0.2.37 | `17bbe6ae875952384cc678ed691c989137f52753` |
| `templates-email` | `escolalms/templates-email` | https://github.com/EscolaLMS/Templates-Email | 0.1.69 | `2703752ea899e1fd5886506db7af48c1693b4230` |
| `templates-pdf` | `escolalms/templates-pdf` | https://github.com/EscolaLMS/Templates-PDF | 0.1.24 | `1e1a8cde95f34274ee9a473652acc54ef06ba5f8` |
| `templates-sms` | `escolalms/templates-sms` | https://github.com/EscolaLMS/Templates-SMS | 0.1.12 | `f99ca932049529142535857b6c3eb3d6844cfb83` |
| `topic-type-gift` | `escolalms/topic-type-gift` | https://github.com/EscolaLMS/Topic-Type-GIFT | 0.0.28 | `42e898b91e93c691e58304a0aae88372a6c36b25` |
| `topic-type-project` | `escolalms/topic-type-project` | https://github.com/EscolaLMS/Topic-Type-Project | 0.1.2 | `3059eb2f1a485f81540ce4e342e13b5dd540727f` |
| `topic-types` | `escolalms/topic-types` | https://github.com/EscolaLMS/topic-types | 0.2.53 | `eb24fad04a6364ef259d0709316a4f9e293c00ab` |
| `translations` | `escolalms/translations` | https://github.com/EscolaLMS/Translations | 0.1.1 | `f1fb321bc36de0d12a1afed7d53aadeaaa8cecb4` |
| `video` | `escolalms/video` | https://github.com/EscolaLMS/Video | 0.0.23 | `6b2810861f8a1e438ebc08c98ea9aa5977a0076f` |
| `vouchers` | `escolalms/vouchers` | https://github.com/EscolaLMS/Vouchers | 0.1.22 | `31a9b0cd1f30860be2276ae89abe9e1f67b968e9` |
| `webinar` | `escolalms/webinar` | https://github.com/EscolaLMS/Webinar | 0.1.44 | `d8fa7b5891876b8f432c50bb95514c7c3d2800b5` |
| `youtube` | `escolalms/youtube` | https://github.com/EscolaLMS/Youtube | 0.1.6 | `8d710f3d88c4beb558d9a5e283741f7d39acc444` |
