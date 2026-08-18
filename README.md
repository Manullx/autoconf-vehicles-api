# Autoconf Vehicles API

API REST em Laravel 12 para autenticação, administração de usuários, veículos e imagens. A autenticação é stateless via Bearer token do Laravel Sanctum.

## Requisitos e instalação

- PHP 8.2 ou superior, Composer e SQLite, MySQL ou PostgreSQL.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure no `.env` a conexão com o banco e as credenciais do administrador inicial:

```dotenv
ADMIN_NAME=Administrator
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=uma-senha-segura
CORS_ALLOWED_ORIGINS=https://app.example.com
```

Use origens separadas por vírgulas em `CORS_ALLOWED_ORIGINS` quando necessário. Não use `*` em produção.

Prepare o banco, os dez veículos de exemplo e suas imagens placeholder:

```bash
php artisan migrate --seed
php artisan storage:link
```

O seeder é idempotente e pode ser repetido com `php artisan db:seed`. As imagens ficam em `storage/app/public/vehicles`.

## Execução e testes

```bash
composer run dev
composer test
```

A API fica disponível, por padrão, em `http://localhost:8000/api`.

## Autenticação

Gere um token enviando as credenciais para o login:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"uma-senha-segura"}'
```

Envie o token retornado nas chamadas protegidas:

```bash
curl http://localhost:8000/api/vehicles \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer SEU_TOKEN'
```

O logout em `POST /api/auth/logout` revoga o token usado na requisição.

## Documentação da API

O contrato completo está em [docs/openapi.yaml](docs/openapi.yaml), no formato OpenAPI 3.1. Ele pode ser importado no Swagger Editor, Postman, Insomnia ou em outro gerador compatível.

Os erros usam JSON consistente inspirado em RFC 7807, com `type`, `title`, `status` e `message`. Erros de validação também incluem `errors`, agrupado por campo.

## Segurança e observabilidade

- A API usa tokens pessoais do Sanctum, sem autenticação por sessão/CSRF.
- CORS aceita somente as origens configuradas em `CORS_ALLOWED_ORIGINS`.
- Login e registro possuem limite de 5 requisições por minuto; operações de imagem, 30 por minuto.
- Models usam `$fillable`, e controllers persistem somente dados validados.
- Exceções inesperadas são registradas pelo canal definido em `LOG_CHANNEL`. Em produção, use `APP_DEBUG=false`, `LOG_CHANNEL=daily` e `LOG_LEVEL=warning` ou mais restritivo.

## Principais respostas

- `200`: consulta ou atualização realizada.
- `201`: recurso criado.
- `204`: exclusão ou logout realizado.
- `401`: token ausente ou inválido.
- `403`: usuário sem permissão.
- `404`: recurso inexistente.
- `422`: dados inválidos.
- `429`: limite de requisições excedido.
