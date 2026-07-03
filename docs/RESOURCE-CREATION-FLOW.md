# Resource Creation Flow

> ⚡ **Este fluxo agora é uma skill!** Use `/create-resource` no chat ou peça para criar um recurso que eu sigo o roteiro automaticamente.
>
> A skill está em `.github/skills/create-resource/SKILL.md` com templates em `assets/templates.md`.
>
> O conteúdo abaixo é mantido como referência estática.

Como criar um novo recurso (CRUD) no atelier, baseado nos patterns existentes.

> **Nota**: Nem todo passo é obrigatório para todo recurso. Adapte conforme a necessidade — por exemplo, um recurso simples como `PieceCategory` não precisa de notificações ou cascading events. Use seu julgamento.

---

## Índice das Etapas

1. [Migration](#1-migration)
2. [Enum (opcional)](#2-enum-opcional)
3. [Cast (opcional)](#3-cast-opcional)
4. [Model](#4-model)
5. [Factory](#5-factory)
6. [Policy](#6-policy)
7. [Form Requests](#7-form-requests)
8. [Service](#8-service)
9. [Controller](#9-controller)
10. [Resource](#10-resource)
11. [Routes](#11-routes)
12. [Tests](#12-tests)

---

## 1. Migration

### Padrão de ordem dos campos

```
id
ulid
FKs (em ordem alfabética)
demais campos (em ordem alfabética)
created_at
softDeletes — padrão para todo recurso
updated_at — vem por último via ->timestamps()
```

### Template

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snake_case_plural_resource_name', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            // FKs em ordem alfabética
            $table->foreignId('first_fk_id')->constrained('first_table')->cascadeOnDelete();
            $table->foreignId('second_fk_id')->nullable()->constrained('second_table')->nullOnDelete();

            // Demais campos em ordem alfabética
            $table->string('description')->nullable();
            $table->string('name');
            $table->unsignedInteger('value')->comment('Amount in cents.');
            $table->string('status');

            $table->timestamps();
            $table->softDeletes();

            // Índices: FKs, colunas de filtro/ordenação, created_at
            $table->index('first_fk_id');
            $table->index('created_at');
        });

        // Partial unique index para evitar duplicatas em registros ativos
        DB::statement(
            'CREATE UNIQUE INDEX table_name_column_active_unique '.
            'ON table_name (column_a, column_b) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('snake_case_plural_resource_name');
    }
};
```

### Regras
- Postgres **não** cria índice em FK automaticamente com `constrained()` — adicione `$table->index(...)` explicitamente
- Use `unsignedInteger` para valores monetários em centavos
- Use `decimal` para quantidades com precisão (ex.: `decimal(6, 3)` para kg)
- Use `ulid()` com `->unique()` para chave pública não-enumerável
- Comente campos não-óbvios com `->comment('...')`

---

## 2. Enum (opcional)

Crie um **PHP 8.1 backed enum** quando o recurso tiver campos com conjunto fixo de valores (status, tipo, método, etc.).

### Template

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ResourceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
```

### Localização
`App\Enums\ResourceStatus`

### Uso no Model
```php
protected function casts(): array
{
    return [
        'status' => ResourceStatus::class,
    ];
}
```

---

## 3. Cast (opcional)

Crie um **custom cast** quando a lógica de serialização for mais complexa que um cast nativo do Laravel. Exemplo: `DateOnly` para datas sem componente de tempo.

### Template

```php
<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<Carbon, ...>
 */
class MyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // Transformar do banco para objeto PHP
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // Transformar de objeto PHP para valor do banco
    }
}
```

---

## 4. Model

### Template completo

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $fk_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $updated_at
 */
class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory, HasUlidRouteKey, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // ordem alfabética
        'description',
        'fk_id',
        'name',
        'status',
        'value',
    ];

    /**
     * Cascade soft-delete: se o recurso for pai de outros.
     */
    protected static function booted(): void
    {
        static::deleting(function (Resource $resource): void {
            if ($resource->isForceDeleting()) {
                return;
            }

            $resource->children()->get()->each(fn ($child) => $child->delete());
        });

        static::restoring(function (Resource $resource): void {
            Child::onlyTrashed()->where('resource_id', $resource->id)->get()
                ->each(fn ($child) => $child->restore());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fk_id'  => 'integer',
            'status' => ResourceStatus::class,
            'value'  => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ParentModel, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class);
    }

    /**
     * @return HasMany<ChildModel, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ChildModel::class);
    }
}
```

### Traits Disponíveis

| Trait | Quando usar |
|-------|-------------|
| `HasUlidRouteKey` | **Sempre** — gera ULID automático no `creating` |
| `SoftDeletes` | **Padrão** — obrigatório a menos que não faça sentido (ex.: Setting, Impersonation) |
| `AuditableTrait` | Quando o recurso é financeiro ou importante |
| `HasFactory` | **Sempre** — necessário para testes |
| `InvalidatesQueryCache` | Quando o recurso é listado com frequência |
| `Notifiable` | Quando o recurso recebe notificações |

### Regras
- `$fillable` como `list<string>` (PHPStan max compliance)
- `$casts()` retornando `array<string, string>`
- `@property` PHPDoc para todos os atributos + relations
- `@return` PHPDoc com generics nas relations: `@return HasMany<Child, $this>`

---

## 5. Factory

### Template

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ParentModel;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resource>
 */
class ResourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // FKs como callable para criar registros relacionados
            'fk_id'       => ParentModel::factory(),
            'name'        => fake()->name(),
            'description' => fake()->sentence(),
            'value'       => fake()->numberBetween(1000, 50000),
            'status'      => ResourceStatus::Active->value,
        ];
    }

    /**
     * Define estados adicionais conforme necessário.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ResourceStatus::Inactive->value,
        ]);
    }
}
```

### Regras
- Namespace: `Database\Factories`
- FK como `ParentModel::factory()` (cria o pai automaticamente)
- Use `fake()->numberBetween(...)` para valores numéricos

---

## 6. Policy

### Para recursos de back-office (staff apenas)

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\StaffManaged;

class ResourcePolicy
{
    use StaffManaged;
}
```

A trait `StaffManaged` já implementa `viewAny`, `view`, `create`, `update`, `delete`, `restore` — todos gateados por `$user->canViewUsers()` (admin/master).

### Para recursos com regras granulares (ex.: User)

Implemente cada método explicitamente:

```php
public function viewAny(User $user): bool { ... }
public function create(User $user): bool { ... }
public function view(User $user, Resource $resource): bool { ... }
public function update(User $user, Resource $resource): bool { ... }
public function delete(User $user, Resource $resource): bool { ... }
public function restore(User $user, Resource $resource): bool { ... }
```

---

## 7. Form Requests

Crie **um request por ação**: `StoreResourceRequest` e `UpdateResourceRequest`.

### StoreRequest

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Resource;

use App\Models\Resource;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Resource::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Description of the resource.
             *
             * @example Some description
             */
            'description' => ['nullable', 'string', 'max:1000'],

            /**
             * Public id of the parent resource.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'fk_id' => ['required', 'string', Rules::existsActive('parent_table', 'ulid')],

            /**
             * Resource name.
             *
             * @example My Resource
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Value in cents.
             *
             * @example 1500
             */
            'value' => ['required', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
```

### UpdateRequest

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Resource;

use App\Models\Resource;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resource = $this->route('resource'); // snake_case do model
        $actor = $this->user();

        return $resource instanceof Resource && $actor !== null && $actor->can('update', $resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Description of the resource.
             *
             * @example Some description
             */
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            /**
             * Public id of the parent resource.
             *
             * @example 01J9Z3K7QffEXAMPLEULID0000
             */
            'fk_id' => ['sometimes', 'string', Rules::existsActive('parent_table', 'ulid')],

            /**
             * Resource name.
             *
             * @example My Resource
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Value in cents.
             *
             * @example 1500
             */
            'value' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
```

### Regras
- PHPDoc `@example` em cada regra para documentação Scramble/OpenAPI
- Store usa `required`, Update usa `sometimes`
- `Rules::existsActive()` para validar ULIDs de tabelas com soft delete
- Campos com FK usam ULID (string), não ID interno

---

## 8. Service

### Template (recurso simples, sem cascade)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resource;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ResourceService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Resource
    {
        return Resource::create($attributes);
    }

    public function delete(Resource $resource): void
    {
        $resource->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Resource>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Resource::query()
            ->with('relation')
            ->orderBy('name')
            ->paginate(Pagination::perPage($perPage));
    }

    public function restore(Resource $resource): Resource
    {
        $resource->restore();

        return $resource->load('relation');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, Resource $resource): Resource
    {
        $resource->update($attributes);

        return $resource->load('relation');
    }
}
```

### Template (recurso com FK via ULID + PublicId)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ParentModel;
use App\Models\Resource;
use App\Support\Pagination;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ResourceService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Resource
    {
        return Resource::create($this->resolveForeignKeys($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, Resource $resource): Resource
    {
        $resource->update($this->resolveForeignKeys($attributes));

        return $resource->load('parentRelation');
    }

    // ...demais métodos (delete, paginate, restore)

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function resolveForeignKeys(array $attributes): array
    {
        if (isset($attributes['parent_ulid']) && is_string($attributes['parent_ulid'])) {
            $attributes['parent_id'] = PublicId::resolve(
                ParentModel::class,
                $attributes['parent_ulid'],
            );
            unset($attributes['parent_ulid']);
        }

        return $attributes;
    }
}
```

### Regras
- Métodos padrão: `create`, `update`, `delete`, `restore`, `paginate`
- Operações multi-tabela (ex.: criar + notificar) em `DB::transaction()`
- Services que dependem de outros services usam **constructor injection**
- Resolução de ULID → ID interno via `PublicId::resolve()` no Service
- `Paginate` usa `Pagination::perPage()` para limitar tamanho da página

---

## 9. Controller

### Template

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Resource\StoreResourceRequest;
use App\Http\Requests\Resource\UpdateResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Resource;
use App\Services\ResourceService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Resources', weight: N)]
class ResourceController extends Controller
{
    public function __construct(private ResourceService $resources) {}

    /**
     * Delete a resource
     */
    public function destroy(Resource $resource): Response
    {
        $this->authorize('delete', $resource);

        $this->resources->delete($resource);

        return response()->noContent();
    }

    /**
     * List resources
     *
     * Description of the list endpoint.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Resource::class);

        return ResourceResource::collection($this->resources->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a resource
     *
     * Restores a soft-deleted resource.
     */
    public function restore(Resource $resource): JsonResponse
    {
        $this->authorize('restore', $resource);

        return ApiResponse::item(new ResourceResource($this->resources->restore($resource)));
    }

    /**
     * Show a resource
     */
    public function show(Resource $resource): JsonResponse
    {
        $this->authorize('view', $resource);

        return ApiResponse::item(new ResourceResource($resource->load('relation')));
    }

    /**
     * Create a resource
     */
    public function store(StoreResourceRequest $request): JsonResponse
    {
        $this->authorize('create', Resource::class);

        $resource = $this->resources->create($request->validated());

        return ApiResponse::item(new ResourceResource($resource->load('relation')), status: Response::HTTP_CREATED);
    }

    /**
     * Update a resource
     */
    public function update(UpdateResourceRequest $request, Resource $resource): JsonResponse
    {
        $this->authorize('update', $resource);

        $resource = $this->resources->update($request->validated(), $resource);

        return ApiResponse::item(new ResourceResource($resource));
    }
}
```

### Regras
- Ordem dos métodos: `destroy → index → restore → show → store → update` (alfabética)
- Cada método: `authorize()` → chama service → retorna response
- PHPDoc em cada método serve como descrição OpenAPI/Scramble
- `#[Group]` para agrupar na documentação da API
- `#[QueryParameter]` para parâmetros de query na index
- `$resource->load('relation')` no show e store para eager loading

---

## 10. Resource

### Template

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resource
 */
class ResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->ulid,
            'created_at' => $this->created_at,
            'name'       => $this->name,
            'parent_id'  => $this->relation->ulid,
            'updated_at' => $this->updated_at,
            'value'      => $this->value,
        ];
    }
}
```

### Regras
- Expor ULID como `id`, nunca o ID interno
- Ordem alfabética dos campos
- `@mixin Resource` para autocomplete no IDE
- Relações usam `$this->relation->ulid` (já carregadas via eager loading no controller/service)

---

## 11. Routes

### Em `routes/api.php`

```php
use App\Http\Controllers\ResourceController;

// Dentro do grupo throttle:api, auth:sanctum, impersonation
Route::middleware(['auth:sanctum', 'impersonation'])->group(function () {
    $softDeletable = function (string $name, string $controller, string $parameter): void {
        Route::post("/{$name}/{{$parameter}}/restore", [$controller, 'restore'])
            ->withTrashed()
            ->name("{$name}.restore");
        Route::apiResource($name, $controller);
    };

    $softDeletable('resources', ResourceController::class, 'resource');
});
```

### Convenção de nomes
- Rota: `resources` (snake_case, plural)
- Parâmetro: `resource` (singular do model, snake_case)
- Name: `resources.restore`, `resources.index`, etc.
- Soft-deletable resources **sempre** usam o helper `$softDeletable` para incluir o restore

---

## 12. Tests

Crie testes **Feature** para os endpoints do recurso.

### Template

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_can_list_resources(): void
    {
        Resource::factory()->count(3)->create();

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/resources')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_resource(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = Resource::factory()->definition();

        $this->postJson('/api/v1/resources', $payload)
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name']]);
    }

    public function test_unauthorized_users_cannot_create(): void
    {
        $user = User::factory()->create(); // role = 'user'

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/resources', Resource::factory()->definition())
            ->assertForbidden();
    }

    // ... show, update, delete, restore, soft delete cascade
}
```

### Regras
- Use `Sanctum::actingAs()` para autenticação
- Use `User::factory()->admin()->create()` ou `->master()->create()`
- Teste permissões (student não pode acessar back-office)
- Teste soft delete e restore
- Teste validação (campos obrigatórios, tipos)
- Teste cascade (se aplicável)

---

## Fluxo Resumido (Checklist)

- [ ] **Migration** — criar tabela com ULID, FKs, índices, soft deletes (padrão)
- [ ] **Enum** (se houver campo de valor fixo)
- [ ] **Cast** (se houver serialização customizada)
- [ ] **Model** — fillable, casts, relations, traits, cascade events
- [ ] **Factory** — definition com estados
- [ ] **Policy** — StaffManaged ou custom
- [ ] **Form Requests** — Store + Update com @example + validação
- [ ] **Service** — create, update, delete, restore, paginate
- [ ] **Controller** — 7 métodos (destroy/index/restore/show/store/update)
- [ ] **Resource** — API Resource com ULID como id
- [ ] **Routes** — softDeletable helper em api.php
- [ ] **Tests** — Feature tests para CRUD + permissões
