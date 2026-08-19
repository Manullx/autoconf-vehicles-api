# Autoconf Vehicles API

API REST para gerenciamento de usuários, veículos e imagens, desenvolvida com Laravel 12. A aplicação utiliza autenticação stateless por Bearer token com Laravel Sanctum e possui controle de acesso entre administradores e usuários comuns.

## Funcionalidades

- Autenticação com tokens pessoais do Laravel Sanctum.
- Registro público com senha e fluxo administrativo de primeiro acesso por senha temporária.
- Administração de usuários.
- Listagem, busca, ordenação e paginação de veículos.
- Controle de acesso aos veículos por proprietário.
- Cadastro de veículo com 1 a 5 imagens de no máximo 2 MB cada.
- Garantia de exatamente uma imagem de capa por veículo.
- Inclusão, exclusão e alteração da capa das imagens.
- Auditoria de criação e última atualização dos veículos.
- Exclusão definitiva do veículo, dos registros e dos arquivos relacionados.
- Respostas de erro padronizadas em JSON.
- Contrato OpenAPI 3.1 disponível pela própria API.

## Requisitos

- PHP 8.2 ou superior.
- Composer 2.
- Extensões PHP exigidas pelo Laravel, incluindo `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `tokenizer` e `xml`.
- SQLite, MySQL ou PostgreSQL.

Para uploads de imagens, configure `upload_max_filesize` com pelo menos `2M`. Como uma requisição pode conter até cinco imagens, configure `post_max_size` com pelo menos `12M` para comportar os arquivos e os demais campos.

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

CORS_ALLOWED_ORIGINS=http://localhost:5173
SANCTUM_EXPIRATION=120
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

Mais de uma origem CORS pode ser informada separando os endereços por vírgulas. `SANCTUM_EXPIRATION` define, em minutos, a validade dos tokens pessoais. Em produção, use `APP_DEBUG=false` e não configure `*` como origem permitida.

### Banco de dados e dados iniciais

Execute as migrations e os seeders:

```bash
php artisan migrate --seed
```

O seeder cria:

- Um administrador com o nome e e-mail configurados no `.env`.
- Dez veículos de demonstração com imagens locais.

Na primeira execução, uma senha temporária segura é exibida no terminal. Ela é obrigatória no primeiro login. Quando o seeder é executado novamente, a senha atual é preservada e a flag `first_login` do administrador volta para `true`.

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

### Registro público

O registro público cria um usuário comum já com a senha definitiva:

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Maria","email":"maria@example.com","password":"senha-segura","password_confirmation":"senha-segura"}'
```

O usuário criado possui `is_admin: false` e `first_login: false`. O endpoint ignora tentativas de enviar privilégios administrativos.

### Primeiro acesso de usuários criados por administrador

Administradores criam usuários em `POST /api/users`. A resposta dessa operação contém uma `temporary_password`, e o novo usuário possui `first_login: true`. O administrador deve transmitir essa senha temporária ao usuário por um canal seguro.

No primeiro login, o e-mail e a senha temporária são obrigatórios:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"SENHA_TEMPORARIA"}'
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

### Definição da senha definitiva

Enquanto `first_login` for `true`, use o token recebido no login para definir a senha definitiva. A confirmação deve ser igual e a senha precisa ter no mínimo 8 caracteres:

```bash
curl -X POST http://localhost:8000/api/auth/password \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer TOKEN_GERADO' \
  -d '{"password":"nova-senha","password_confirmation":"nova-senha"}'
```

Após essa operação, `first_login` passa para `false`, os demais tokens emitidos com a senha temporária são revogados e esse endpoint deixa de aceitar novas alterações para o usuário. Usuários fora do fluxo de primeiro acesso não podem trocar a senha sem comprovar a senha atual.

Até a conclusão dessa etapa, o token pode acessar somente `/api/auth/me`, `/api/auth/password` e `/api/auth/logout`; as rotas de usuários, veículos e imagens respondem com `403`.

### Login regular

Todo login exige a senha correta, independentemente do valor de `first_login`:

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

As rotas de documentação, registro público e login não exigem autenticação. As demais recebem um Bearer token.

| Método | Rota | Descrição |
| --- | --- | --- |
| `GET` | `/api/docs` | Retorna o contrato OpenAPI em YAML |
| `POST` | `/api/auth/login` | Autentica e gera um token |
| `POST` | `/api/auth/register` | Registra publicamente um usuário comum |
| `POST` | `/api/auth/password` | Define a senha do usuário autenticado |
| `POST` | `/api/auth/logout` | Revoga o token atual |
| `GET` | `/api/auth/me` | Retorna o usuário autenticado |
| `GET/POST` | `/api/users` | Lista ou cria usuários |
| `GET/PUT/PATCH/DELETE` | `/api/users/{user}` | Gerencia um usuário |
| `GET/POST` | `/api/vehicles` | Lista ou cria veículos |
| `GET/PUT/PATCH/DELETE` | `/api/vehicles/{vehicle}` | Gerencia um veículo |
| `POST` | `/api/vehicles/{vehicleId}/images` | Adiciona imagens |
| `PATCH` | `/api/vehicles/{vehicleId}/images/{imageId}/cover` | Define a imagem de capa |
| `DELETE` | `/api/vehicles/{vehicleId}/images/{imageId}` | Exclui uma imagem e seu arquivo |

O contrato completo está no repositório em [`docs/openapi.yaml`](docs/openapi.yaml) e é servido localmente em `http://localhost:8000/api/docs`, sem depender de CDN ou conexão externa.

## Cadastro de veículos com imagens

O cadastro utiliza `multipart/form-data`. `files` é obrigatório e aceita de uma a cinco imagens JPG, JPEG, PNG ou WebP, com no máximo 2 MB por arquivo. `cover_index` é obrigatório e indica a posição da capa no array, começando em `0`.

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

Uploads posteriores respeitam o limite total de cinco imagens do veículo. A capa excluída é substituída automaticamente por outra imagem, e a única imagem de um veículo não pode ser excluída. Dessa forma, todo veículo disponível pela API mantém exatamente uma capa.

Os campos `created_by` e `updated_by` guardam os identificadores dos responsáveis pela criação e pela última alteração. O detalhe também inclui os objetos resumidos `creator` e `updater`; alterações de dados, upload, troca de capa e exclusão de imagem atualizam o responsável e `updated_at`.

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

- Tokens pessoais e stateless com Laravel Sanctum, com validade configurável e padrão de 120 minutos.
- Login, criação de senha e cadastro possuem limite de 5 requisições por minuto.
- Operações de imagem possuem limite de 30 requisições por minuto.
- CORS limitado às origens configuradas.
- Senhas armazenadas com hash pelo cast do model `User`.
- Atributos persistidos somente após validação.
- Login sempre exige senha, inclusive durante o primeiro acesso.
- Limite de imagens e unicidade da capa são revalidados dentro de transações com bloqueio do veículo.
- Exceções inesperadas registradas pelo canal configurado em `LOG_CHANNEL`.
