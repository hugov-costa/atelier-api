---
name: review-audit
description: 'Analisar e contrapor um relatório de auditoria de código fornecido pelo usuário. Usar quando solicitar: revisar auditoria, contrapor, analisar relatório, verificar achados, confirmar auditoria, avaliar relatório de auditoria, cruzar achados.'
argument-hint: 'Cole o relatório de auditoria ou descreva os achados a serem verificados'
---

# Revisão de Auditoria (Contra-Análise)

Analisar um relatório de auditoria **fornecido pelo usuário**, cruzando cada achado com o código real do projeto.

> ⚠️ **Diferença da `deep-audit`**: esta skill **não** faz varredura do zero. Ela pega um relatório pronto (de outra ferramenta, outro agente, ou sessão anterior) e verifica cada alegação contra o código.

---

## Procedimento

### 1. Carregar Contexto

1. Leia as instruções do projeto (`.github/copilot-instructions.md`)
2. Leia as convenções de código (`.github/instructions/coding-standards.instructions.md`)
3. Consulte a memória do repositório (`/memories/repo/`) para decisões de design já tomadas e correções pendentes
4. Se o usuário passou um documento/texto, identifique claramente cada **achado** individual

### 2. Para Cada Achado

Para cada ponto do relatório, execute:

1. **Localizar o código**: encontre o arquivo e linha exatos mencionados (ou deduza pelo contexto)
2. **Ler o código real**: leia o trecho completo, não apenas a linha citada. Nada de "...existing code..."
3. **Verificar a alegação**: o que o relatório diz é verdade? O código realmente faz o que foi afirmado?
4. **Classificar**:

| Veredito | Significado |
|----------|-------------|
| ✅ **Confirmado** | A evidência no código bate com o achado. Severidade está calibrada. |
| ⚠️ **Confirmado, severidade incorreta** | O problema existe, mas a severidade atribuída está errada (sub/superestimada). |
| ❌ **Falso-positivo** | O relatório alega algo que o código não confirma. Explique por quê. |
| 🔶 **Depende de decisão** | O achado é real, mas a correção depende de uma decisão de projeto que é sua. |
| ❓ **Não verificável** | O código citado não está no escopo ou não foi encontrado. |

### 3. Sugerir Correções

Para cada achado **confirmado**, sugira:

```
### H1 — [Título]
**Veredito**: ✅ Confirmado [ou ⚠️ Confirmado, severidade incorreta]
**Correção proposta**: [descrição clara do que precisa mudar]
**Arquivos envolvidos**: [lista de arquivos]
**Depende de decisão?**: [sim/não — se sim, qual pergunta]
```

### 4. Capturar Decisões Pendentes

Quando um achado depender de decisão de projeto (ex.: "is_active deve bloquear login?", "qual fuso horário usar?"), **pergunte usando o formato de opções selecionáveis** (`vscode_askQuestions`).

Registre a decisão na memória do repositório em `/memories/repo/boilerplate-api.md`.

### 5. Relatório Final

Ao final, produza um resumo no formato:

```markdown
## Resumo da Contra-Análise

| Veredito | Quantidade |
|----------|-----------|
| ✅ Confirmados | N |
| ⚠️ Confirmados (severidade incorreta) | N |
| ❌ Falso-positivos | N |
| 🔶 Dependentes de decisão | N |
| ❓ Não verificáveis | N |

## Pendências
- [Decisão 1] — [pergunta]
- [Decisão 2] — [pergunta]

## Próximos Passos
[Sugestão de ordem de correção, considerando as decisões pendentes]
```

---

## Regras

- **Não confie no relatório cegamente** — verifique cada alegação contra o código real
- **Seja honesto** — se o achado procede, confirme. Se não, explique
- **Separe fato de opinião** — "código não faz X" é fato. "Devia fazer Y" é opinião
- **Anotações duvidosas** — se o relatório apontar divergência entre docblock e código, verifique e classifique conforme a `deep-audit` (prescritivo vs assertivo)
- **Atualize a memória** — salve decisões tomadas e alinhe com correções já pendentes
