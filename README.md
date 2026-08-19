# Autoconf Vehicles API

API REST para gerenciamento de usuários, veículos e imagens, desenvolvida com Laravel 12. A aplicação utiliza autenticação stateless por Bearer token com Laravel Sanctum e possui controle de acesso entre administradores e usuários comuns.

## Funcionalidades

- Autenticação com tokens pessoais do Laravel Sanctum.
- Fluxo de primeiro acesso com criação obrigatória de senha.
- Administração de usuários.
- Listagem, busca, ordenação e paginação de veículos.
- Controle de acesso aos veículos por proprietário.
- Cadastro de veículo com até 5 imagens de no máximo 200 MB cada.
- Escolha da imagem de capa durante o cadastro.
- Inclusão, exclusão e alteração da capa das imagens.
- Exclusão definitiva do veículo, dos registros e dos arquivos relacionados.
- Respostas de erro padronizadas em JSON.
- Contrato OpenAPI 3.1.

## Requisitos

- PHP 8.2 ou superior.
- Composer 2.
- Extensões PHP exigidas pelo Laravel, incluindo `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `tokenizer` e `xml`.
- SQLite, MySQL ou PostgreSQL.

Para uploads de imagens, configure `upload_max_filesize` com pelo menos `200M`. Como uma requisição pode conter até cinco imagens, ajuste também `post_max_size` de acordo com o tamanho total que deseja permitir.

## Instalação

Clone o projeto, acesse seu diretório e instale as dependências:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### Configuração do ambiente

O projeto usa SQLite por padrão. Crie o arquivo do banco caso ele ainda não exista:

```bash
touch database/database.sqlite
```

As principais variáveis do `.env` são:

```dotenv
APP_NAME="Autoconf Vehicles API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite

ADMIN_NAME=Administrator
ADMIN_EMAIL=admin@example.com

CORS_ALLOWED_ORIGINS=http://localhost:3000
```

Para MySQL ou PostgreSQL, substitua a configuração do banco:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=autoconf
DB_USERNAME=usuario
DB_PASSWORD=senha
```

Mais de uma origem CORS pode ser informada separando os endereços por vírgulas. Em produção, use `APP_DEBUG=false` e não configure `*` como origem permitida.

### Banco de dados e dados iniciais

Execute as migrations e os seeders:

```bash
php artisan migrate --seed
```

O seeder cria:

- Um administrador com o nome e e-mail configurados no `.env`.
- Dez veículos de demonstração com imagens locais.

Na primeira execução, uma senha temporária segura é exibida no terminal. Quando o seeder é executado novamente, a senha atual é preservada e a flag `first_login` do administrador volta para `true`.

Crie o link público necessário para acessar as imagens:

```bash
php artisan storage:link
```

Os arquivos dos veículos são armazenados em `storage/app/public/vehicles/{vehicle_id}`.

## Executando a aplicação

Inicie o servidor local:

```bash
composer run dev
```

Ou utilize diretamente o Artisan:

```bash
php artisan serve
```

A API ficará disponível em `http://localhost:8000/api`.

## Autenticação e primeiro acesso

### 1. Login inicial

Usuários com `first_login: true` podem realizar o login somente com o e-mail:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com"}'
```

A resposta contém o usuário, a flag `first_login` e o token:

```json
{
  "user": {
    "id": 1,
    "name": "Administrator",
    "email": "admin@example.com",
    "is_admin": true,
    "first_login": true
  },
  "first_login": true,
  "token": "TOKEN_GERADO",
  "token_type": "Bearer"
}
```

### 2. Definição da senha

Use o token do login para definir a senha. A confirmação deve ser igual e a senha precisa ter no mínimo 8 caracteres:

```bash
curl -X POST http://localhost:8000/api/auth/password \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer TOKEN_GERADO' \
  -d '{"password":"nova-senha","password_confirmation":"nova-senha"}'
```

Após essa operação, `first_login` passa para `false`.

### 3. Próximos logins

Quando `first_login` for `false`, a senha correta será obrigatória:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"nova-senha"}'
```

Nas chamadas protegidas, envie o token no cabeçalho:

```bash
curl http://localhost:8000/api/vehicles \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer TOKEN_GERADO'
```

O logout em `POST /api/auth/logout` revoga o token utilizado na requisição.

## Rotas principais

Todas as rotas abaixo, exceto o login, exigem autenticação.

| Método | Rota | Descrição |
| --- | --- | --- |
| `POST` | `/api/auth/login` | Autentica e gera um token |
| `POST` | `/api/auth/password` | Define a senha do usuário autenticado |
| `POST` | `/api/auth/logout` | Revoga o token atual |
| `GET` | `/api/auth/me` | Retorna o usuário autenticado |
| `POST` | `/api/auth/register` | Cria um usuário comum como administrador |
| `GET/POST` | `/api/users` | Lista ou cria usuários |
| `GET/PUT/PATCH/DELETE` | `/api/users/{user}` | Gerencia um usuário |
| `GET/POST` | `/api/vehicles` | Lista ou cria veículos |
| `GET/PUT/PATCH/DELETE` | `/api/vehicles/{vehicle}` | Gerencia um veículo |
| `POST` | `/api/vehicles/{vehicleId}/images` | Adiciona imagens |
| `PATCH` | `/api/vehicles/{vehicleId}/images/{imageId}/cover` | Define a imagem de capa |
| `DELETE` | `/api/vehicles/{vehicleId}/images/{imageId}` | Exclui uma imagem e seu arquivo |

O contrato completo, incluindo os campos e respostas de cada rota, está em [`docs/openapi.yaml`](docs/openapi.yaml).

## Cadastro de veículos com imagens

O cadastro com imagens utiliza `multipart/form-data`. O campo `files` aceita até cinco imagens, cada uma com no máximo 200 MB. O campo `cover_index` indica a posição da capa no array, começando em `0`.

```bash
curl -X POST http://localhost:8000/api/vehicles \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer TOKEN_GERADO' \
  -F 'placa=ABC1D23' \
  -F 'chassi=12345678901234567' \
  -F 'marca=Toyota' \
  -F 'modelo=Corolla' \
  -F 'versao=XEi' \
  -F 'valor_venda=120000.00' \
  -F 'cor=Branco' \
  -F 'km=15000' \
  -F 'cambio=automatico' \
  -F 'combustivel=flex' \
  -F 'files[]=@/caminho/frente.jpg' \
  -F 'files[]=@/caminho/traseira.jpg' \
  -F 'cover_index=0'
```

## Testes e qualidade de código

Execute toda a suíte de testes:

```bash
composer test
```

Para verificar ou aplicar o padrão de formatação:

```bash
vendor/bin/pint --test
vendor/bin/pint
```

## Respostas HTTP

- `200`: consulta ou atualização realizada.
- `201`: recurso criado.
- `204`: exclusão ou logout realizado.
- `401`: credenciais ou token inválidos.
- `403`: usuário sem permissão.
- `404`: recurso não encontrado.
- `422`: dados inválidos.
- `429`: limite de requisições excedido.

Erros são retornados em JSON com os campos `type`, `title`, `status` e `message`. Erros de validação também incluem o objeto `errors`, agrupado por campo.

## Segurança

- Tokens pessoais e stateless com Laravel Sanctum.
- Login, criação de senha e cadastro possuem limite de 5 requisições por minuto.
- Operações de imagem possuem limite de 30 requisições por minuto.
- CORS limitado às origens configuradas.
- Senhas armazenadas com hash pelo cast do model `User`.
- Atributos persistidos somente após validação.
- Exceções inesperadas registradas pelo canal configurado em `LOG_CHANNEL`.
