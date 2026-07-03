# Deploy no Render — Passo a Passo

Guia para iniciantes. Siga **exatamente nesta ordem**.

---

## Antes de começar

Você vai precisar:

- [ ] **Uma conta no GitHub** com o repositório do projeto (seu `boilerplate-api`)
- [ ] **Um cartão de crédito** para criar a conta no Render (eles pedem mesmo no plano pago)
- [ ] **Um domínio** (opcional, Render dá um `<nome>.onrender.com` de graça)

---

## Passo 1: Criar conta no Render

1. Acesse https://dashboard.render.com
2. Clique **"Get Started"** → **"Sign up with GitHub"**
3. Autorize o Render a acessar seus repositórios
4. Preencha os dados do cartão (não cobra nada até você subir o plano)

---

## Passo 2: Criar o Banco de Dados (PostgreSQL)

> Faça **primeiro** o banco, porque o app precisa dele pra funcionar.

1. No dashboard do Render, clique **"New +"** → **"PostgreSQL"**
2. Preencha:
   - **Name**: `boilerplate-db`
   - **Database**: `atelier` (padrão)
   - **User**: `atelier` (padrão)
   - **Region**: `São Paulo (South America)` ⬅️ **IMPORTANTE**
   - **Plan**: `Starter` (US$7/mês) — suficiente pra começar
3. Clique **"Create Database"**
4. **Aguarde** a criação (1-2 minutos)
5. Na tela do banco, copie a **"Internal Database URL"** (vai usar no passo 5)
   ⚠️ Guarde essa URL num bloco de notas, você vai usar várias vezes

---

## Passo 3: Criar o Redis

1. Clique **"New +"** → **"Redis"**
2. Preencha:
   - **Name**: `boilerplate-redis`
   - **Region**: `São Paulo (South America)` ⬅️ **IMPORTANTE**
   - **Plan**: `Starter` (US$7/mês)
3. Clique **"Create Redis"**
4. Após criado, copie a **"Internal Connection String"**

---

## Passo 4: Configurar Storage (Cloudflare R2 — o mais barato)

O app precisa de um lugar pra guardar fotos (avatar, logo). Render não tem isso nativo. A opção mais barata com boa performance no Brasil:

1. Crie uma conta em https://dash.cloudflare.com (se não tiver)
2. Vá em **"R2"** no menu
3. Ative o R2 (pede cartão, mas o plano free inclui 10GB)
4. Crie **dois buckets**:
   - **Bucket 1**: `atelier-public` (arquivos públicos: avatares)
   - **Bucket 2**: `atelier-private` (arquivos temporários)
5. Vá em **"R2"** → **"Manage R2 API Tokens"** → **"Create API Token"**
   - Permissão: **"Admin Read & Write"**
   - Copie o **Access Key ID** e o **Secret Access Key**

**Alternativa gratuita (se não quiser Cloudflare):**
Pule este passo. Na configuração do app (Passo 5), use `STORAGE_DRIVER=local` em vez de `minio`. Avatares e logos serão salvos no disco do próprio servidor Render (funciona, mas não persiste se o container for recriado — só pra testes).

---

## Passo 5: Configurar as variáveis de ambiente

Antes de criar o Web Service, prepare **todas** as variáveis de ambiente. Você vai colar isso no Render.

Crie uma lista num arquivo de texto no seu computador com:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=<deixe em branco, o entrypoint gera>
APP_URL=https://seu-app.onrender.com   # troque pelo seu domínio depois
FRONTEND_URL=https://seu-front.vercel.app  # URL do frontend (coloque depois)

DB_CONNECTION=pgsql
DATABASE_URL=<cole a Internal Database URL do PostgreSQL aqui>

REDIS_URL=<cole a Internal Connection String do Redis aqui>
CACHE_STORE=redis
QUERY_CACHE_ENABLED=true

STORAGE_DRIVER=r2   # ou "local" se não usar Cloudflare
MINIO_ACCESS_KEY=<Access Key ID do R2>
MINIO_SECRET_KEY=<Secret Access Key do R2>
MINIO_PRIVATE_BUCKET=atelier-private
MINIO_PUBLIC_BUCKET=atelier-public
MINIO_ENDPOINT=https://<seu-account>.r2.cloudflarestorage.com
MINIO_PUBLIC_URL=https://pub-<hash>.r2.dev  # URL pública do bucket (Cloudflare gera)

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailjet.com
MAIL_PORT=587
MAIL_USERNAME=<sua Mailjet API Key>
MAIL_PASSWORD=<sua Mailjet Secret Key>
MAIL_FROM_ADDRESS=noreply@seudominio.com
MAIL_FROM_NAME="Ateliê"

CORS_ALLOWED_ORIGINS=https://seu-front.vercel.app
AUTH_COOKIE_SAME_SITE=none

TELESCOPE_ENABLED=false
PULSE_ENABLED=false
```

**⚠️ Dicas importantes:**
- `DATABASE_URL` é a **Internal Database URL** do Render (começa com `postgres://`)
- `REDIS_URL` é a **Internal Connection String** do Redis (começa com `rediss://` ou `redis://`)
- Se usar `STORAGE_DRIVER=local`, pule as variáveis `MINIO_*`
- **NÃO** coloque `APP_KEY` ainda — o entrypoint gera sozinho na primeira execução

---

## Passo 6: Fazer push do código para o GitHub

1. No seu computador, abra o terminal na pasta do projeto
2. Execute:
```bash
git add .
git commit -m "Ajustes para deploy no Render"
git push origin main
```

---

## Passo 7: Criar o Web Service (API)

1. No dashboard do Render, clique **"New +"** → **"Web Service"**
2. **Connect repository**: escolha seu repositório (`boilerplate-api`)
3. Preencha:
   - **Name**: `boilerplate-api`
   - **Region**: `São Paulo (South America)` ⬅️ **IMPORTANTE**
   - **Branch**: `main`
   - **Runtime**: `Docker` (Render vai detectar o Dockerfile)
   - **Plan**: `Starter` (US$7/mês, 512MB RAM)
4. Role até **"Environment Variables"**
5. Adicione **todas as variáveis** do Passo 5, uma por uma:
   - Clique **"Add Environment Variable"**
   - Cole o nome e o valor
   - Repita para cada uma
6. Clique **"Create Web Service"**

**Aguarde o build** (5-10 minutos na primeira vez). Você vai ver o log no navegador:

```
[entrypoint] generating application key
[entrypoint] applying pending migrations
[entrypoint] starting process: php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=10000
```

Quando aparecer `✓ Server running...`, sua API está no ar! 🎉

Render vai te dar uma URL tipo: `https://boilerplate-api.onrender.com`

---

## Passo 8: Verificar se a API está funcionando

Abra o navegador e acesse:

```
https://boilerplate-api.onrender.com/api/v1/health
```

Você deve ver algo como:
```json
{"data":{"status":"ok","checks":{"database":"ok","cache":"ok","storage":"ok"}},"message":null}
```

Se vir `"status":"ok"` → **Está funcionando!** ✅

---

## Passo 9: Criar o Background Worker (Fila)

Render precisa de um serviço separado para processar filas (envio de email, processamento de avatar).

1. Clique **"New +"** → **"Background Worker"**
2. **Conecte o mesmo repositório**
3. Preencha:
   - **Name**: `boilerplate-worker`
   - **Region**: `São Paulo (South America)`
   - **Branch**: `main`
   - **Runtime**: `Docker`
   - **Start Command**: `php artisan queue:work redis --sleep=1 --tries=3 --max-time=3600 --backoff=5`
   - **Plan**: `Starter` (US$7/mês)
4. Adicione **as mesmas variáveis de ambiente** do Passo 5
5. **IMPORTANTE**: Adicione também:
   - `CONTAINER_ROLE` = `worker`
6. Clique **"Create Background Worker"**

Aguarde o build. Quando subir, os jobs da fila serão processados automaticamente.

---

## Passo 10: Configurar o Cron (Agendador)

O scheduler roda tarefas periódicas (gerar mensalidades, enviar lembretes). No Render, isso é um **Cron Job**.

1. Clique **"New +"** → **"Cron Job"**
2. Conecte o mesmo repositório
3. Preencha:
   - **Name**: `boilerplate-scheduler`
   - **Region**: `São Paulo (South America)`
   - **Branch**: `main`
   - **Runtime**: `Docker`
   - **Start Command**: `php artisan schedule:work`
   - **Schedule**: `* * * * *` (roda a cada minuto, que é como o Laravel Scheduler funciona)
   - **Plan**: `Starter` (US$7/mês, mas só roda 1x por minuto — uso mínimo)
4. Adicione **as mesmas variáveis de ambiente**
5. Adicione também:
   - `CONTAINER_ROLE` = `scheduler`
6. Clique **"Create Cron Job"**

---

## Passo 11: Criar o primeiro usuário master

A API está no ar, mas não tem nenhum usuário. Você precisa criar o **primeiro**:

Abra o terminal no seu computador e execute:

```bash
curl -X POST https://boilerplate-api.onrender.com/api/v1/users/master \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Administrador",
    "email":"admin@meuatelie.com.br",
    "password":"Minha@Senha123",
    "password_confirmation":"Minha@Senha123"
  }'
```

Se funcionar, você vai receber:
```json
{"data":{"id":"<ULID>","name":"Administrador","email":"admin@meuatelie.com.br",...},"message":null}
```

**⚠️ Esse comando só funciona UMA vez.** Depois que existir qualquer usuário no banco, ele é rejeitado.

---

## Passo 12: Testar o login

```bash
curl -X POST https://boilerplate-api.onrender.com/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email":"admin@meuatelie.com.br",
    "password":"Minha@Senha123"
  }'
```

Você deve receber um `token` de volta. Se recebeu → **Tudo funcionando!** 🚀

---

## Passo 13: Configurar domínio próprio (opcional)

1. No dashboard do Render, vá em **"Settings"** do seu Web Service
2. Role até **"Custom Domain"**
3. Digite seu domínio: `api.meuatelie.com.br`
4. Render mostra um **registro CNAME** que você precisa criar no DNS do seu domínio
5. Vá no painel do seu provedor de domínio (Registro.br, HostGator, etc.)
6. Crie um registro **CNAME**:
   - **Nome**: `api`
   - **Valor**: `boilerplate-api.onrender.com`
7. Após propagar (pode levar horas), o Render emite SSL automático

---

## Resumo de custos mensais

| Serviço | Plano | Custo (USD) |
|---------|-------|-------------|
| Web Service (API) | Starter | $7 |
| PostgreSQL | Starter | $7 |
| Redis | Starter | $7 |
| Background Worker | Starter | $7 |
| Cron Job | Starter | ~$1 (uso mínimo) |
| Cloudflare R2 | Free | $0 |
| **Total** | | **~$29/mês** |

Para economizar, você pode **desligar o Worker** se não for usar filas por enquanto, ou usar o plano gratuito do Render (cada serviço "hiberna" após 15min sem uso — só para testes).

---

## Problemas comuns (e como resolver)

**❌ "No application encryption key specified"**
→ Você esqueceu de adicionar `APP_KEY` nas env vars, ou o entrypoint não conseguiu gerar. Vá em **Environment Variables** e coloque manualmente uma chave gerada por `php artisan key:generate --show`.

**❌ "SQLSTATE[08006] could not connect to server"**
→ A `DATABASE_URL` está errada, ou o banco não está pronto. Verifique no Render se o PostgreSQL está verde (status "Available").

**❌ O build falha com "out of memory"**
→ O plano Starter tem 512MB. Se o build falhar, vá em **Settings** do Web Service, mude o **Plan** para "Professional" temporariamente durante o build, e depois volte para Starter.

**❌ "Error while processing job: Maximum attempts reached"**
→ O worker não está rodando. Verifique se o Background Worker está com status "Live".

**❌ O site fica "pending" para sempre**
→ O build pode estar demorando. Render tem limite de 15 minutos para builds no plano Starter. Se passar disso, o deploy falha. Tente novamente — builds sucessivos são mais rápidos (cache Docker).
