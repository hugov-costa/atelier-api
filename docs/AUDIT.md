# Auditoria técnica — boilerplate-api

**Data:** 2026-07-02
**Escopo:** segurança, performance, funcionamento (corretude), qualidade de código.
**Método:** revisão manual do núcleo de autenticação/autorização + 6 varreduras profundas paralelas por dimensão. Gate estático executado: **PHPStan (level max) e Pint passam limpos, sem baseline** — o código já é sólido no nível estático, então os achados abaixo são semânticos (lógica, segurança, performance, design).

## Veredito geral

Base de código **madura e bem construída**. O núcleo de segurança (auth por cookie httpOnly + Sanctum, CSRF double-submit com `__Host-` e `hash_equals`, timing-guard no login, 2FA com segredos cifrados e anti-replay, brokers de reset com token hasheado) e a camada de autorização (policies, proteção do master, gate de impersonação) são de alta qualidade. Superfície Octane limpa (sem singletons/estáticos mutáveis). Nenhum achado **CRÍTICO**. Os itens que mais valem correção: 2 HIGH e um conjunto de MEDIUMs de corretude/privacidade/performance.

Contagem: **0 críticos · 2 altos · 12 médios · ~15 baixos/informativos.**

---

## ALTA severidade

### H1 — Query cache está morto sob Redis (round-trip desperdiçado) e desligado no deploy padrão
`config/cache.php:136` (`serializable_classes => false`) + `config/cache.php:20` (`CACHE_STORE=database`) + `app/Services/UserService.php:119-143`

`rememberSingleFlight` guarda um **objeto** `LengthAwarePaginator` no cache. Em store serializante (Redis, o único taggable viável em produção), a leitura passa por `unserialize` com `allowed_classes=false` → volta como `__PHP_Incomplete_Class`. O fluxo então: `get()` retorna não-nulo → early-return → `paginate()` vê que **não** é `instanceof LengthAwarePaginator` (linha 108) → refaz o SQL. Resultado no Redis: **todo request faz um GET jogado fora e reexecuta a query** (o single-flight/lock nunca é alcançado). No store `database` (default) não é taggable → `QueryCache::enabled()` é `false` → simplesmente sem cache. Ou seja, o subsistema não entrega benefício em **nenhuma** configuração padrão. Invisível no CI porque `.env.testing`/`phpunit.xml` usam `array` (`serialize=false`), que mascara o bug.
**Correção:** cachear um payload serializável (arrays: items + total + meta) e reconstruir o paginator; tratar leitura não-paginator como *miss*. Decidir explicitamente se o Redis é o store de produção — senão remover a camada.

### H2 — Reexecutar `tuitions:generate` reenvia a fatura ("generated") para todos os alunos com saldo aberto
`app/Services/StatementService.php:156-169` (+ `TuitionFeeService::generateForMonth`)

A criação de registros é idempotente (índice parcial único + `whereDoesntHave`), mas o envio da notificação não é: em `sendForUserCycle`, o guard `alreadyNotified()` é **pulado quando `$context === 'generated'`** (`if ($context !== 'generated' && ...)`, linha 164 — o docblock confirma: "Generation always sends"). `sendForCycle('generated')` seleciona **todos** os usuários com qualquer mensalidade (amount>0) ou cobrança de peça em aberto naquela data de vencimento. Se o comando agendado (`monthlyOn(1, 02:00)`) falhar no meio e for reexecutado, ou for rodado manualmente no mesmo mês, cada aluno com saldo em aberto recebe **outra fatura em PDF por e-mail** (0 registros novos criados).
**Correção:** tornar o envio `generated` idempotente (marcador por ciclo/contexto) ou notificar apenas os enrollments efetivamente criados nesta execução (coletar os user_ids no loop).

---

## MÉDIA severidade

### M1 — `anonymize()` não apaga `phone`, `birthday` nem `admission_date` (erasure LGPD incompleta) ⚠️ anotação duvidosa
`app/Services/AccountService.php:50-83` (e docblock `:34` / `UserController.php:139-143`)

`anonymize()` faz `forceFill` de name, email, password, avatar, `email_verified_at`, campos de 2FA e role — mas **não** de `phone`, `birthday` e `admission_date`. Após a "erasure irreversível", o telefone (identificador de contato direto) e a data de nascimento **permanecem** na linha do usuário. Isso derrota o objetivo de minimização de dados. O docblock ("Irreversibly erase a user's personal data") e o doc do endpoint erase ("Irreversibly erases the user's personal data … to fulfil a data subject's LGPD/GDPR erasure request") **afirmam algo que o código não faz** — anotação duvidosa. Além disso, `redactAuditTrail` só limpa as chaves `name`/`email` dos valores de auditoria, deixando phone/birthday que tenham passado por audits.
**Correção:** incluir `phone => null`, `birthday => null` (e decidir sobre `admission_date`) no `forceFill`; ampliar `REDACTED_AUDIT_KEYS`.

### M2 — `is_active` nunca é verificado na autenticação (desativar conta não bloqueia acesso)
`app/Services/AuthService.php:28-51` (e ausência em `AuthenticateFromCookie`/resolução de token)

`authenticate()` valida senha + 2FA, mas nunca `is_active`. `is_active` só é usado em billing/relatórios. Consequência: um usuário desativado mantém todos os tokens existentes **e** consegue logar e emitir novos. Se `is_active` for apenas status de matrícula/mensalidade, é aceitável; se operadores o tratam como "suspender conta", é bypass total dessa expectativa.
**Correção (se suspensão for desejada):** rejeitar inativos em `authenticate()`, adicionar checagem via `Sanctum::authenticateAccessTokensUsing` (bloqueia tokens já emitidos) e revogar tokens na desativação. **Confirmar a intenção de `is_active`.**

### M3 — Upload de avatar sem limite de dimensões → decompression-bomb / OOM do worker
`app/Http/Requests/Avatar/UploadAvatarRequest.php:29` + `app/Jobs/ProcessAvatarUpload.php:54-58`

Regras: `image|mimes:...|max:2048` — limitam só o tamanho do arquivo (KB), não a resolução. O job faz `decodeBinary()` (GD) que rasteriza a imagem **inteira** antes do `scaleDown()`. Um PNG de ~1.5 MB altamente compressível pode codificar ~20000×20000 → GD aloca ~GBs → OOM mata o worker Octane de fila (derrubando e-mails de verificação/reset e outros jobs). O upload de logo **acerta** isso (`UploadLogoRequest.php:32` tem `Rule::dimensions()->maxWidth(5000)->maxHeight(5000)`) — provando que a omissão no avatar é lapso.
**Correção:** adicionar `Rule::dimensions()->maxWidth(3000)->maxHeight(3000)` (a imagem é reduzida a 512 de qualquer forma).

### M4 — Nova cobrança de peça pode ligar-se a uma mensalidade **já paga** e ficar impagável
`app/Services/PieceChargeService.php:71` + `tuitionForCycle()` `:191-197`

`tuitionForCycle` seleciona **qualquer** mensalidade daquela data de vencimento — sem `whereNull('paid_at')`, contradizendo seu próprio docblock ("The unpaid tuition already raised"). Se a mensalidade-alvo já existe e já está paga, a nova cobrança liga-se a ela com `paid_at` nulo; a liquidação só ocorre na transição para paga (que nunca mais dispara), e `accelerateForUser`/`linkChargesToFee` não a alcançam. A cobrança fica permanentemente em aberto no extrato.
**Correção:** `->whereNull('paid_at')` em `tuitionForCycle` (ou copiar `paid_at` da fee já paga para a cobrança).

### M5 — Lembretes de anuidade sem idempotência → reexecução no mesmo dia duplica e-mail
`app/Console/Commands/SendBillingReminders.php:40-53`

Lembretes de mensalidade/peça usam `alreadyNotified`; `remindAnnualFee()` não tem guard — notifica todo enrollment que casa a data. Reexecução do comando (retry/manual) no mesmo dia reenvia `AnnualFeeDue`.
**Correção:** aplicar a mesma checagem "já notificado para este contexto+data" antes de disparar.

### M6 — Timezone da app é UTC para um ateliê pt-BR (UTC-3) → off-by-one de data ⚠️ verificar
`config/app.php:72` (`timezone => 'UTC'`), consumido por `Carbon::now()/today()` em `PieceChargeService::createForPiece:63`, `TuitionFeeService::nextDueDate:163`, `StudentStatementService::build:28`, `SendBillingReminders`.

Extratos são pt-BR (meses em português), sugerindo America/Sao_Paulo. Qualquer `now()/today()` entre ~21:00–23:59 local resolve para o **dia seguinte** em UTC — desloca bucket de carência/ciclo de peças registradas à noite e faz uma fee vencendo hoje virar "overdue" a partir das 21h local.
**Correção:** definir `app.timezone` para o fuso do ateliê, ou localizar explicitamente as chamadas de data. **Confirmar o fuso pretendido.**

### M7 — `ProblemDetail::response()` descarta headers de protocolo (`Retry-After`, `Allow`)
`app/Exceptions/ApiExceptionRenderer.php:44-48` + `app/Http/Responses/ProblemDetail.php:38-40`

O render converte todo `HttpExceptionInterface` mas nunca copia `$e->getHeaders()`. Um 429 (rate limiters de login/e-mail) perde `Retry-After`; um 405 perde `Allow`. Clientes ficam sem orientação de backoff e as respostas violam o contrato HTTP (o README:74 anuncia o tratamento de 405).
**Correção:** mesclar `$e instanceof HttpExceptionInterface ? $e->getHeaders() : []` nos headers de `ProblemDetail::response()`.

### M8 — Índices ausentes em FKs no PostgreSQL (Postgres não indexa FK automaticamente)
- `piece_charges.tuition_fee_id` — `2026_07_01_000062_*:22,30` (filtrado em `settleForTuition`/`unsettleForTuition`/`linkChargesToFee`, no hot path de marcar mensalidade paga → seq scan).
- `clays.clay_supplier_id`, `glazes.glaze_supplier_id` — usados no cascade/restore de soft-delete dos fornecedores.
- `impersonations.impersonator_id` — só `impersonated_id`/`token_id` são indexados.

O autor já indexou FKs de `pieces`/`enrollments`/`commission_orders`, então são omissões.
**Correção:** `$table->index(...)` em cada uma.

### M9 — Ordenação da lista de usuários por `name`/`email` sem índice B-tree
`app/Services/UserService.php:212-223` + `2026_06_24_000000_add_search_indexes_to_users_table.php`

`SORTABLE_COLUMNS` permite `name`/`email`/`created_at`. Só há GIN trigram (aceleram `LIKE '%..%'`, **não** `ORDER BY`) em name/email e B-tree só em `created_at`. `orderBy(name|email)` força filesort conforme a tabela cresce; `deleted_at` também não é indexado (o `WHERE deleted_at IS NULL` global não é assistido por índice).
**Correção:** B-tree em `name`/`email` (ou índice parcial `WHERE deleted_at IS NULL`) se o volume crescer.

### M10 — CRUD soft-deletable copiado-e-colado em ~12 recursos (não-DRY)
Services `ClaySupplierService`/`CustomerService`/`PieceCategoryService` são idênticos byte-a-byte exceto o model; `ClayService`/`GlazeService` diferem só no FK do fornecedor. Controllers idem (`destroy/index/restore/show/store/update` idênticos até o `->additional(['message'=>null])` e `per_page=15`). Qualquer mudança de convenção precisa ser replicada em ~12 lugares e vai divergir.
**Correção:** `CrudService`/`CrudController` abstratos (ou trait) parametrizados por model+resource; manter overrides só onde há diferença real.

### M11 — Cálculo financeiro dentro de Resource via service-location (risco de N+1)
`app/Http/Resources/CommissionOrderResource.php:23,45-48`

`toArray()` faz `app(CommissionOrderService::class)` (único `app(...)` em toda a camada Resources/Services/Controllers) e calcula `pieces_total`/`production_cost_total`/`realized_margin`/`sale_total` por pedido. Lógica de domínio/dinheiro na camada de apresentação; qualquer caller futuro que esqueça de eager-load `pieces` cai em N+1 (hoje o index eager-loada, então não dispara ainda).
**Correção:** calcular no service/model (ou accessors) e passar pronto; não resolver services em resources.

### M12 — Enum `DayOfWeek` é código morto; consumidores usam número mágico e numeração ambígua
`app/Enums/DayOfWeek.php` (zero usos) vs `between:1,7` mágico em `StoreRecurrentClassRequest:32`/`UpdateRecurrentClassRequest:38`, cast `integer` cru em `RecurrentClass.php:46`. A numeração do enum (Domingo=1…Sábado=7) não bate com Carbon (0–6) nem ISO (1=Seg) — armadilha latente se alguém comparar com `Carbon::dayOfWeek`.
**Correção:** usar o enum (`Rule::in(DayOfWeek::values())` + cast) ou removê-lo; não deixar os dois.

---

## BAIXA / INFORMATIVO

- **L1 — Login devolve o token no corpo JSON** além do cookie httpOnly (`AuthController.php:85-96`). Convida a persistir em `localStorage`, anulando o httpOnly (XSS exfiltra token de 14 dias). Justificável só para clientes não-browser; considerar condicionar ou remover no fluxo cookie.
- **L2 — Enumeração de usuário por timing** em `forgot-password` e `set-password/request` (trabalho síncrono diverge por existência da conta, apesar do 204 uniforme e e-mail em fila). Mitigado por `throttle:auth`, não eliminado.
- **L3 — Reenvio de e-mail de verificação usa o limiter genérico 60/min** (`routes/api.php:79`), permitindo e-mail-bomb ao próprio endereço; o padrão Laravel é 6/min. Aplicar `throttle:6,1`.
- **L4 — TOCTOU no bootstrap do primeiro master** (`UserService::createMaster:62-79`): dois requests concorrentes num install vazio podem criar dois masters. Envolver em transação com lock, ou índice único parcial em `role=master`.
- **L5 — CSRF condiciona só ao cookie `access_token`**, ignorando o `impersonate_token` (`VerifyCookieCsrfToken.php:32-45`) que `AuthenticateFromCookie` também aceita. Hoje não explorável (o `access_token` do master coexiste), mas o gate checa a pré-condição errada. Basear em "qualquer cookie de auth presente".
- **L6 — `quantity` numérico sem `max`** (`Store/UpdateMaterialPurchaseRequest`): `numeric` aceita notação científica (`1e308`), podendo estourar a coluna decimal / agregados. Adicionar `max` sensato.
- **L7 — `password` ainda mass-assignable** em `User#[Fillable]` (`User.php:48`). Inalcançável hoje (services setam explicitamente), mas footgun latente. Remover de `Fillable`.
- **L8 — `marginByCategory` agrupa por `piece_categories.name`, não `id`** (`ReportService.php:139`): categorias homônimas se fundem numa linha, contando peças/margem em dobro. Agrupar por `id`.
- **L9 — Template de conta recorrente pode ficar obsoleto** quando o último Bill foi soft-deletado (`BillService.php:39-46`): `MAX(id)` cai num registro antigo (o escopo default exclui trashed), carregando valor/vencimento defasados.
- **L10 — `PublicId::resolve()` retorna `0` em miss**, contradizendo o docblock "guaranteed to hit a row" (`PublicId.php:9-28`). Escreveria FK `0` silenciosamente. Ou lança em miss, ou remove a garantia do texto. ⚠️ anotação duvidosa.
- **L11 — `type` do problem+json aponta para páginas MDN**, e o 419 aponta para página inexistente (`ProblemDetail.php:17,28,32`). Usar `about:blank` (default RFC 9457) ou URIs próprias estáveis.
- **L12 — Repositório solitário** `RecurrentClassRepository` (único em `app/Repositories`, alcançado por `app(...)`) — outlier arquitetural. Adotar repositórios de forma consistente ou dobrar `hasOverlap()` num scope/service.
- **L13 — `markAllAsRead` faz um UPDATE por notificação** (`NotificationController.php:50`); `notifications` sem índice em `read_at`. Baixo volume por usuário.
- **L14 — Impersonar admin/inativo é permitido** (só master é protegido — `ImpersonationService.php:28`). Não é escalonamento (master já supera admin) e a escrita é atribuída ao impersonador; decidir se impersonação deveria ser só de alunos (`! $target->canViewUsers()`).
- **L15 — `users.update` acessível durante impersonação** (`RestrictImpersonatedSession` não o restringe); limitado a name/phone/birthday do próprio alvo, auditado. Adicionar à lista se indesejado.
- **L16 — `canViewUsers()` reutilizado como gate de escrita** do back-office (`Policies/Concerns/StaffManaged.php`): correto hoje, mas quem futuramente estreitar `canViewUsers()` para "só leitura" alterará silenciosamente autorização de escrita. Introduzir `isStaff()`/`canManageAtelier()`.
- **L17 — Comentário "6-digit TOTP"** em `TwoFactorController.php:43` vs validação só `['required','string']` (sem limite de tamanho/dígitos). ⚠️ anotação duvidosa; google2fa valida o formato na verificação, mas o comentário superestima o que o código impõe.
- **L18 — Datas sem limite superior** (`order_date`, `start_datetime`, `due_date`, `admission_date` aceitam ano 9999); `birthday` acerta com `before:today`. Qualidade de dado.
- **L19 — Comentários inline `//`** violando a regra da casa em: `TwoFactorController.php:43`, `StoreMaterialPurchaseRequest.php:151-152`, `LogoService.php:45-47`, `AppServiceProvider.php:41-43,89-90`, `BillingCycle.php:36-37`, `bootstrap/app.php:50-51,55`.
- **L20 — Ordenação de chaves em Resources** foge da regra da casa (id/ulid → alfabético) em `UserResource`, `AuditResource` e `ImpersonationResource` (esta sem `id`/`ulid` primeiro).
- **L21 — `helper values()` copiado em 5 enums**; `UserRole` inconsistentemente não o tem. Extrair trait `HasValues`.
- **L22 — `api.json` (spec OpenAPI, 565 KB) versionado** na raiz e `Sanctum token_prefix` vazio (perde secret-scanning). Considerar `.gitignore` do artefato gerado e um prefixo de token.

---

## Verificado e SÓLIDO (checado, sem ação)

- **Auth/CSRF:** cookies httpOnly, `Secure` forçado em produção; CSRF double-submit correto (skip de método seguro, `hash_equals` com ordem de args correta, header custom exigido, `__Host-` prefix; ordem de middleware `VerifyCookieCsrfToken` antes de `AuthenticateFromCookie` evita bypass). Login com timing-guard (bcrypt constante) impede enumeração por tempo.
- **2FA:** `two_factor_secret`/`recovery_codes` cifrados em repouso e ocultos/excluídos da auditoria; anti-replay via `two_factor_last_used_timestep`; recovery codes comparados com `hash_equals`, alta entropia, uso único; enable/disable exigem step-up de senha e são bloqueados na impersonação.
- **Reset/set-password:** brokers do framework, tokens hasheados, expiração e uso único; todos os tokens revogados no reset; set-password no-op em conta que já tem senha.
- **Autorização:** sem auto-escalonamento de role (`role` fora de `#[Fillable]`, sempre setado por código master-gated; último master não pode ser rebaixado; troca de e-mail próprio exige `current_password`). Master não pode ser impersonado nem se auto-deletar. Toda ação de controller de domínio é autorizada (6/6 index/show/store/update/destroy/restore); sem IDOR nos endpoints self/per-user; sem `before()` que super-conceda.
- **Injeção:** sem SQL/sort-column injection — `SORTABLE_COLUMNS` whitelist, direção normalizada, LIKE parametrizado; todo `selectRaw/whereRaw` usa strings estáticas. Sem `$request->all()`. Regex de telefone ancorada (sem ReDoS).
- **Uploads:** avatar e logo são re-encodados via Intervention (remove polyglot/payload; SVG excluído), chaves de storage geradas pelo servidor (sem path traversal), arquivos antigos limpos, ownership do avatar exigido.
- **Dinheiro:** inteiro em centavos ponta-a-ponta; `Money::toInt` guarda null/string; arredondamento único por etapa; sem float currency. `BillingCycle::dueDateForMonth` clampa dia 31 → último dia de meses curtos. Idempotência de **registros** protegida por índices parciais únicos.
- **Octane:** sem singletons/`scoped`/`instance` e sem estáticos mutáveis em `app/`; services transientes; `config('app.debug')` lido por-render. PDFs (dompdf) e processamento de imagem rodam **em fila** (`ShouldQueue`), fora do request. Paginação limitada a 100. Route-model binding por `ulid` (indexado).
- **Exposição:** `ApiExceptionRenderer` esconde stack/SQL fora de debug; `UserResource` não vaza segredos; docs Scramble protegidas por `RestrictedDocsAccess` (não públicas em produção por padrão).

---

## Remediação priorizada (sugestão)

1. **H2** (reenvio de faturas) e **M1** (erasure incompleta LGPD) — impacto direto em usuários e compliance; baixo esforço.
2. **M4/M5/M6** — corretude financeira/datas (orphan impagável, e-mail duplicado, off-by-one de fuso).
3. **M3** (avatar OOM) e **L3/L5/M2** — hardening de disponibilidade/segurança.
4. **H1 + M8/M9** — decidir o destino do query cache e adicionar os índices de FK/ordenação.
5. **M7** (headers de contrato) e a leva de qualidade (**M10–M12**, L-series) conforme cadência.
