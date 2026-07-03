---
name: deep-audit
description: 'Realizar auditoria profunda de código-fonte com foco em segurança, performance, qualidade e LGPD. Usar quando solicitar: auditar, examinar, inspecionar, analisar criticamente, fazer uma auditoria, ou busca de vulnerabilidades/falhas/inconsistências. NÃO USAR para revisar/contrapor relatórios de auditoria prontos — use review-audit para isso.'
---

# Auditoria Profunda de Código (Deep Audit)

## Quando Usar
- O usuário pede uma auditoria, revisão, inspeção ou verificação completa do código
- Precisa avaliar segurança, performance, qualidade de código e corretude
- Precisa verificar conformidade com LGPD/GDPR (privacidade de dados)
- Precisa checar se documentação/comentários refletem a realidade do código
- Antes de deploys críticos ou mudanças arquiteturais

## Abordagem Geral

A auditoria deve ser **exaustiva, metódica e cética**. Siga estas fases sequencialmente. Em caso de dúvida sobre a intenção do código, **pergunte ao usuário** — não assuma.

---

## Fase 0: Preparação

### 0.1 Carregar Instruções do Projeto
Leia o(s) arquivo(s) de instrução do projeto:
- `.github/copilot-instructions.md`
- `README.md`
- Qualquer SKILL.md em `.github/skills/`

### 0.2 Carregar Decisões Anteriores
Consulte a memória do repositório (`/memories/repo/`) para decisões de design já tomadas.

### 0.3 Entender a Stack
Identifique:
- Framework e versão
- Banco de dados
- Cache
- Storage
- Autenticação/Autorização
- Servidor web
- Filas/Jobs
- Bibliotecas críticas de segurança

---

## Fase 1: Análise Estática

### 1.1 Ferramentas Automatizadas
Execute na ordem:

1. **Code style** — `composer pint:test` (ou equivalente no projeto)
   - Verificar se `declare(strict_types=1)` está em todos os arquivos PHP
2. **Static analysis** — `composer phpstan` (ou equivalente)
   - Verificar se passa sem baseline (nível máximo)
   - Anotar qualquer supressão ou erro ignorado

### 1.2 Leitura Estrutural Completa
Leia **todos** os arquivos do projeto, sem pular blocos. Para cada arquivo:
- Leia do início ao fim (ou em grandes blocos concatenados)
- Não use "...existing code..." — leia o conteúdo real
- Foque especialmente em: Models, Controllers, Services, Middleware, Providers, Config

---

## Fase 2: Varreduras por Dimensão

Execute **6 varreduras paralelas**, cada uma focada em uma dimensão. Para cada uma, leia os arquivos relevantes com atenção total.

### 2.1 Segurança (prioridade máxima)

| O que verificar | Onde olhar |
|----------------|------------|
| Autenticação | `AuthService`, `LoginRequest`, `Sanctum config` |
| Autorização | `Policies/`, `middleware`, `FormRequest::authorize()` |
| CSRF | `VerifyCookieCsrfToken`, `cors.php` |
| Two-factor | `TwoFactorService`, recovery codes, anti-replay |
| Impersonação | `ImpersonationService`, `RestrictImpersonatedSession` |
| Rate limiting | `AppServiceProvider`, `routes/api.php` |
| Password rules | `AppServiceProvider`, `UpdatePasswordRequest` |
| Cookies | `AuthenticateFromCookie`, `auth.php` config |
| Headers de segurança | `SecurityHeaders` |
| Validação de input | Todos os Form Requests |
| Mass assignment | `$fillable` em todos os Models |
| SQL injection | Queries raw, `whereRaw`, `DB::statement` |
| Serialização insegura | Cache config (`serializable_classes`) |
| Upload de arquivos | Avatar, logo — validação de tipo/dimensão |
| Tokens expostos | Logs, responses, URLs |
| Octane safety | Singletons, estáticos mutáveis |

**Checklist específico para Laravel/Sanctum:**
- [ ] Token cookie é httpOnly?
- [ ] CSRF double-submit usa `hash_equals`?
- [ ] Há timing guard no login (hash constante para email inexistente)?
- [ ] 2FA recovery codes são armazenados cifrados?
- [ ] Impersonação é time-boxed?
- [ ] `is_active` é respeitado em writes?
- [ ] Rate limiting cobre endpoints críticos?

### 2.2 Privacidade e LGPD/GDPR (100% obrigatório)

| O que verificar | Onde olhar |
|----------------|------------|
| Anonimização | `AccountService::anonymize()` |
| Dados pessoais residuais | Todos os campos da User table |
| Audit trail | `ImpersonatorResolver`, `AccountService::redactAuditTrail()` |
| Consentimento | Fluxos de criação de conta |
| Retenção | Config de prunning, `anonymize_trashed_days` |
| Exportação | `AccountController::export()` |

**Checklist LGPD:**
- [ ] Anonimização cobre **todos** os campos pessoais (name, email, phone, birthday, admission_date, password, avatar, 2FA, role)
- [ ] Audit trail é redigido após anonimização
- [ ] Avatar é removido do storage
- [ ] Tokens são revogados
- [ ] Após anonimização, registro ainda é referencialmente válido (soft-delete mantém FK)
- [ ] Dados bancários/financeiros são mantidos? (LGPD Art. 16 — obrigações legais)

### 2.3 Performance

| O que verificar | Onde olhar |
|----------------|------------|
| N+1 queries | Relations sem `with()`, lazy loading em loops |
| Cache | `QueryCache`, `InvalidatesQueryCache`, `cache.php` config |
| Índices de BD | Migrations — FKs, colunas de filtro/ordenação |
| Paginação | `Pagination` helper, `cursorPaginate` vs `paginate` |
| Large datasets | `chunkById` em jobs, `chunk` em queries grandes |
| Serialização | Objetos complexos em cache (`serializable_classes`) |

**Checklist Performance:**
- [ ] Cache store é taggable (Redis)?
- [ ] `serializable_classes` permite objetos necessários?
- [ ] Todas as FKs têm índices explícitos? (Postgres NÃO cria automático)
- [ ] Colunas de `ORDER BY` e `WHERE` frequentes são indexadas?
- [ ] Jobs processam em lotes (chunk)?
- [ ] Não há queries dentro de loops (N+1)?

### 2.4 Qualidade de Código

| O que verificar | Onde olhar |
|----------------|------------|
| Consistência de padrão | Controllers → Services → Models |
| Type hints | Parâmetros, retornos, propriedades |
| Docblocks | Precisão, `@param`, `@return`, `@property` |
| Código morto | Enums/classes/métodos não utilizados |
| Complexidade | Métodos muito longos, muitos parâmetros |
| Tratamento de erros | Exceções, validação, fallbacks |
| Testes | Cobertura de bordas, cenários de erro |

### 2.5 Corretude (Assertividade)

| O que verificar | Onde olhar |
|----------------|------------|
| Lógica de negócio | Services — as regras implementam o domínio? |
| Datas e timezone | `now()`, `toDateString()`, `Carbon` operations |
| Cálculos monetários | Integer cents — arredondamentos, somas |
| Cascade soft-delete | Events `deleting`/`restoring` propagam corretamente? |
| Unique constraints | Partial indexes, race conditions |
| Idempotência | Jobs, comandos schedulados |
| Transações | `DB::transaction()` em operações multi-tabela |

### 2.6 Documentação vs Realidade (Anotações Duvidosas)

Para **cada** comentário, docblock, `@property`, anotação, README, ou documentação:

#### Classificação de Duvidosidade

A chave está no **tom** da anotação — o que ela **afirma** vs o que ela **prescreve**:

| Tom | Classificação | Rigidez da análise |
|-----|---------------|-------------------|
| **Prescritivo** (diz o que **deve** ser feito) | ✅ **Não duvidoso** por padrão | Analisar se o código implementa o que foi prescrito |
| **Assertivo/Descritivo** (diz o que **é** feito, **afirma** um comportamento) | ⚠️ **Duvidoso** | Exige verificação rigorosa — confrontar com o código real |

**Exemplos:**

```
// ✅ Prescritivo (não duvidoso):
// "É necessário consultar o campo x da tabela y antes de proceder."
// ↓ O comentário diz o que DEVE ser feito. Analisar se o código faz isso.

// ⚠️ Assertivo (duvidoso):
// "A consulta do campo x da tabela y é feita."
// ↓ O comentário AFIRMA que algo acontece. Verificar se realmente acontece.
```

**Exemplos reais do projeto (já identificados):**

| Anotação | Tom | Veredito |
|----------|-----|----------|
| `"Irreversibly erase a user's personal data"` (AccountService) | Assertivo — afirma que apaga **todos** os dados pessoais | ⚠️ Duvidoso — phone/birthday não são apagados |
| `"guaranteed to hit a row"` (PublicId) | Assertivo — afirma que sempre encontra o registro | ⚠️ Duvidoso — retorna 0 em miss |
| `"6-digit TOTP"` (TwoFactorController) | Assertivo — afirma que a validação é de 6 dígitos | ⚠️ Duvidoso — regra é só `string` sem limite |
| `"The unpaid tuition already raised..."` (PieceChargeService) | Assertivo — afirma que a query filtra não pagas | ⚠️ Duvidoso — query não tem `whereNull('paid_at')` |

**Regra geral:** Anotações assertivas/descritivas são **sempre** suspeitas até prova em contrário lida no código. Anotações prescritivas podem estar desatualizadas (o código pode não implementar o que foi prescrito), mas a natureza da suspeita é diferente — é uma lacuna de implementação, não uma afirmação falsa.

#### Checklist

- [ ] Classificar cada anotação como **prescritiva** ou **assertiva**
- [ ] Para assertivas: o código **realmente** faz o que está escrito?
- [ ] Para prescritivas: o código **implementa** o que foi prescrito?
- [ ] Especial atenção a:
  - Docblocks de métodos ("irreversibly erase", "guaranteed to hit", etc.)
  - README.md (funcionalidades anunciadas vs implementadas)
  - Comentários inline que podem estar desatualizados
  - Anotações de segurança que podem ser enganosas

---

## Fase 3: Cruzamento de Achados

Para cada achado em qualquer dimensão:

1. **Verificar a localização exata** no código (arquivo + linha)
2. **Confirmar** lendo o código ao redor (contexto)
3. **Categorizar** a severidade:
   - **Crítico**: Vulnerabilidade explorável, perda de dados, violação LGPD
   - **Alto**: Quebra de funcionalidade, degradação severa, anonimização incompleta
   - **Médio**: Inconsistência, risco baixo, performance sub-ótima
   - **Baixo/Info**: Código morto, docstring imprecisa, estilo
4. **Verificar** se o achado é real (não assumir — ler o código)
5. **Anotar** se depende de decisão do usuário (ex.: "is_active deve bloquear login?")

---

## Fase 4: Relatório

### Estrutura do Relatório

```markdown
# Auditoria: [projeto]

## Veredito Geral
[Resumo de 2-3 frases. Mencionar pontos fortes e número de achados.]

## Resumo
- **Críticos**: N
- **Altos**: N
- **Médios**: N
- **Baixos/Info**: N

## [Críticos/Altos]
### H1 — [Título curto]
**Arquivo**: `caminho/arquivo.php:linha`
**Dimensão**: [segurança | performance | qualidade | lgpd | corretude | docs]
**Descrição**: [3-5 linhas explicando o problema]
**Evidência**: [citação do código ou configuração relevante]
**Impacto**: [o que acontece se não for corrigido]
**Depende de decisão?**: [sim/não + qual]

## [Médios]
[mesmo formato, agrupados por dimensão]

## Anotações Duvidosas
[Listar todas as divergências entre documentação e código]

## O Que Foi Verificado e Está Sólido
[Listar o que foi examinado e aprovado — mostra escopo da auditoria]

## Pendências (se houver)
[Perguntas que precisam de resposta do usuário para prosseguir]
```

### Regras do Relatório
- Severidades bem calibradas (não inflar)
- Cada achado deve ser **verificável** (arquivo + linha + contexto)
- Se não puder confirmar, marcar como **"não verificado"** ou **"depende de contexto"**
- Nada deve ser assumido sem evidência
- Falso-positivos devem ser explicitamente anotados como tal

---

## Fase 5: Análise Confirmatória (Opcional)

Se o usuário contratar uma segunda análise da auditoria:

1. **Não confiar cegamente** nos achados — verificar cada um contra o código
2. Para cada achado, declarar:
   - **Confirmado** — a evidência no código bate
   - **Falso-positivo** — explicar por que
   - **Não verificável** — código não está no escopo lido
3. Verificar se as severidades estão proporcionais
4. Verificar se a auditoria original cobriu todas as dimensões
5. Sinalizar qualquer viés ou lacuna na cobertura

---

## Princípios Fundamentais

1. **Leia o código real** — nunca confie em resumos, cache visual, ou "...existing code..."
2. **Seja cético** — especialmente com docblocks e comentários
3. **Não assuma intenção** — se o código faz algo inesperado, pergunte ao usuário
4. **Priorize pelo impacto** — segurança > LGPD > performance > qualidade > docs
5. **Verifique, não confie** — cada achado deve ser confirmado lendo o código
6. **Documente o escopo** — o que foi e o que não foi verificado

## Assets

Esta skill não requer assets externos. Todo o conhecimento necessário está neste documento.
