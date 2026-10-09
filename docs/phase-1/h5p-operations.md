# H5P service: operating it for many tenants

Notes for the docs site. Service: `api/h5p/README.md`; decision: ADR 0015.

## Production mounts

The development stack mounts the whole `api/` folder into the H5P service. In production give it
only what it needs:

1. On the API (platform `.env`): `H5P_SERVICE_CONFIG_DIR=/var/www/html/storage/h5p-service`.
2. Once: `php artisan ulams:h5p:export-config`. Creating, syncing (`ulams:tenant:sync-env`) and
   deleting tenants keep the directory current afterwards.
3. Start the service with the override:
   `docker compose -f docker-compose.yml -f h5p/compose.h5p.prod.yml up -d h5p`.

The directory holds, per tenant, only the database, bucket and URL settings and the Passport
**public** key. It still contains credentials: keep it readable by the service only.

## Connections

A tenant that has had no H5P request for 30 minutes (`TENANT_IDLE_EVICT_MS`) releases its database
pool and S3 client; its next request reconnects (a short delay, no visible error). Set it to `0` to
keep every tenant connected.

## Libraries

Installing, updating or deleting H5P libraries changes them for **all** tenants, so the service
accepts it only on the platform host (the platform admin). Tenants can still install content types
from the H5P Hub inside the editor (open question in the Phase 1 plan).

## Learner sessions

Access tokens live about 5 minutes. The admin and the React front refresh them in the background and
hand every new token to the H5P frame, which keeps saving the learner's state without reloading the
content. The Astro front plays H5P without a token: learners' answers count for progress, but the
content does not restore where they left off.
