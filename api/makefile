include .env
#export $(shell sed 's/=.*//' envfile)
export POSTGRES_DB=postgresql://$(DB_USERNAME):$(DB_PASSWORD)@127.0.0.1:$(DB_PORT)/$(DB_DATABASE)
export NOW_DB_PREFIX=$(date +\%Y-\%m-\%d-\%H:\%M:\%S)

test-phpunit:
	- docker compose exec api bash -c "./vendor/bin/phpunit"

bash:
	- docker compose exec api bash

bash-sudo:
	- docker compose exec api bash

migrate-fresh-quick:
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan migrate:fresh --seed"
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan passport:keys --force"
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan passport:client --personal --no-interaction"
	- docker compose exec api bash -c "cp storage/oauth-private.key vendor/orchestra/testbench-core/laravel/storage/oauth-private.key"

composer-update:
	- docker compose up -d api
	- docker compose exec api bash -c "XDEBUG_MODE=off composer self-update"
	- docker compose exec api bash -c "XDEBUG_MODE=off composer update"
## package manifest, caches, opcache and workers follow the new vendor/ (dev-reload.sh)
	- docker compose exec api bash /var/www/html/dev-reload.sh

restart_queue_cron:
	- docker compose restart ulams_queue_cron

update-composer-to-git:
	- git checkout develop
	- git pull
	- docker compose up -d api
	- docker compose exec api bash -c "XDEBUG_MODE=off composer update --no-scripts"
	- git add composer.lock
	- git commit -m "updating dependencies"
	- git push origin develop


swagger-generate:
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan l5-swagger:generate"

# H5P content types and demo content, imported by the H5P service (api/h5p, src/cli/seed.ts)
h5p-seed:
	- docker compose exec h5p node dist/cli/seed.js --samples

node-packages:
# install globally at /usr/bin/mjml
	- docker compose exec api bash -c "npm install -g mjml"

tinker:
# use CTRL+C to quit and CTRL+D to refresh tinker
	- docker compose exec api bash -c "while true; do php artisan tinker; done"

migrate-fresh: migrate-fresh-quick h5p-seed



refresh: composer-update migrate-fresh h5p-seed

docker-up:
	- docker compose up -d

switch-to-postgres:
	- cp docker/envs/.env.postgres.example .env
	- docker compose exec api bash -c "php artisan config:cache"

migrate-postgres: switch-to-postgres migrate-fresh-quick

test-phpunit-postgres: switch-to-postgres test-phpunit

test-fresh: migrate-fresh-quick test-phpunit

# creates a backup file into `data` folder
backup-postgres:
	- docker compose exec postgres bash -c "pg_dump --clean --dbname=$(POSTGRES_DB) -f /var/lib/postgresql/backups/backup-$(NOW_DB_PREFIX).sql"
	- docker compose exec postgres bash -c "cp /var/lib/postgresql/backups/backup-$(NOW_DB_PREFIX).sql  /var/lib/postgresql/backups/backup-latest.sql"

# imports database backup from data folder
# make import BACKUP_FILE=backup-2020-09-15-14:49:22.sql
# or
# make import BACKUP_FILE=backup-latest.sql
#import-postgres: backup-postgres

import-postgres:
	- docker compose exec postgres bash -c "psql --dbname=$(POSTGRES_DB) < /var/lib/postgresql/backups/$(BACKUP_FILE)"

init: docker-up switch-to-postgres composer-update migrate-fresh-quick

wait:
	- docker compose exec api bash -c "./wait.sh"

# Demo courses from front/docs/design/experiences.md (database/seeds/DemoCoursesSeeder.php).
# Re-running keeps existing demo courses; DEMO_REFRESH=1 rebuilds them.
DEMO_REFRESH ?= 0
EXPERIENCE ?= $(firstword $(subst ., ,$(DOMAIN)))

# platform tenant (api.localhost): all three courses
demo-seed:
	- docker compose exec -e DEMO_EXPERIENCE=all -e DEMO_REFRESH=$(DEMO_REFRESH) api bash -c "XDEBUG_MODE=off php artisan db:seed --class=DemoCoursesSeeder --force"

# one tenant with its own course: make demo-seed-tenant DOMAIN=coffee.localhost EXPERIENCE=coffee
demo-seed-tenant:
	- docker compose exec -e DEMO_EXPERIENCE=$(EXPERIENCE) -e DEMO_REFRESH=$(DEMO_REFRESH) api bash -c "XDEBUG_MODE=off php artisan db:seed --class=DemoCoursesSeeder --domain=$(DOMAIN) --force"
# tenant queue workers (queue.sh) do not serve the video long-job queue yet: process it once
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan queue:work redis-long-job --queue=queue-long-job --stop-when-empty --timeout=600 --domain=$(DOMAIN)"

# the three demo tenants
demo-seed-tenants:
	- $(MAKE) demo-seed-tenant DOMAIN=coffee.localhost EXPERIENCE=coffee
	- $(MAKE) demo-seed-tenant DOMAIN=oncall.localhost EXPERIENCE=oncall
	- $(MAKE) demo-seed-tenant DOMAIN=nightsky.localhost EXPERIENCE=nightsky

# Demo mode (packages/demo): login without password and an hourly reset, on the three demo
# tenants only. Writes DEMO_MODE=true to their env files (kept by ulams:tenant:sync-env).
demo-mode-on:
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan ulams:tenant:create coffee --demo=on"
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan ulams:tenant:create oncall --demo=on"
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan ulams:tenant:create nightsky --demo=on"

# reset one demo tenant now (the scheduler does it hourly): make demo-reset DOMAIN=coffee.localhost
demo-reset:
	- docker compose exec api bash -c "XDEBUG_MODE=off php artisan ulams:demo:reset --force --domain=$(DOMAIN)"

# Demo profile of the api container (php-profile.sh, docker-compose.demo.yml): production PHP
# settings, cached config/routes/events, vendor/ in a named volume. dev-up returns to the
# development profile (edits on the bind mount apply at once).
demo-up:
	- docker compose -f docker-compose.yml -f docker-compose.demo.yml up -d --force-recreate api

dev-up:
	- docker compose -f docker-compose.yml up -d --force-recreate api

# apply code/config edits without restarting the container (opcache, framework caches, workers)
dev-reload:
	- docker compose exec api bash /var/www/html/dev-reload.sh
