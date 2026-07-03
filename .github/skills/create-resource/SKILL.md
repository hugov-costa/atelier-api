---
name: create-resource
description: 'Criar um novo recurso CRUD no atelier seguindo os patterns do projeto. Usar quando solicitar: criar recurso, novo CRUD, criar modelo, nova entidade, adicionar resource, novo módulo.'
argument-hint: 'Nome do recurso (ex.: "Category", "Discount")'
---

# Criar Novo Recurso (CRUD)

Fluxo para criar um novo recurso RESTful no atelier, seguindo os patterns estabelecidos no projeto.

> **Importante**: Nem todo passo é obrigatório. Adapte conforme o recurso — ex.: `PieceCategory` não precisa de notificações ou cascade. Use julgamento.

---

## Procedimento

Para **cada etapa**, use o template correspondente em [assets/templates.md](./assets/templates.md). O checklist abaixo é a ordem de execução.

### 1. Migration
- Nome: `YYYY_MM_DD_HHMMSS_create_{snake_plural}_table.php`
- Field order: `id → ulid → FKs (alfabética) → campos (alfabética) → created_at → softDeletes → updated_at`
- Regras:
  - Postgres **não** cria índice em FK com `constrained()` — adicione `$table->index()` explicitamente
  - `unsignedInteger` para valores monetários (centavos)
  - `decimal(6,3)` para kg/L com precisão
  - `ulid()->unique()` para chave pública
  - Partial unique index para duplicatas em ativos: `WHERE deleted_at IS NULL`

### 2. Enum (opcional)
- Criar se o recurso tiver campo com conjunto fixo de valores (status, tipo, etc.)
- PHP 8.1 backed enum com método `values()`

### 3. Cast (opcional)
- Criar se a serialização for mais complexa que casts nativos
- Exemplo: `DateOnly` para datas sem time

### 4. Model
- Traits obrigatórias: `HasFactory`, `HasUlidRouteKey`, `SoftDeletes`
- `$fillable` como `list<string>` em ordem alfabética
- `$casts()` retornando `array<string, string>`
- `@property` PHPDoc para todos os atributos + relations
- `@return PHPDoc` com generics nas relations
- Cascade soft-delete via eventos `deleting`/`restoring` quando for pai de outros

### 5. Factory
- FK como `ParentModel::factory()` (cria o pai automaticamente)
- Estados para variações (ex.: `inactive()`, `admin()`)

### 6. Policy
- **Staff managed**: `use StaffManaged;` — para recursos de back-office
- **Granular**: Implementar cada método explicitamente para regras específicas

### 7. Form Requests
- Criar **Store** e **Update**: `{Resource}/Store{Resource}Request.php`, `{Resource}/Update{Resource}Request.php`
- Store: `required` nas rules; Update: `sometimes`
- `@example` PHPDoc em cada regra (documentação Scramble)
- `Rules::existsActive()` para validar ULIDs de tabelas com soft delete

### 8. Service
- Métodos padrão: `create`, `update`, `delete`, `restore`, `paginate`
- Resolução ULID → ID interno via `PublicId::resolve()`
- `DB::transaction()` para operações multi-tabela
- `Pagination::perPage()` no paginate

### 9. Controller
- Ordem: `destroy → index → restore → show → store → update`
- Cada método: `authorize()` → chama service → retorna response
- `#[Group]` para agrupar na doc Scramble
- `#[QueryParameter]` para parâmetros de query na index

### 10. Resource (API Resource)
- Expor ULID como `id`, nunca o ID interno
- Campos em ordem alfabética
- `@mixin Model` para autocomplete

### 11. Routes
- Helper `$softDeletable` para incluir restore automático
- Dentro do grupo `['auth:sanctum', 'impersonation']`

### 12. Tests
- Feature tests: CRUD + permissões + soft delete/restore
- `Sanctum::actingAs()` para autenticação
- `User::factory()->admin()->create()` ou `->master()->create()`

---

## Regras Gerais

### Traits Disponíveis

| Trait | Quando usar |
|-------|-------------|
| `HasUlidRouteKey` | **Sempre** |
| `SoftDeletes` | **Padrão** — exceção rara (Setting, Impersonation) |
| `AuditableTrait` | Recurso financeiro ou importante |
| `HasFactory` | **Sempre** |
| `InvalidatesQueryCache` | Listado com frequência |
| `Notifiable` | Recebe notificações |

### Padrão de Nomenclatura

| Item | Padrão | Exemplo |
|------|--------|---------|
| Tabela | `snake_case_plural` | `piece_categories` |
| Model | `StudlyCase` singular | `PieceCategory` |
| Controller | `{Model}Controller` | `PieceCategoryController` |
| Service | `{Model}Service` | `PieceCategoryService` |
| Resource | `{Model}Resource` | `PieceCategoryResource` |
| Route param | snake_case singular | `piece_category` |
| Route name | `{resources}.{action}` | `piece-categories.index` |
| Form Request | `{Action}{Model}Request` | `StorePieceCategoryRequest` |
| Factory | `{Model}Factory` | `PieceCategoryFactory` |
| Policy | `{Model}Policy` | `PieceCategoryPolicy` |
| Migration | `create_{snake_plural}_table` | `create_piece_categories_table` |

### Field Order (Migration)

```
id → ulid → FKs (alfabética) → campos (alfabética) → created_at → softDeletes → updated_at
```

### Method Order (Controller)

`destroy → index → restore → show → store → update`

---

## Referências

- Templates completos: [assets/templates.md](./assets/templates.md)
- Padrões de código: `.github/instructions/coding-standards.instructions.md`
- Instruções do projeto: `.github/copilot-instructions.md`
