# Templates para Criação de Recursos

Use estes templates como ponto de partida. Substitua `Resource`/`resource` pelo nome do seu recurso.

---

## 1. Migration

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

            $table->index('first_fk_id');
            $table->index('created_at');
        });

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

---

## 2. Enum

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

---

## 3. Cast

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

---

## 5. Factory

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
            'fk_id'       => ParentModel::factory(),
            'name'        => fake()->name(),
            'description' => fake()->sentence(),
            'value'       => fake()->numberBetween(1000, 50000),
            'status'      => ResourceStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ResourceStatus::Inactive->value,
        ]);
    }
}
```

---

## 6. Policy

### Staff Managed

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

### Granular

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ResourcePolicy
{
    public function viewAny(User $user): bool { ... }
    public function create(User $user): bool { ... }
    public function view(User $user, Resource $resource): bool { ... }
    public function update(User $user, Resource $resource): bool { ... }
    public function delete(User $user, Resource $resource): bool { ... }
    public function restore(User $user, Resource $resource): bool { ... }
}
```

---

## 7. Form Requests

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
        $resource = $this->route('resource');
        $actor = $this->user();

        return $resource instanceof Resource && $actor !== null && $actor->can('update', $resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'fk_id'       => ['sometimes', 'string', Rules::existsActive('parent_table', 'ulid')],
            'name'        => ['sometimes', 'string', 'max:255'],
            'value'       => ['sometimes', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
```

---

## 8. Service

### Simples (sem FK externa)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resource;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ResourceService
{
    public function create(array $attributes): Resource
    {
        return Resource::create($attributes);
    }

    public function delete(Resource $resource): void
    {
        $resource->delete();
    }

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

    public function update(array $attributes, Resource $resource): Resource
    {
        $resource->update($attributes);

        return $resource->load('relation');
    }
}
```

### Com FK via ULID (PublicId)

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
    public function create(array $attributes): Resource
    {
        return Resource::create($this->resolveForeignKeys($attributes));
    }

    public function update(array $attributes, Resource $resource): Resource
    {
        $resource->update($this->resolveForeignKeys($attributes));

        return $resource->load('parentRelation');
    }

    // ...delete, paginate, restore

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

---

## 9. Controller

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

    public function destroy(Resource $resource): Response
    {
        $this->authorize('delete', $resource);

        $this->resources->delete($resource);

        return response()->noContent();
    }

    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Resource::class);

        return ResourceResource::collection($this->resources->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    public function restore(Resource $resource): JsonResponse
    {
        $this->authorize('restore', $resource);

        return ApiResponse::item(new ResourceResource($this->resources->restore($resource)));
    }

    public function show(Resource $resource): JsonResponse
    {
        $this->authorize('view', $resource);

        return ApiResponse::item(new ResourceResource($resource->load('relation')));
    }

    public function store(StoreResourceRequest $request): JsonResponse
    {
        $this->authorize('create', Resource::class);

        $resource = $this->resources->create($request->validated());

        return ApiResponse::item(new ResourceResource($resource->load('relation')), status: Response::HTTP_CREATED);
    }

    public function update(UpdateResourceRequest $request, Resource $resource): JsonResponse
    {
        $this->authorize('update', $resource);

        $resource = $this->resources->update($request->validated(), $resource);

        return ApiResponse::item(new ResourceResource($resource));
    }
}
```

---

## 10. Resource (API Resource)

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

---

## 11. Routes

```php
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

---

## 12. Test

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
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/resources', Resource::factory()->definition())
            ->assertForbidden();
    }
}
```
