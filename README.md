# atelier

API-only service built with **Laravel 13** on **PHP 8.5**, served by **FrankenPHP + Laravel Octane**, backed by **PostgreSQL 18** and **Redis** (cache + queues), with outbound email delivered through **Mailjet** and object storage on **MinIO** (S3-compatible).

It is meant as a hardened, well-structured **boilerplate**: token + cookie authentication, email verification, password reset, optional two-factor, model auditing, standardized responses, OpenAPI docs and a strict quality gate are wired in out of the box.

The entire toolchain runs through Docker — no local PHP, Composer or Node is required.

## Stack

| Concern         | Choice                                       |
| --------------- | -------------------------------------------- |
| Runtime         | FrankenPHP + Octane (PHP 8.5)                |
| Framework       | Laravel 13 (API only)                        |
| Database        | PostgreSQL 18                                |
| Cache & queues  | Redis (`phpredis`)                           |
| Auth            | Laravel Sanctum (Bearer token + httpOnly cookie) |
| Two-factor      | TOTP (`pragmarx/google2fa`)                  |
| Auditing        | `owen-it/laravel-auditing`                   |
| Object storage  | MinIO via the S3 driver                      |
| API docs        | Scramble (OpenAPI)                           |
| Mail            | Mailjet (Symfony Mailjet transport)          |
| Observability   | Pulse (prod), Telescope (local), request-id correlation |
| Quality gate    | Pint, PHPStan (max), PHPUnit, CaptainHook    |

## Services

- **app** — FrankenPHP/Octane HTTP server on `http://localhost:8000`.
- **worker** — dedicated `queue:work` consumer for the Redis queue.
- **scheduler** — runs `schedule:work` (e.g. the daily audit pruning).
- **pulse** — runs `pulse:work`, draining the Redis ingest stream into Pulse storage.
- **postgres** — PostgreSQL 18.
- **redis** — Redis for cache and queues.
- **minio** — S3-compatible object storage (API `:9000`, console `:9001`).
- **createbuckets** — one-shot job that creates the `private`/`public` buckets.

## Usage

```bash
docker compose build
docker compose up -d
```

On first start the **app** container bootstraps itself, each step guarded so it is skipped once complete:

1. Creates `.env` from `.env.example` (skipped if `.env` exists).
2. Generates `APP_KEY` (skipped if already set).
3. Creates the `atelier` database (skipped if it already exists).
4. Runs pending migrations (skipped when none are pending).
5. Installs the CaptainHook git hooks (skipped when there is no `.git`).

Health check: `curl http://localhost:8000/up`

### Seeding a first user

The database starts empty, registration always creates **non-admin** users, and `admin` is not mass-assignable — so you need a deliberate step to get an administrator (required for `/users`, the audit endpoints and the Pulse/Telescope dashboards outside `local`). Run the seeder:

```bash
docker compose exec app php artisan db:seed
```

It creates an admin (`test@example.com` / `password`) plus 10 sample users. To promote an existing account instead, flip the flag from Tinker (a query update bypasses the mass-assignment guard):

```bash
docker compose exec app php artisan tinker
>>> App\Models\User::where('email', 'you@example.com')->update(['admin' => true]);
```

## Architecture

- **Thin controllers** — controllers only validate (via FormRequests), delegate to a service and return a response. All business logic and database queries live in `app/Services` (`AuthService`, `PasswordResetService`, `EmailVerificationService`, `UserAuditService`, `TwoFactorService`).
- **Standardized responses**
  - Success (2xx): a predictable envelope `{ "data": ..., "message": ... }` built by `App\Http\Responses\ApiResponse`; list endpoints add pagination `meta`/`links`. Models are serialized through API Resources.
  - Errors: **RFC 9457 `application/problem+json` (`type`, `title`, `status`, `detail`, plus `errors` on validation) built by `App\Exceptions\ApiExceptionRenderer`. Stack traces and internal details are never leaked (debug details only appear when `APP_DEBUG=true`). This covers validation (422), 401, 403, 404, 405 and a masked 500 for any other error (including database errors).
- **Versioning** — every route is served under the `/api/v1` prefix.
- **Public identifiers** — users expose a **ULID** as their public `id` and route key (e.g. `/users/{ulid}/audits`); the auto-increment integer key stays internal, so resources are not enumerable.
- **Query caching** — read-heavy list queries are cached in Redis under a per-model tag (`InvalidatesQueryCache`). Invalidation is **scoped to columns that affect cached output**: a write only flushes the tag when it creates/deletes/restores a row or changes a display column (`User::queryCacheColumns()` — name, email, role, avatar, verification, 2FA state, soft-delete), so high-frequency, non-display writes (a login touch, a rotating 2FA time-step) no longer needlessly invalidate the whole list. The user listing uses it (the cache key includes the `search`/`sort`/`direction` filters); cursor pagination bypasses the cache. A safety TTL (`QUERY_CACHE_TTL`, default 1h) bounds staleness from writes that bypass Eloquent (raw SQL, bulk updates); set it to `0` to cache until invalidated. Caching needs a taggable store — when the active store can't tag (database/file), the layer (`App\Support\QueryCache`) **degrades to uncached reads instead of failing the request**. It is not real-time push — use broadcasting for that.
- **Security headers** — every API response carries hardening headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `X-Permitted-Cross-Domain-Policies: none`, a locked-down `Content-Security-Policy`, and HSTS over HTTPS) via the `SecurityHeaders` middleware.
- **Search & sort indexes** — the user search uses a leading-wildcard `LIKE`, which a plain B-tree index can't serve. A PostgreSQL-only migration creates `pg_trgm` GIN indexes on `users.name`/`users.email` so the search stays index-assisted at scale (skipped on non-PostgreSQL connections such as the SQLite test database). The default listing order is `created_at desc`, so `users.created_at` is indexed to avoid a filesort as the table grows.

## API

All endpoints are prefixed with `/api/v1`. Authentication uses Sanctum: a successful `login` returns a Bearer token in the body **and** sets it in an httpOnly cookie (see [Authentication](#authentication)). Accounts are created by staff (`POST /users`) rather than by public self-registration — see [Ceramics atelier domain](#ceramics-atelier-domain).

| Method | Endpoint                            | Auth        | Description                                   |
| ------ | ----------------------------------- | ----------- | --------------------------------------------- |
| GET    | `/health`                           | public      | Readiness probe (database, cache, storage)    |
| POST   | `/users/master`                     | public      | Bootstrap the first master (empty install only) |
| POST   | `/login`                            | public      | Authenticate (TOTP required if 2FA is on)     |
| POST   | `/set-password/request`             | public      | Email a set-password link (always 204)        |
| POST   | `/set-password/validate-token`      | public      | Validate a set-password token                 |
| POST   | `/set-password/confirm`             | public      | Set the first password using the token        |
| POST   | `/logout`                           | token       | Revoke the current token, clear the cookie    |
| GET    | `/user`                             | token       | The authenticated user                        |
| GET    | `/user/export`                      | token       | Download own personal data as JSON (LGPD/GDPR) |
| PUT    | `/user/password`                    | token       | Change own password (verifies current password) |
| DELETE | `/user`                             | token       | Erase own account — anonymises personal data + soft delete (confirms `password`; LGPD/GDPR) |
| POST   | `/forgot-password`                  | public      | Email a password reset link (always 204)      |
| POST   | `/reset-password`                   | public      | Reset password; revokes all of the user's tokens |
| GET    | `/email/verify/{id}/{hash}`         | token+signed| Verify the email address                      |
| POST   | `/email/verification-notification`  | token       | Resend the verification email                 |
| POST   | `/two-factor/enable`                | token       | Provision a 2FA secret, QR URL, recovery codes |
| POST   | `/two-factor/confirm`               | token       | Confirm and activate 2FA                       |
| POST   | `/two-factor/recovery-codes`        | token       | Regenerate recovery codes (2FA must be enabled) |
| DELETE | `/two-factor`                       | token       | Disable 2FA                                    |
| GET    | `/users/audits`                     | token+view  | List audits across all users (admin or master) |
| GET    | `/users/{user}/audits`              | token+view  | Audit trail of a single user (admin or master) |
| GET    | `/users`                            | token+view  | List users (admin or master) — `search`, `sort` (name/email/created_at), `direction`, `per_page`, and `paginator=cursor` |
| POST   | `/users`                            | token+staff | Create a passwordless account and email a set-password link (`role` only by master) |
| GET    | `/users/{user}`                     | token       | Show a user (self, admin or master)           |
| PATCH  | `/users/{user}`                     | token       | Update a user (self, or master for others; `role` only by master; changing your own email requires `current_password`) |
| DELETE | `/users/{user}`                     | token+master| Soft-delete a user, recoverable (master, not self) |
| POST   | `/users/{user}/erase`               | token+master| Erase a user's personal data on their behalf (LGPD/GDPR; master, not self) |
| POST   | `/users/{user}/restore`             | token+master| Restore a soft-deleted user (master)          |
| POST   | `/users/{user}/impersonate`         | token+master| Start impersonating a (non-master) user; requires `reason` |
| DELETE | `/impersonate`                      | token       | Stop the active impersonation session         |
| POST   | `/users/{user}/avatar`              | token       | Upload a profile image (self or master)       |
| DELETE | `/users/{user}/avatar`              | token       | Remove the profile image (self or master)     |

Interactive OpenAPI docs are served by Scramble at `http://localhost:8000/docs/api` (JSON at `/docs/api.json`). Endpoints are organised into groups, every operation and body/query field is described, and **bearer authentication is wired in**: `login`, copy the `token` from the response, click **Authorize**, paste it, and call protected endpoints straight from the docs. Public endpoints are documented without a security requirement. Access to the docs themselves is restricted outside `local` by Scramble's `RestrictedDocsAccess` middleware.

Two health endpoints exist: `/up` is the shallow **liveness** probe (the process is running), while `/api/v1/health` is the **readiness** probe that verifies the database, cache and object storage are reachable (returning `503` if any is degraded). Failure reasons are logged, never returned.

### Authentication

- **Browser clients**: `login` sets an **encrypted, httpOnly + Secure + SameSite** cookie (`access_token`) holding the Sanctum token. The `AuthenticateFromCookie` middleware bridges it into the `Authorization` header, so JavaScript never touches the token. Browsers must send credentials (`fetch(..., { credentials: 'include' })`); configure the frontend origin in `CORS_ALLOWED_ORIGINS`.
- **Mobile / server-to-server**: send the token from the response body as `Authorization: Bearer <token>`.
- **CSRF**: cookie-authenticated, state-changing requests are protected by a double-submit check (`VerifyCookieCsrfToken`). `login` also sets a readable CSRF cookie; clients echo it back in the `X-XSRF-TOKEN` header (axios does this automatically). When the cookie is issued as `Secure` (always in production), it carries the **`__Host-` prefix** (`__Host-XSRF-TOKEN`) — pinning it to this exact host with `Path=/` and no `Domain`, so a sibling subdomain cannot inject a forged token; plain-HTTP dev, where `Secure` is unavailable, falls back to the unprefixed `XSRF-TOKEN`. The middleware accepts either name and the SPA reads whichever is present. Requests carrying an explicit `Authorization: Bearer` header skip CSRF (not CSRF-able).
- **Token expiry**: issued Sanctum tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 14 days, matching the cookie lifetime), bounding the blast radius of a leaked token. The short-lived impersonation token keeps its own 30-minute `expires_at`, which is honoured in addition to this ceiling.
- When the frontend lives on a **different site** than the API, set `AUTH_COOKIE_SAME_SITE=none` (requires HTTPS / `AUTH_COOKIE_SECURE=true`).
- **Secure cookie in production**: `AUTH_COOKIE_SECURE` only applies outside production; when `APP_ENV=production` the auth cookie is always flagged `Secure` regardless of the env value (defense-in-depth so a copied local `.env` can't downgrade it). The browser also exposes the `X-Request-Id` response header (CORS) so the SPA can surface it for log correlation.
- **Password policy**: configured once via `Password::defaults()` and enforced everywhere a password is set (registration, password reset and password change) — at least 8 characters with upper- and lower-case letters, a number and a symbol.
- **Changing the password while logged in**: `PUT /user/password` takes `current_password` and the new password. It is verified in-band (no email round-trip — that flow is reserved for *forgotten* passwords) and, on success, every other token is revoked while the current one stays valid. Re-authentication (`current_password`) is likewise required when a user changes **their own** email; a master editing another account is not asked for it.
- **Step-up auth**: credential-downgrade actions require the current `password` even within an authenticated session — changing the password or own email (above), **disabling two-factor** and **regenerating recovery codes** (`ConfirmPasswordRequest`). Reading one's own data (`GET /user`, `GET /user/export`) does not, since it grants no new privilege.
- **Email verification is not enforced as an API gate** (no `verified` middleware): a registered-but-unverified user can use the API; the SPA surfaces an "unverified email" banner rather than blocking. This is a deliberate low-friction-onboarding choice — tighten it by adding the `verified` middleware to sensitive routes if a deployment needs confirmed email before write access.

### Roles & authorization

Three roles, stored in `users.role` (cast to the `App\Enums\UserRole` enum):

- **`user`** — may read and edit **only their own** account.
- **`admin`** — may additionally **read** other users and the audit trail (`/users`, `/users/{user}`, `/users/*/audits`), but cannot mutate other accounts.
- **`master`** — full management of other users (update, delete, restore), role assignment and impersonation. Telescope/Pulse dashboards are restricted to masters.

`UserPolicy` enforces this (`canViewUsers`/`canManageUsers`), and the `users.view` middleware guards the audit routes. Role changes go through `PATCH /users/{user}` and are master-only; the **last master cannot be demoted** (anti-lockout). New users default to `user`.

### Impersonation

A master can act as another (non-master) user for support via `POST /users/{user}/impersonate` with a required `reason`. A dedicated, time-boxed token (default 30 min, `IMPERSONATION_TTL_MINUTES`) is issued for the target and carried by a separate httpOnly `impersonate_token` cookie, so the master never loses their own session; `DELETE /impersonate` ends it. While impersonating, identity/credential actions are blocked (password, email-via-`current_password`, 2FA, account deletion, data export, user management). Every access is recorded in the `impersonations` table (impersonator, target, reason, IP, expiry) and each write performed during the session is stamped with the master's id on the audit record (`impersonator_id`, resolved by `App\Audit\ImpersonatorResolver`). The data subject can see this access in their own `GET /user/export` (`access_log`), satisfying LGPD transparency.

### Two-factor (optional, per user)

`POST /two-factor/enable` → store TOTP secret + recovery codes and return them (secret/`qr_code_url`/`recovery_codes`). `POST /two-factor/confirm` with a current TOTP code activates it. Once active, `login` requires a valid `code` (TOTP or a recovery code). Secrets and recovery codes are stored encrypted, hidden from serialization and excluded from audits. Disabling 2FA and regenerating recovery codes require the current `password` (step-up). **Replay protection**: the last consumed TOTP time-step is tracked (`two_factor_last_used_timestep`), so a code accepted at login cannot be replayed within its validity window.

### Auditing

The `User` model is audited (`created`/`updated`/`deleted`/`restored`) — soft deletes included. It is a **change log, not an access log**: reads (`retrieved`) and logins are never recorded, so its volume scales with write operations, not traffic. Sensitive fields (`password`, two-factor columns) are never audited. Records older than `AUDIT_RETENTION_DAYS` (default `180` ≈ 6 months) are **hard-deleted** daily by the `audit:prune` command (scheduler service); impersonation access records are pruned on the same window (`IMPERSONATION_RETENTION_DAYS`) by `impersonations:prune`. Events triggered from the console/queue are only audited when `AUDIT_CONSOLE=true`.

The retention window is a deliberate, documented LGPD choice: ~6 months balances accountability and incident investigation against data-minimisation (Art. 15/16), and aligns with the Marco Civil access-log floor (Lei 12.965/2014, Art. 15). Hard deletion is intentional — soft-deleting audit rows would keep the personal data alive and defeat minimisation. Data lawfully pruned under this policy is, correctly, absent from the LGPD export; within the window the export is complete.

#### Scaling the audit trail

Today only `User` is audited. As you add audited models, the single global `audits` table grows with **total audited writes across all models** — retention bounds the size to `rate × window`, not to a small number. Pruning therefore deletes in bounded batches (`--batch`, default 1000) rather than one statement, so a large backlog never becomes a long transaction (locks, WAL spikes, table bloat). When the trail outgrows batched pruning, reach for, in rough order:

1. **Queue the audit writes** — set `audit.queue.enable=true` (`AUDIT_QUEUE`) to move the INSERT off the request path and cut write contention/latency.
2. **Audit selectively** — only have high-value models `implements Auditable`; keep high-churn/transient models out, and never enable the `retrieved` event (it would log every read).
3. **Time-partition `audits`** (range on `created_at`) and drop old partitions — instant reclaim, no DELETE/vacuum pressure — the scalable replacement for time-based pruning.
4. **Archive to cold storage** before deletion if compliance needs history beyond the hot window.

### Account deletion & erasure

Two distinct operations, so the LGPD/GDPR right to erasure is fully operable while accidental removals stay recoverable:

- **Erasure** (`DELETE /user` by the data subject, or `POST /users/{user}/erase` by a master on their behalf) anonymises the identifying fields (name, email, password, 2FA, avatar — removed from storage), revokes tokens, then soft-deletes. The scrub runs `withoutAuditing`, so the old data is not copied into a fresh audit record. Anonymising (rather than hard-deleting) keeps audit/accountability rows referentially valid while no longer identifying the person. The erasure also **redacts the account's own historical audit records** (`AccountService::redactAuditTrail`), replacing the captured `name`/`email` in `old_values`/`new_values` with `[redacted]` so the prior identity does not survive in the trail until the retention prune.
- **Soft delete** (`DELETE /users/{user}` by a master) is a recoverable operational removal (`POST /users/{user}/restore`).
- So removals don't retain identifying data indefinitely, `accounts:anonymize-trashed` (scheduled daily) anonymises any account soft-deleted longer than `ACCOUNT_ANONYMIZE_TRASHED_DAYS` (default `30`); it runs the same audit-trail redaction.

The audit trail keeps the structural record of the change (event, timestamp, author, IP) for accountability, but the identifying values it captured are redacted at erasure rather than waiting for the retention prune.

The data-portability export (`GET /user/export`) is **streamed** (`response()->streamJson` over lazy/cursor queries), so a long-lived account with a large audit history is serialised incrementally instead of being loaded into memory at once.

### Mail & storage

```dotenv
MAIL_MAILER=mailjet
MAILJET_APIKEY=your-key
MAILJET_APISECRET=your-secret
MAILJET_MODE=api   # or "smtp"
```

Verification and password-reset notifications are **queued** (processed by the worker). Because the frontend is a separate app, their email links point at `FRONTEND_URL` (`/verify-email?verify_url=…` and `/reset-password?token=…&email=…`), not at the API — the frontend page reads those parameters and calls the API to complete the flow. Changing an account's email marks it **unverified** again and sends a fresh verification email. Storage exposes two disks, `minio_private` and `minio_public`, backed by the MinIO buckets created on startup.

Profile images: the request accepts `jpeg`/`png`/`webp` up to 2 MB. The raw upload is stashed on `minio_private` and the transcode is handed to the queued `ProcessAvatarUpload` job — scaling to fit 512×512 (via `intervention/image` on the GD extension) and re-encoding to WebP — so image processing never blocks a web worker (important under Octane). The final object is written to `minio_public` under a **random, unguessable key** (`avatars/{ulid}/{random}.webp`) rather than a key derived from the public ULID, so the URL cannot be guessed for another user; the previous object is removed on replace. `UserResource` exposes the resulting public URL as `avatar_url`.

## Ceramics atelier domain

On top of the boilerplate's identity/account features, this API manages a **ceramics atelier** (studio): students and staff, class scheduling, enrollments and monthly tuition, expenses, the materials catalogue (clays, glazes and their suppliers, piece categories, firing cycles) and the produced pieces with their computed prices.

The domain is layered onto the boilerplate **without loosening any of its guarantees** — thin controllers, FormRequest validation, service-layer business logic, API Resources, RFC 7807 errors, ULID public identifiers and the strict quality gate all apply unchanged.

### Public identifiers

Every domain resource exposes a **ULID** as its public `id` and route key (e.g. `/bills/{ulid}`), exactly like users; the auto-increment key stays internal and resources are not enumerable. Payloads reference related records by ULID too (`user_id`, `clay_supplier_id`, `user_ids: []`, …); the service layer resolves them to internal keys via `App\Support\PublicId`.

### Accounts: no self-registration

Public self-registration is intentionally **removed**. Instead:

- **Bootstrap** — on an empty installation, `POST /users/master` creates the first **master** with a password. It is rejected (`409`) once any account exists.
- **Staff-created accounts** — `POST /users` creates a person **without a password** and emails a set-password link (only a master may assign an elevated `role`; admins create students). The account then completes onboarding through the **set-password flow** (`/set-password/request`, `/set-password/validate-token`, `/set-password/confirm`). This flow uses a dedicated Laravel password broker (`set_password`) so its tokens are **hashed at rest, expiring and single-use**, and confirming it also marks the email verified. The link points at `FRONTEND_URL/set-password?token=…&email=…`.

### Authorization

Roles are unchanged (`user`, `admin`, `master`). The atelier back-office is **staff-only**: every domain ability is authorized by a per-model policy composed from `App\Policies\Concerns\StaffManaged` (`admin` or `master`). Students (`user`) hold accounts, attend classes and own enrollments/pieces, but have **no back-office access** — the same account model, a different role.

### Resources

All endpoints live under `/api/v1` and require a staff token. Every list endpoint is paginated (`per_page`, 1–100) and returns an API-Resource collection with `meta`/`links`.

| Resource | Endpoint | Notes |
| -------- | -------- | ----- |
| Settings | `GET`/`PUT /settings` | Singleton pricing & billing configuration |
| Bills | `apiResource /bills` | Expenses; `due_date` cannot precede its reference month; filter by `reference_year`/`reference_month`/`is_recurrent` |
| Clay suppliers / clays | `apiResource /clay-suppliers`, `/clays` | Clay priced per kilogram; optional `description` |
| Glaze suppliers / glazes | `apiResource /glaze-suppliers`, `/glazes` | Glaze priced per liter; optional `description` |
| Material purchases | `apiResource /material-purchases` | Procurement ledger (polymorphic clay/glaze + supplier); the **latest** receipt updates the material's current price (a backdated receipt never clobbers a newer one); filter by `status`/`material_type`/`supplier_id` |
| Piece categories | `apiResource /piece-categories` | Optional `available_until`; overrides the profit margin |
| Firing cycles | `apiResource /firing-cycles` | Cost charged per piece |
| Single classes | `apiResource /single-classes` | One-off classes; `end` after `start`; attendees by ULID |
| Recurrent classes | `apiResource /recurrent-classes` | Weekly slots; `end` after `start`; **no overlap** on the same weekday |
| Enrollments | `apiResource /enrollments` | Annual fee derived from settings (or exempt); filter by `annual_fee_is_paid`/`user_id` |
| Tuition fees | `apiResource /tuition-fees` | Amount & due date derived from settings; toggle paid/unpaid; filter by `status` |
| Pieces | `apiResource /pieces` | `kind` (commission/student); price & production cost **computed and snapshotted server-side** |
| Piece charges | `GET /piece-charges` (index/show), `PUT /piece-charges/{id}` | Student receivables raised automatically for student pieces; settle with tuition, or `is_paid` toggled manually |
| Customers | `apiResource /customers` | Reusable commission customers; deleting cascades (soft) to their orders |
| Commission orders | `apiResource /commission-orders` | Bundle commission pieces for a customer; status workflow; sale total derived (or overridden); realized margin |
| Student statement | `GET /students/{student}/statement` | Consolidated balance (tuition + annual + piece charges) with aging & history; staff **or the student** |
| Reports | `GET /reports/monthly` | Revenue/expenses/net, commission margin, per-category piece margins & activity (`year`, `month`) |

Monetary values are stored and returned as **integer cents**; multipliers and margins are decimals. Every domain resource is **soft-deleted** (recoverable) and exposes `POST /{resource}/{id}/restore` (staff-only): deleting a clay/glaze supplier cascades to its materials, deleting an enrollment cascades to its tuition fees, and deleting a piece cascades to its charge — all of which **restore in cascade** too. A soft-deleted supplier's `email`/`name` are freed for reuse via a **partial unique index** (`WHERE deleted_at IS NULL`) plus live-scoped validation.

### Business rules

- **Students** — accounts referenced as class attendees, enrollment holders or piece producers must be **active** (`is_active`) and not deleted. Admins may be students too (a staff member taking a class), so only the active flag is checked, never the role.
- **Enrollment annual fee** — never client-supplied. On create it is taken from `settings.annual_enrollment_cost` (or `0` when the student is exempt); toggling exemption zeroes it.
- **Tuition amount & due date** — a tuition fee **snapshots** its `amount` from `settings.tuition_monthly_cost` at creation (a later settings change never rewrites past charges), and its due date is the configured `tuition_fee_due_day_of_month` (clamped to the month length). A fee for a tuition-exempt enrollment is created already marked paid, for zero.
- **Annual fee payment** — marking an enrollment's `annual_fee_is_paid` stamps `annual_fee_paid_at` (and clearing it reverts), so the paid annual fee is attributed to the month it was received in the report.
- **Piece charges** — a **student** piece raises a `PieceCharge` (1:1) for its price (materials + firing); commission pieces don't (they're sold, not billed to a student). The charge is billed on a tuition cycle via `BillingCycle`: a piece made within `settings.piece_charge_billing_grace_days` (default 20) of its cycle's due date bills on the **next** tuition; one made later is pushed **one cycle further**. A charge is created before its target tuition exists, so it is **explicitly bound** (`tuition_fee_id`) to that tuition as soon as the tuition materializes (or at settlement, whichever comes first); paying the tuition **settles exactly its bound charges** and unpaying reverts only those — a charge settled by hand and never bound (e.g. a tuition-exempt student) is left untouched. Staff can settle a charge manually via `PUT /piece-charges/{id}` (`is_paid`) — the override for students with no tuition to bundle against or for corrections. When a student goes **inactive**, their still-unbound open charges are pulled forward to the next due date so they're collected before leaving. Charges are raised automatically and cascade with their piece.
- **Class integrity** — start/end ordering is enforced for both class types (reusing `AfterDateTime`/`BeforeDateTime` for datetimes and times), and recurrent classes may not overlap another slot on the same weekday (checked in `withValidator` against `RecurrentClassRepository`, so a day-only change is re-validated too).
- **Piece kind** — each piece is either a **commission** (made by the atelier, usually to order, and sold) or a **student**'s own class work. A commission carries the studio base cost and a profit margin; a student piece is charged **materials + firing only**, because the studio overhead is already covered by the student's tuition. `kind` is explicit (not derived from role — an admin may be a student too), and `user_id` records whoever made the piece (artist or student).
- **Piece pricing** — `PiecePricingService` (pure, unit-tested) computes both cost fields; the client never sets them:

  ```
  materials       = round(clay_price_per_kg * clay_amount_kg)
                  + round(glaze_price_per_l  * glaze_amount_l)   // 0 without glaze
                  + Σ firing_cycle prices

  student:     production_cost = price = materials              // no base cost, no margin
  commission:  production_cost = base_cost + materials
               price           = round(production_cost * clay_amount_multiplier^floor(clay_amount_kg) * margin)
               margin          = category_profit_margin ?? default_profit_margin
  ```

  > The source repository ships these tables with **no pricing implementation**, so this formula is a documented interpretation of the column comments, isolated in one service. With the shipped defaults (multiplier `1.0`, margin `1.0`) a commission's `price == production_cost`, so it is a safe no-op until an operator tunes the settings — adjust the service if the atelier's real costing differs.

  A piece is a **one-off physical object**, so it is an immutable historical record. At creation it **snapshots** the inputs that produced its numbers — `clay_unit_price`, `glaze_unit_price`, `base_cost`, `profit_margin` and the per-cycle firing prices — alongside the final `price`/`production_cost`. Re-pricing a material or changing settings never touches an existing piece, and there is no recalculation. `PUT /pieces/{piece}` may change **administrative fields only** (the name); to correct a composition, recreate the piece.

- **Material purchases** — a procurement ledger of clay/glaze receipts (polymorphic `material` + `supplier`, `unit_price`, `quantity`, `total_price`, an itemized `freight` portion, `lot`, `invoice_number`, `payment_method`, `purchase_date`, and a `receipt_date` set when the goods arrive). Marking a purchase **received** sets the material's current unit price to the receipt's `unit_price` — **freight is excluded** from the per-unit price (it stays part of `total_price`, so it still counts as a month expense). Existing pieces keep their snapshot, so only future costing changes.
- **Commission orders** — a `CommissionOrder` bundles **commission** pieces (only; a student piece is rejected) for a reusable `Customer`, with a status workflow (`pending → in_production → ready → delivered`) and a `paid_at`. The pieces' value is **derived** from their prices unless a negotiated `sale_total_override` wins; `shipping_charged` is added on top for the **sale total** the customer pays. The **realized margin** is that sale total minus the pieces' production cost minus `shipping_cost` (what the atelier paid to ship). Pieces are attached/detached from the order side (the piece endpoints stay read-only).
- **Student statement** — `GET /students/{student}/statement` consolidates a student's balance across tuition, annual fee and piece charges: `total_outstanding` plus `overdue`/`upcoming` aging, the itemized open items, and a recent payment history. Readable by staff **or the student themselves**.
- **Monthly report** — revenue sums paid tuition, annual fees, piece charges and **commission sales** (orders paid in the month, at their effective sale total); expenses sum the month's bills, material purchases **and the freight the atelier paid on commissions** (`bills_total`/`materials_total`/`commission_shipping`/`expenses.total`), so `net` reflects that shipping outflow; plus a `commission` block (paid orders' sales, production cost, shipping cost and realized margin), a `production.by_category` breakdown (piece count and price/cost/**margin** per category) and activity counts.
- **Auditing** — the financially significant models (`Setting`, `Bill`, `Enrollment`, `TuitionFee`, `Piece`, `PieceCharge`, `MaterialPurchase`, `CommissionOrder`) are audited via the existing `audits` table and retention/pruning; high-churn scheduling models are left out to bound volume (see [Scaling the audit trail](#scaling-the-audit-trail)).

### Automation (scheduler)

Two idempotent commands run at the **start of each month** (`monthlyOn(1)`), on the same scheduler service as the existing prunes, so members get the full month's notice before the due day:

- `tuitions:generate` — creates the month's tuition fee for every active, non-exempt enrollment that doesn't already have one (amount snapshotted from settings).
- `bills:generate-recurrent` — carries each recurrent bill (the latest per name) into the current month if one doesn't already exist.

Both are safe to re-run — they only create what is missing — so a missed run self-heals on the next invocation. They can also be run by hand for catch-up.

A third command runs **daily** (`dailyAt('08:00')`):

- `notifications:send-billing-reminders` — **5 days before** and **1 day after** a due date, re-sends the cycle's billing statement (tuition + pieces) to students with an outstanding balance, and reminds active students of an unpaid annual fee.

### Notifications

Students are notified of billing events on two channels, reusing the framework's notification system (all **queued**). The recipient is always the **student** who owns the charge.

| Event | Email | In-app |
|---|---|---|
| Enrollment created | ✔ | ✔ |
| Billing statement (generated / −5d / +1d) | ✔ (PDF) | ✔ |
| Annual fee due (−5d / +1d) | ✔ | ✔ |
| Piece charge created | — | ✔ |

The **billing statement** (`BillingStatementNotification`) is the monthly cobrança: `StatementService` builds a per-student, per-cycle statement (the tuition line plus every unpaid student piece for that cycle, each itemized from its snapshot — clay/glaze quantity × unit price, firing prices — down to the amount actually charged) and the email carries it as a **PDF invoice** (`barryvdh/laravel-dompdf`, Blade at `resources/views/invoices/statement.blade.php`) showing the atelier logo, student name, enrollment number, reference month, due date and the itemized totals, with a friendly explanatory body. It is sent at tuition **generation** and again at the **−5d / +1d** reminders; a student with **nothing billable** (all exemptions, no piece charges) receives no statement, while an **exempt-from-tuition student who still owes for pieces** gets a pieces-only statement. Inactive students with accelerated charges are still billed.

Exemptions are per-enrollment: `is_exempt_from_tuition_fee`, `is_exempt_from_annual_fee` and `is_exempt_from_piece_charges` (the last skips raising a `PieceCharge` for that student's pieces). The atelier logo lives in `settings.logo_path`, uploaded via `POST /settings/logo` (removed via `DELETE`), stored on public object storage under an extension **derived from the validated image MIME type** (never the client filename) and embedded in the PDF as a base64 data URI.

In-app notifications use the `database` channel and are read through `GET /notifications` (with `?unread=true`), `POST /notifications/{id}/read` and `POST /notifications/read-all`, each scoped to the authenticated user. `PieceChargeCreated` is raised inside the piece-creation transaction, so it implements `ShouldQueueAfterCommit` to avoid a worker racing the commit (the queue runs with `after_commit` off). The annual-fee reminder needs a due date, so an enrollment carries `annual_fee_due_date` (defaulting to the enrollment date).

## Observability

Self-hosted out of the box — no external SaaS account is required. Because these tools can capture request/query payloads (potential PII), their master switches default to **off** (`TELESCOPE_ENABLED=false`, `PULSE_ENABLED=false`) and must be explicitly enabled per environment, on top of the access gates below.

- **Laravel Pulse** (production) — a health/performance dashboard at `http://localhost:8000/pulse` (slow requests, slow queries, slow jobs, queued throughput, exceptions, cache and outgoing-request stats). Recordings are written to a **Redis ingest stream** (`PULSE_INGEST_DRIVER=redis`) so request latency is never blocked by storage writes; the dedicated **pulse** service drains the stream into storage with `pulse:work`. Access is locked down by the `viewPulse` gate: allowed in `local`, and otherwise only for users with the `admin` flag.
- **Laravel Telescope** (local only) — deep request/query/job/mail/exception inspection at `http://localhost:8000/telescope`. It is installed as a dev dependency, excluded from package auto-discovery and registered **only** when `APP_ENV=local`, so it never loads in production. The `viewTelescope` gate mirrors Pulse (local, or `admin`).
- **Request correlation** — `AssignRequestId` (first middleware in the `api` group) assigns every request an `X-Request-Id` (honouring an inbound one if present), shares it into the log context so every log line for that request is correlated, and echoes it back on the response.

Both dashboards are Livewire pages served through the `web` middleware group; in production, place them behind your own session auth or infrastructure-level protection in addition to the gate. To plug in an external error tracker (e.g. Sentry), add its SDK and point its DSN at an env variable — the request-id log context will travel with it.

## Quality gate

All tooling runs inside the container — no local PHP or Composer is required.

| Command | Purpose |
| ------- | ------- |
| `docker compose exec app composer pint`    | Format the code (Laravel preset, aligned arrays). |
| `docker compose exec app composer phpstan` | Static analysis at the maximum level (1 GB memory). |
| `docker compose exec app composer test`    | Run the request tests against SQLite.              |

### Git hooks (CaptainHook)

Installed automatically when the **app** container boots; the hooks delegate execution into the running container, so the stack must be up when committing.

- **pre-commit** runs, stopping at the first failure: **Pint** (auto-fixes and re-stages the staged PHP files), **PHPStan** (max level), **PHPUnit**.
- **commit-msg** enforces **Conventional Commits** (lowercase type, subject ≤ 72 chars).

Tests run against an in-memory SQLite database configured in the dedicated `.env.testing`.

### Continuous integration

`.github/workflows/ci.yml` mirrors the gate on every push/PR: it builds the Docker image and runs `composer audit` (failing on known dependency CVEs), Pint (`--test`), PHPStan and PHPUnit inside the same container, so CI and local enforce exactly the same checks.

## Configuration reference

| Variable | Default | Purpose |
| -------- | ------- | ------- |
| `AUTH_COOKIE_NAME` | `access_token` | Name of the httpOnly token cookie |
| `AUTH_COOKIE_LIFETIME` | `20160` | Cookie lifetime in minutes (14 days) |
| `AUTH_COOKIE_SECURE` | `true` | Send the cookie only over HTTPS |
| `AUTH_COOKIE_SAME_SITE` | `lax` | `lax` for same-site, `none` for cross-site frontends |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:3000` | Comma-separated allowed origins (credentials enabled) |
| `FRONTEND_URL` | `http://localhost:3000` | Base URL the verification/reset/set-password emails link back to |
| `SET_PASSWORD_TOKEN_EXPIRATION` | `1440` | Set-password token lifetime in minutes (24 h) |
| `AUDIT_CONSOLE` | `false` | Audit events triggered from console/queue |
| `AUDIT_RETENTION_DAYS` | `180` | Days of audit history to keep (hard-deleted by `audit:prune`) |
| `IMPERSONATION_RETENTION_DAYS` | `180` | Days of impersonation access records to keep (`impersonations:prune`) |
| `QUERY_CACHE_ENABLED` | `true` | Cache read-heavy list queries |
| `QUERY_CACHE_TTL` | `3600` | Safety TTL in seconds (`0` = until invalidated) |
| `TELESCOPE_ENABLED` | `true` | Record Telescope entries (only loads when `APP_ENV=local`) |
| `PULSE_ENABLED` | `true` | Record Pulse metrics |
| `PULSE_INGEST_DRIVER` | `redis` | Where recordings are buffered (`redis` ingest needs the `pulse` service) |
| `PULSE_STORAGE_KEEP` | `7 days` | How long Pulse retains aggregated data |
| `API_VERSION` | `v1` | Version shown on the OpenAPI docs |
| `MINIO_ENDPOINT` | `http://minio:9000` | MinIO endpoint used by the S3 disks |
| `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY` | `atelier` / `atelier_secret` | MinIO credentials |
| `MINIO_PRIVATE_BUCKET` / `MINIO_PUBLIC_BUCKET` | `private` / `public` | Bucket names |

## Notes

- `.env` is created inside the bind-mounted project directory and persists across rebuilds, so `APP_KEY` stays stable.
- `vendor/` lives in a named volume seeded from the image. After changing dependencies, rebuild and reset the volume: `docker compose down -v && docker compose build && docker compose up -d`.
- Octane keeps the application in memory; after editing code that runs at boot, reload with `docker compose exec app php artisan octane:reload` or restart the `app` container.
