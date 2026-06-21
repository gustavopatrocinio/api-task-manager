# Subscription Management API

API REST para gestão de assinaturas de clientes. Desenvolvida com Laravel 13, autenticação via Sanctum e banco SQLite para desenvolvimento local.

## Funcionalidades

- Autenticação com tokens Bearer (register, login, logout)
- Papéis `admin` e `customer`
- Catálogo de planos (admin gerencia, customer consulta planos ativos)
- Assinaturas com trial, cancelamento e controle por usuário
- Pagamentos locais com confirmação manual pelo admin
- Respostas de erro JSON padronizadas

## Requisitos

- PHP 8.3+
- Composer
- Extensão SQLite (`pdo_sqlite`)

## Instalação

```bash
git clone <seu-repo>
cd api-task-manager

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

A API fica disponível em `http://127.0.0.1:8000`.

## Dados iniciais

Após `php artisan db:seed`:

| Tipo | Email / Slug | Senha / Detalhe |
|------|----------------|-----------------|
| Admin | `admin@example.com` | `password` |
| Customer | `customer@example.com` | `password` |
| Plano Basic | slug: `basic` | R$ 29,90/mês |
| Plano Pro Mensal | slug: `pro-mensal` | R$ 99,90/mês, 7 dias trial |
| Plano Pro Anual | slug: `pro-anual` | R$ 999,90/ano, 14 dias trial |

## Autenticação

Obtenha um token via login:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"customer@example.com","password":"password"}'
```

Use o token retornado nas rotas protegidas:

```
Authorization: Bearer {token}
```

## Endpoints

### Auth

| Método | Rota | Auth |
|--------|------|------|
| POST | `/api/v1/auth/register` | Não |
| POST | `/api/v1/auth/login` | Não |
| POST | `/api/v1/auth/logout` | Sim |
| GET | `/api/v1/auth/me` | Sim |

### Plans (customer)

| Método | Rota | Auth |
|--------|------|------|
| GET | `/api/v1/plans` | Sim |
| GET | `/api/v1/plans/{id}` | Sim |

### Plans (admin)

| Método | Rota | Auth |
|--------|------|------|
| GET | `/api/v1/admin/plans` | Admin |
| POST | `/api/v1/admin/plans` | Admin |
| GET | `/api/v1/admin/plans/{id}` | Admin |
| PUT/PATCH | `/api/v1/admin/plans/{id}` | Admin |
| DELETE | `/api/v1/admin/plans/{id}` | Admin |
| PATCH | `/api/v1/admin/plans/{id}/activate` | Admin |

### Subscriptions

| Método | Rota | Auth |
|--------|------|------|
| GET | `/api/v1/subscriptions` | Sim |
| POST | `/api/v1/subscriptions` | Sim |
| GET | `/api/v1/subscriptions/{id}` | Sim |
| PUT/PATCH | `/api/v1/subscriptions/{id}` | Sim |
| DELETE | `/api/v1/subscriptions/{id}` | Sim |

Filtros no index (admin): `status`, `user_id`, `plan_id`

Body do POST (customer):

```json
{
  "plan_id": 1,
  "payment_method": "pix"
}
```

Body do POST (admin):

```json
{
  "plan_id": 1,
  "user_id": 2,
  "payment_method": "pix"
}
```

### Payments

| Método | Rota | Auth |
|--------|------|------|
| GET | `/api/v1/payments` | Sim |
| GET | `/api/v1/payments/{id}` | Sim |
| GET | `/api/v1/admin/payments` | Admin |
| GET | `/api/v1/admin/payments/{id}` | Admin |
| POST | `/api/v1/admin/payments/{id}/confirm` | Admin |
| POST | `/api/v1/admin/payments/{id}/fail` | Admin |

Ao assinar um plano, um pagamento `pending` é criado automaticamente. O admin confirma manualmente:

```json
{
  "notes": "Recebido via PIX"
}
```

## Fluxo típico

1. Customer faz login
2. Customer lista planos ativos (`GET /plans`)
3. Customer cria assinatura (`POST /subscriptions`)
4. Admin confirma pagamento (`POST /admin/payments/{id}/confirm`)
5. Assinatura passa para `active`

## Erros

Respostas de erro seguem o formato:

```json
{
  "message": "Descrição do erro",
  "errors": {}
}
```

| Status | Situação |
|--------|----------|
| 401 | Não autenticado |
| 403 | Sem permissão |
| 404 | Recurso não encontrado |
| 422 | Erro de validação |

## Testes

```bash
php artisan test
```

## Collection Insomnia

Importe o arquivo [`insomnia/Subscription-API.insomnia.json`](insomnia/Subscription-API.insomnia.json).

Configure no environment:

- `base_url`: `http://127.0.0.1:8000`
- `token`: preencher após login

## Estrutura

```
app/
├── Enums/           # Status e papéis
├── Http/
│   ├── Controllers/Api/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Policies/
├── Services/        # Regras de assinatura e pagamento
└── Support/         # Respostas de erro padronizadas
database/
├── migrations/
├── seeders/
└── factories/
tests/
├── Feature/
└── Unit/
```

## Banco de dados

Desenvolvimento usa SQLite (`database/database.sqlite`). Para MySQL, ajuste o `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_api
DB_USERNAME=root
DB_PASSWORD=
```

## Licença

MIT
