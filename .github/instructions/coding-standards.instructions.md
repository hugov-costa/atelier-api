---
description: "Use when writing, editing, or reviewing PHP code in this project. Covers naming, ordering, comments, and structural conventions for maintainability."
applyTo: "**/*.php"
---

# Coding Standards

Este projeto segue padrões rigorosos para garantir consistência e facilidade de manutenção. O custo de implementação é maior, mas o código resultante é previsível e autoexplicativo.

---

## 0. Quality Gate — Obrigatório e Intransponível

**É expressamente proibido pular ou ignorar o quality gate**, total ou parcialmente, por qualquer motivo. Nenhum commit pode ser feito sem que todas as etapas abaixo passem:

1. **Pint** — `composer pint:test` (code style)
2. **PHPStan** — `composer phpstan` (static analysis, level max)
3. **Testes** — `composer test` (PHPUnit)

O CaptainHook aplica essas verificações automaticamente no `pre-commit`. O uso de `--no-verify` ou qualquer outro meio de contornar o gate é **terminantemente proibido** — mesmo para commits "rápidos" ou "provisórios", mesmo em branches feature, mesmo para correções urgentes.

> A única exceção é durante um rebase interativo que não modifica código (ex.: squash de commits já aprovados), e mesmo assim o resultado final deve passar pelo gate antes do push.

Violação desta regra é considerado falha grave de processo.

---

## 1. Comentários Inline

**Não deve haver comentários inline no código.** Nomes descritivos e código autoexplicativo substituem comentários.

**Exceção única:** `routes/api.php` — permite comentários para agrupar rotas, pois não há outra forma concisa de organizá-las.

```php
// ✅ Certo — sem comentários, nomes descritivos
public function getActiveUsers(): Collection { ... }

// ❌ Errado
public function getUsers(): Collection { // pega usuarios ativos
```

> Nomes descritivos compensam a ausência de comentários (veja seção 2).

---

## 2. Nomenclatura

- **Idioma**: sempre inglês
- **Estilo**: descritivo, nunca abreviado
- **Contexto**: o nome deve ser auto-suficiente para entender o propósito

```php
// ✅ Certo
public function calculateProductionCost(): int { ... }
public function findActiveEnrollmentsForMonth(Carbon $month): Collection { ... }

// ❌ Errado
public function calcCost(): int { ... }
public function getActEnr(Carbon $m): Collection { ... }
```

Aplicável a: classes, funções, métodos, variáveis, propriedades, constantes, parâmetros.

---

## 3. Ordem de Campos em Tabelas (Migrations / Schemas)

Ao definir colunas em uma migration, array de `$fillable`, casts, ou qualquer representação de esquema de tabela, siga esta ordem **quando aplicável**:

```
1. id
2. ulid
3. FKs (foreign keys) — em ordem alfabética
4. Demais campos — em ordem alfabética
5. created_at
6. deleted_at (padrão — soft delete para todo recurso)
7. updated_at
```

```php
// ✅ Certo — migration
Schema::create('pieces', function (Blueprint $table): void {
    $table->id();
    $table->ulid('ulid')->unique();
    $table->foreignId('clay_id')->constrained()->cascadeOnDelete();
    $table->foreignId('glaze_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('piece_category_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->decimal('clay_amount', 6, 3);
    $table->string('kind');
    $table->string('name');
    $table->unsignedInteger('price');
    $table->timestamps();
    $table->softDeletes();
});
```

```php
// ✅ Certo — $fillable (ordem alfabética)
protected $fillable = [
    'clay_id',
    'clay_amount',
    'kind',
    'name',
    'price',
    'user_id',
];
```

---

## 4. Ordem em Arrays, Propriedades e Models

Para **qualquer array**, lista de propriedades de classe, `$fillable`, `$casts`, `$attributes`, etc.: **ordem alfabética**.

```php
// ✅ Certo
protected $fillable = [
    'description',
    'email',
    'name',
    'phone',
];

// ❌ Errado
protected $fillable = [
    'name',
    'phone',
    'email',
    'description',
];
```

---

## 5. Ordem de Argumentos de Função

Argumentos de funções/métodos devem seguir **ordem alfabética**.

```php
// ✅ Certo
public function calculate(
    int $clayPricePerKg,
    float $clayAmount,
    ?int $glazePricePerLiter,
    ?float $glazeAmount,
    PieceKind $kind,
    Setting $settings,
): array { ... }

// ❌ Errado
public function calculate(
    Setting $settings,
    PieceKind $kind,
    int $clayPricePerKg,
    float $clayAmount,
    ?int $glazePricePerLiter,
    ?float $glazeAmount,
): array { ... }
```

---

## 6. Ordem de Métodos na Classe

A ordem dos métodos dentro de um arquivo deve ser:

```
1. Magic methods (__construct, __toString, etc.)
2. protected methods
3. public methods
4. private methods
```

Dentro de cada grupo, manter **ordem alfabética** quando possível.

```php
class PieceService
{
    // 1. Magic methods
    public function __construct(
        private PiecePricingService $pricing,
    ) {}

    // 2. Protected
    protected function resolveCategory(array $attributes): ?PieceCategory { ... }

    // 3. Public
    public function create(array $attributes): Piece { ... }
    public function delete(Piece $piece): void { ... }
    public function update(array $attributes, Piece $piece): Piece { ... }

    // 4. Private
    private function asFloat(mixed $value): float { ... }
    private function syncFiringCycles(...): void { ... }
}
```

> ⚠️ Exceção: métodos que implementam uma interface ou estendem classe pai seguem a ordem da interface/classe pai, não esta regra.

---

## 7. Fluxo de Dados

Siga as convenções gerais do projeto estabelecidas no `copilot-instructions.md`:

- Controllers → Services (controllers finos, lógica em Services)
- Services retornam Model, não Response
- Responses via `ApiResponse::item()` ou `ProblemDetail`
- Operações multi-tabela em `DB::transaction()`
- ULID como chave pública em todas as models
