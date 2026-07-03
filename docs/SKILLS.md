# Skills do Projeto

Skills disponíveis para o atelier. Ativadas automaticamente pelo contexto da conversa ou via `/comando`.

| Comando | Skill | Descrição |
|---------|-------|-----------|
| `/create-resource` | `create-resource` | Criar um novo recurso CRUD seguindo os patterns do projeto (migration → model → controller → tests, etc.) |
| `/deep-audit` | `deep-audit` | Auditoria profunda do zero: varre o código em 6 dimensões (segurança, LGPD, performance, qualidade, corretude, documentação) |
| `/review-audit` | `review-audit` | Contra-analisar um relatório de auditoria já existente: cruza cada achado com o código real, classifica e sugere correções |

## Como usar

- **Slash command**: digite `/` no chat e selecione a skill
- **Contexto automático**: basta descrever o que precisa com palavras-chave que a skill relevante é carregada

Exemplos:
- "Cria um recurso de Categoria de Aluno" → `/create-resource`
- "Faz uma auditoria no projeto" → `/deep-audit`
- "Analisa essa auditoria que recebi" → `/review-audit`
