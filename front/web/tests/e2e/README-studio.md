# Course Builder end-to-end test

`studio.spec.ts` drives the whole M2.1 flow in Chromium on the **fake LLM driver** (synthetic
answers, no API key, no network): sign in → upload `coffee-brewing.md` → interview with "Decide for
me" → edit an objective and approve the outline → generation → approve the apply → the course exists
in the API → chat edit of a quiz question → approve → undo. axe (WCAG 2.2 AA) runs on every studio
screen. It is skipped unless `STUDIO_E2E=1`.

## Run it

1. An API with the fake driver and a prepared database (here: the CI test database), for example in
   a throwaway container from the API image, on port 18081:

   ```bash
   docker run -d --name ulams-studio-e2e-api --network ulams -p 18081:8000 \
     -v "$PWD/api:/var/www/html" -w /var/www/html \
     -e APP_ENV=local -e DB_HOST=postgres -e DB_DATABASE=<test db> -e DB_USERNAME=default -e DB_PASSWORD=secret \
     -e CACHE_DRIVER=array -e QUEUE_CONNECTION=sync -e FILESYSTEM_DRIVER=local -e SESSION_DRIVER=array \
     -e AI_DRIVER=fake -e AI_FAKE_MODE=synthetic -e COURSE_BUILDER_SSE_SECONDS=8 \
     -e TENANCY_PLATFORM_HOSTS=127.0.0.1,localhost,e2e.localhost -e PHP_CLI_SERVER_WORKERS=8 \
     --entrypoint php <api image> artisan serve --host=0.0.0.0 --port=8000 --no-reload
   ```

   `PHP_CLI_SERVER_WORKERS` matters: the event stream holds one worker while it is open.
   Create the author (a tutor) once:

   ```bash
   php artisan tinker --execute='$u = Ulams\Core\Models\User::firstOrCreate(["email" => "author@e2e.test"], ["first_name" => "Ada", "last_name" => "Author", "password" => bcrypt("e2e-secret"), "is_active" => true, "email_verified_at" => now()]); $u->syncRoles(["tutor"]);'
   ```

2. The web app pointing every `*.app.localhost` host at it:

   ```bash
   ULAMS_TENANT_HOSTS='{slug}.app.localhost=>http://{slug}.localhost:18081' ULAMS_WARM_TENANTS='' \
     yarn workspace @ulams/web dev --port 4329
   ```

3. The test:

   ```bash
   STUDIO_E2E=1 yarn workspace @ulams/web test:e2e tests/e2e/studio.spec.ts --project=desktop
   ```

`studio-sources.spec.ts` and `studio-updates.spec.ts` (Living Course) run the same way and in both
projects (`desktop` and `phone`, 360 px): `STUDIO_E2E=1 yarn workspace @ulams/web test:e2e
tests/e2e/studio-updates.spec.ts`. The updates spec builds a course from `coffee-brewing.v1.md`,
uploads `coffee-brewing.v2.md` through the sources API, then reviews and applies the proposal; it
needs the queue to run jobs (`QUEUE_CONNECTION=sync`).

`studio-living-course.spec.ts` is the end-to-end test of the whole Living Course (build and publish a
course, a learner completes a lesson and its quiz through the learner API, the author uploads
`coffee-brewing.v2.md`, reviews, applies, the learner sees the notices with progress unchanged, the audit
page verifies the chain). It also needs a verified learner:

```bash
php artisan tinker --execute='$u = Ulams\Core\Models\User::firstOrCreate(["email" => "learner@e2e.test"], ["first_name" => "Lee", "last_name" => "Learner", "password" => bcrypt("e2e-learner-secret"), "is_active" => true, "email_verified_at" => now()]); $u->syncRoles(["student"]);'
```

Extra API settings for this spec: `LIVING_COURSE_WEBHOOK_DEBOUNCE_SECONDS=0` and
`COURSE_BUILDER_SESSIONS_PER_DAY=500` (every project builds its own course; the default limit is 10 sessions per author
and day, counting the ones a refused connect removed). A learner's quiz attempt schedules a delayed job that ends it, and
the `sync` queue runs that job at once, which ends the attempt as soon as it starts. So run this spec with a real queue
(`QUEUE_CONNECTION=database` and one `php artisan queue:work database` in the API container) instead of `sync`, and run
it with `--workers=1` (the two projects would otherwise build two courses with the same author at once, and the API
allows one AI run at a time per author). Its second test fills the "Add web pages" form with a private address and
checks the readable refusal.

Variables: `STUDIO_LEARNER_EMAIL`, `STUDIO_LEARNER_PASSWORD`, `STUDIO_BASE_URL` (default `http://e2e.app.localhost:4329`), `STUDIO_API_URL`
(`http://127.0.0.1:18081`), `STUDIO_AUTHOR_EMAIL`, `STUDIO_AUTHOR_PASSWORD`. Each run creates one
course in the database.
