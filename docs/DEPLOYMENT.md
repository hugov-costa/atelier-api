# Production deployment (API)

How to run `atelie` in production. For the full platform guide (front + API +
cookie/CORS interplay) see the front repo's `docs/DEPLOYMENT.md`; this document is the
API-only reference.

## 1. Configuration

1. `cp .env.production.example .env` (or inject via your secrets manager) and replace
   every `<placeholder>`.
2. Generate the app key once: `php artisan key:generate --force`.
3. Critical settings: `APP_ENV=production`, `APP_DEBUG=false`,
   `CORS_ALLOWED_ORIGINS=<real front origin>` (never `*`/`localhost`),
   `FRONTEND_URL=<real front url>`, `TELESCOPE_ENABLED=false`, Redis with a password,
   strong DB credentials, `CACHE_STORE=redis`, `QUERY_CACHE_ENABLED=true`.
4. The auth cookie is **forced `Secure` when `APP_ENV=production`** (`config/auth.php`),
   regardless of `AUTH_COOKIE_SECURE`. Set `AUTH_COOKIE_SAME_SITE`:
   - `lax` when the front is a subdomain of the API (same site);
   - `none` when they are on different registrable domains (cross-site; requires HTTPS).
5. The application timezone is **UTC** for storage. Business dates (due dates,
   billing cycles) use `America/Sao_Paulo` automatically via the `BusinessDate` helper.
   No timezone change is needed in `.env`.

## 2. Processes

The FrankenPHP image entrypoint runs `composer install`, `key:generate` (if missing),
`app:ensure-database` and `migrate --force` for the `app` role. Run these containers:

| Role | Command | Purpose |
| --- | --- | --- |
| `app` | `octane:start --server=frankenphp` | HTTP server |
| `worker` | `queue:work` | queued mail (verification, password reset), notifications |
| `scheduler` | `schedule:work` | daily chores + monthly bills/tuitions + reminders |
| `pulse` | `pulse:work` | performance monitoring dashboard |

**Scheduled tasks** (all handled by the `scheduler` container):
| Frequency | Command | What it does |
| --- | --- | --- |
| Daily | `audit:prune` | Purges old audit records |
| Daily | `impersonations:prune` | Cleans expired impersonation sessions |
| Daily | `accounts:anonymize-trashed` | Anonymises soft-deleted accounts past retention window (LGPD) |
| Daily at 08:00 | `notifications:send-billing-reminders` | Sends upcoming/overdue billing reminders |
| Monthly on 1st 02:00 | `bills:generate-recurrent` | Creates next month's recurrent bills |
| Monthly on 1st 02:00 | `tuitions:generate` | Generates next month's tuition fees |

> **Multiple replicas**: the entrypoint migrates on every `app` boot. With >1 replica, run
> migrations as a single release step and disable the automatic migrate to avoid races.

## 3. Managed infrastructure

- **PostgreSQL** — the user search relies on a `pg_trgm` GIN index (dedicated migration);
  ensure `CREATE EXTENSION pg_trgm` is allowed (or pre-create it).
- **Redis** — cache (incl. the query cache layer with tag-based invalidation for 16 models),
  session and queue. Require auth. The cache store must be set to `redis` (`CACHE_STORE=redis`).
- **Object storage** — create the `public` and `private` buckets (`createbuckets` container
  in `docker-compose.yml` does this automatically); serve `public` over HTTPS/CDN via
  `MINIO_PUBLIC_URL`.
- **Mail** — set the Mailjet credentials, otherwise verification/reset emails won't send.

## 4. Health endpoints

Two health probes are available:
| Endpoint | Type | What it checks |
|----------|------|---------------|
| `/up` | **Liveness** | PHP process is running (FrankenPHP built-in) |
| `/api/v1/health` | **Readiness** | Database, cache and object storage are reachable |

The readiness endpoint returns `200` when all dependencies are healthy, `503` if any is
degraded (failure details are logged, never returned to the client).

## 5. Release commands

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan migrate --force   # single release step; see §2
```

After `config:cache`, `.env` changes only take effect after re-caching.

## 6. Checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generated.
- [ ] `CORS_ALLOWED_ORIGINS` / `FRONTEND_URL` = real front origin (no `localhost`/`*`).
- [ ] `AUTH_COOKIE_SAME_SITE` matches same-site vs cross-site; HTTPS everywhere.
- [ ] `CACHE_STORE=redis`, `QUERY_CACHE_ENABLED=true`; Redis password set.
- [ ] DB credentials from a secret store; `DB_CONNECTION=pgsql`.
- [ ] `TELESCOPE_ENABLED=false`; Pulse only if needed (admin-gated).
- [ ] `pg_trgm` extension available in PostgreSQL.
- [ ] MinIO `public`/`private` buckets created (`createbuckets` container or manually);
      `MINIO_PUBLIC_URL` configured.
- [ ] Mailjet credentials valid (verification, reset and billing emails).
- [ ] `worker`, `scheduler` and `pulse` containers running.
- [ ] Migrations run as a single release step (multi-replica).
- [ ] `config:cache` + `route:cache` + `event:cache`.
- [ ] First master user created via `POST /api/v1/users/master`.
- [ ] `pint --test`, `phpstan` (max) and `php artisan test` green; `composer audit` clean.
- [ ] DB + object-storage backups configured and tested.

## 7. Rollback

Redeploy the previous image. Prefer expand/contract migrations so the previous version
stays schema-compatible; only `migrate:rollback` when the migration is reversible and no
newer replica already depends on the new schema.
