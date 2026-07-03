# atelie (Boilerplate API) — Project Instructions

## General
- **Framework**: Laravel 13.x, PHP 8.3+ (platform: 8.5.7)
- **Domain**: Ceramics atelier management system
- **Architecture**: Controllers → Services pattern (thin controllers, business logic in Services)
- **API**: RESTful JSON API under `/api/v1/`
- **Server**: FrankenPHP + Laravel Octane
- **Database**: PostgreSQL (prod), SQLite `:memory:` (testing)
- **Cache**: Redis with taggable store support
- **Storage**: MinIO (S3-compatible) for avatars/logos
- **Auth**: Laravel Sanctum (token-based + httpOnly cookie)
- **All files use `declare(strict_types=1)`**

## Execution Principles
- **Priorities**: security > LGPD > performance > code quality > speed
- **Precision**: maximum care, no loose ends. Nothing that needs to be fixed later.
- **Verify**: always check against the real code before stating or modifying anything
- **Dubious annotations**: every assertive docblock/comment (that claims what code DOES) is suspect until verified
- **Ask**: when a design decision is needed, use selectable options for the user

## Coding Conventions
- PHP 8 attributes (`#[Fillable]`, `#[Hidden]`, `#[Group]`) over docblock annotations where possible
- All monetary values stored as **integer cents** (never float/double for currency)
- Type hints on all properties, parameters, and return types
- Use `@return` PHPDoc with generic type annotations for relations: `@return HasMany<Piece, $this>`
- Use `@property` PHPDoc on models for IDE autocompletion
- `@var list<string>` for string arrays in `$fillable` properties
- Use `Closure` type hint for callable parameters
- `@param Closure(Request): Response $next` pattern for middleware
- Use `Closure` for inline validation rules in Form Requests

## Response Envelope
- **Success**: `ApiResponse::item($data, $message, $status)` → `{ data, message }` JSON
- **Collections**: `Resource::collection($paginator)->additional(['message' => null])`
- **Errors**: `ProblemDetail::response($status, $detail, $extensions)` → RFC 9457 `application/problem+json`
- **No content**: `response()->noContent()`
- API Resources expose ULID as `id` field

## Models
- **Namespace**: `App\Models`
- Route-model binding uses ULID (`getRouteKeyName()` returns `'ulid'`)
- `HasUlidRouteKey` trait generates ULID automatically on `creating`
- **SoftDeletes** — padrão para todo recurso; exceções raras (ex.: Setting, Impersonation)
- **OwenIt Auditing** (`Auditable` interface + `AuditableTrait`) on financial/important models
- Models with caching use `InvalidatesQueryCache` trait
- `protected $fillable` as `list<string>` type (PHPStan max compliance)
- Custom `casts()` method returning `array<string, string>`
- Default values via `protected $attributes = [...]`
- Cascade soft-delete: `deleting`/`restoring` booted events propagate to children

### Key Models
| Model | Auditable | SoftDeletes | Caching |
|-------|-----------|-------------|---------|
| User | Yes | Yes | Yes |
| Piece | Yes | Yes | No |
| Bill | Yes | Yes | No |
| Enrollment | Yes | Yes | No |
| TuitionFee | Yes | Yes | No |
| PieceCharge | Yes | Yes | No |
| CommissionOrder | Yes | Yes | No |
| MaterialPurchase | Yes | Yes | No |
| Setting | Yes | No | No (singleton) |
| Impersonation | No | No | No |
| Clay/Glaze/Customer/etc. | No | Yes | No |

### Special Model Rules
- **Piece**: Composition and pricing fields are **immutable** after creation (LogicException on update)
- **Setting**: Singleton pattern via `Setting::current()` — always `firstOrFail()`
- **User**: `password` cast as `'hashed'`; generates ULID on create; route binding by ULID
- **MaterialPurchase**: Polymorphic relations via custom morph maps (`material_type`, `supplier_type`)

## Enums
- **Namespace**: `App\Enums`
- PHP 8.1 backed enums (string or int)
- Each has `values(): array` static method
- Used in model casts for type-safe attribute access

| Enum | Type | Values |
|------|------|--------|
| UserRole | string | `user`, `admin`, `master` |
| OrderStatus | string | `pending`, `in_production`, `ready`, `delivered` |
| PaymentMethod | string | `cash`, `pix`, `credit_card`, `debit_card`, `bank_slip`, `bank_transfer` |
| PieceKind | string | `commission` (sold, includes margin), `student` (materials only) |
| DayOfWeek | int | 1=Sunday … 7=Saturday |
| Month | int | 1=January … 12=December |

### UserRole Methods
- `canViewOthers(): bool` — Admin and Master
- `canManageOthers(): bool` — Master only

## Controllers
- **Namespace**: `App\Http\Controllers`
- Base `Controller` uses `AuthorizesRequests`
- Constructor injection of Service classes
- Each method: authorize → call service → return response
- `#[Group('Name', weight: N)]` attribute for Scramble API doc grouping
- Route model binding receives model already resolved by ULID
- PHPDoc on every method serves as Scramble/OpenAPI description

## Services
- **Namespace**: `App\Services`
- Business logic lives here, not in controllers or models
- Services depend on each other via constructor injection
- Single Responsibility: one service per domain entity
- Key services: `PiecePricingService` (pure calculator), `BillingCycle` (pure date math)
- All monetary calculations use integer arithmetic

### Key Service Methods Pattern
- `create(array $attributes): Model` — often wraps in `DB::transaction()`
- `update(array $attributes, Model $model): Model`
- `delete(Model $model): void`
- `restore(Model $model): Model`
- `paginate(int $perPage, array $filters = []): LengthAwarePaginator`

## Form Requests
- **Namespace**: `App\Http\Requests\{Resource}\{Action}Request`
- `authorize()` method gates the request (policy check)
- `rules()` method returns validation array
- PHPDoc `@example` annotations on each rule for Scramble API docs
- Custom `Closure` rules for complex validation (e.g., `onlyMasterAssignsElevatedRole`)
- Uses `Rule::enum()`, `Rule::exists()`, custom `Rules::activeUser()`, `Rules::existsActive()`

## Custom Validation Rules
- **Namespace**: `App\Rules`
- `AfterDateTime`, `BeforeDateTime`
- `DueDateAfterReference`
- Helper: `App\Support\Rules` with `activeUser()` and `existsActive()` builders

## Policies
- **Namespace**: `App\Policies`
- `StaffManaged` trait for standard CRUD — all abilities gated by `$user->canViewUsers()`
- `UserPolicy` has granular checks (viewAny, create, view, update, delete, restore)
- Custom gates in `AppServiceProvider`: `viewPulse`, `viewReports`

## Middleware (in order applied)
| Middleware | Purpose |
|-----------|---------|
| `AssignRequestId` | X-Request-Id header + logging context |
| `SecurityHeaders` | CSP, X-Frame-Options, HSTS, etc. |
| `AuthenticateFromCookie` | Bridges httpOnly cookie → Authorization header |
| `VerifyCookieCsrfToken` | Double-submit CSRF for cookie-authenticated requests |
| `EnsureUserCanViewUsers` | Gates user listing/audit to admin/master |
| `RestrictImpersonatedSession` | Blocks identity-changing actions during impersonation |

## Routes
- All under `/api/v1/`
- `throttle:60,1` for health
- `throttle:auth` for login, password ops (5/min)
- `throttle:api` for everything else (60/min)
- Soft-deletable resources have `POST /{resource}/{id}/restore` with `->withTrashed()`
- Resources use `apiResource` for standard CRUD
- Auth routes: login, logout, user profile, password change, 2FA
- Impersonation: start/stop
- Reports: monthly summary

## Exceptions
- **Namespace**: `App\Exceptions`
- `ApiExceptionRenderer` handles all exceptions → RFC 9457 problem+json
- Custom handling: Validation (422), Auth (401), Authorization (403), ModelNotFound (404), HttpException
- Debug mode adds `debug.exception` and `debug.message` to 500 responses
- CSRF mismatch returns 419

## Support Classes
| Class | Purpose |
|-------|---------|
| `Money` | Safe numeric coercion to integer cents |
| `Pagination` | Bounds page size (default 15, max 100) |
| `BillingCycle` | Pure date math for monthly billing cycle |
| `QueryCache` | Tag-based read-query cache helpers |
| `PublicId` | Resolves ULID → internal auto-increment ID |
| `Rules` | Shared validation rule builders |

## Auth Flow
1. **Login**: `POST /api/v1/login` → returns `{ user, token, token_type }` + httpOnly cookie
2. **Auth methods**: Bearer token (header) or httpOnly cookie (browser)
3. **Two-factor**: TOTP via Google2FA, 8 recovery codes, anti-replay timestep tracking
4. **Impersonation**: Master creates time-boxed (30min) token for target user
5. **Token revocation**: On password change (keeps current), on logout, on anonymization
6. **Password reset**: Standard Laravel flow with queued notifications
7. **Set password**: Separate broker for onboarding (accounts created without password)

## Queue & Jobs
- **Default driver**: Redis (sync in testing)
- `ProcessAvatarUpload`: Transcodes avatar to 512px WebP off request lifecycle
- Automatic recurrent bill generation (`BillService::generateRecurrentForMonth`)
- Automatic tuition fee generation (`TuitionFeeService::generateForMonth`)
- All notifications implement `ShouldQueue` (queued)
- `ShouldQueueAfterCommit` for piece charge notifications (dispatch after DB commit)

## Notifications
- **Namespace**: `App\Notifications`
- All extend Laravel notification classes
- In-app (database) for internal notices
- Email for auth flows (verify email, password reset, set password)
- Piece charges use Portuguese (`'Uma cobrança de peça foi gerada para você.'`)
- Billing statements sent in Portuguese

## Support & Infrastructure Files
- `config/audit.php` — Custom `ImpersonatorResolver` tagged to audits
- `config/auth.php` — Token cookie, impersonation, set-password broker config
- `config/cors.php` — CORS with credentials support
- `config/cache.php` — Query cache config (`query.enabled`, `query.ttl`)
- `config/sanctum.php` — Stateful domains, token expiration (14 days)
- `config/filesystems.php` — MinIO private + public disks
- `config/scramble.php` — OpenAPI docs with Stoplight Elements UI
- `config/database.php` — Multiple DB connections including `pgsql_admin`
- `docker-compose.yml` — app, worker, scheduler, pulse, postgres, redis, minio, createbuckets

## Testing
- **PHPUnit 12.x** with `RefreshDatabase` trait
- `tests/Feature/` — API integration tests
- `tests/Unit/` — Pure logic tests (PricingService, BillingCycle, User)
- In-memory SQLite for testing
- Factory states: `UserFactory::admin()`, `UserFactory::master()`, `UserFactory::unverified()`
- Default password in tests: `'password'` (hashed via `Hash::make`)
- Use `Sanctum::actingAs($user)` for authenticated requests
- Problem details tests verify RFC 9457 compliance

## Composer Scripts
- `pint` — Code style fix
- `pint:test` — Code style dry-run
- `phpstan` — Static analysis (level max)
- `test` — Config clear + PHPUnit

## Key Business Domain Rules
1. **Piece pricing**: Commission = base_cost + materials + firing, with per-kg clay multiplier and profit margin. Student = materials + firing only (no base cost/margin)
2. **Billing cycle**: Tuition due day configurable, clamped to month length. Piece charges have grace days (20) before billing on next tuition
3. **Enrollment cascade**: Soft-deleting an enrollment cascades to tuition fees; restoring brings them back
4. **Supplier cascade**: Soft-deleting a clay/glaze supplier cascades to their materials
5. **Commission order**: Sale total = piece prices (or negotiated override) + shipping charged. Margin = sale total - production cost - shipping cost
6. **Anonymization (LGPD/GDPR)**: Redacts name, email, password, avatar, 2FA, roles, audit trail values
7. **Impression audit**: Every audit record tagged with `impersonator_id` when applicable

## Dependencies (notable)
- `barryvdh/laravel-dompdf` — PDF generation (billing statements)
- `intervention/image` — Avatar processing
- `owen-it/laravel-auditing` — Audit trail
- `pragmarx/google2fa` — Two-factor TOTP
- `dedoc/scramble` — OpenAPI/Swagger docs
- `laravel/octane` — FrankenPHP server
- `laravel/pulse` — Performance monitoring
- `laravel/telescope` — Dev debugging
- `league/flysystem-aws-s3-v3` — MinIO/S3 storage
- `symfony/mailjet-mailer` — Email delivery
