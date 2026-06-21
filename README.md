# Subscription Management API

REST API for managing customer subscriptions. Built with Laravel 13, Sanctum authentication, and SQLite for local development.

## Features

- Bearer token authentication (register, login, logout)
- `admin` and `customer` roles
- Plan catalog (admin manages, customers browse active plans)
- Subscriptions with trial periods, cancellation, and per-user access control
- Local payments with manual admin confirmation
- Standardized JSON error responses

## Requirements

- PHP 8.3+
- Composer
- SQLite extension (`pdo_sqlite`)

## Installation

```bash
git clone <your-repo>
cd api-task-manager

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

The API will be available at `http://127.0.0.1:8000`.

## Seed data

After running `php artisan db:seed`:

| Type | Email / Slug | Password / Details |
|------|--------------|-------------------|
| Admin | `admin@example.com` | `password` |
| Customer | `customer@example.com` | `password` |
| Basic plan | slug: `basic` | R$ 29.90/month |
| Pro Monthly | slug: `pro-mensal` | R$ 99.90/month, 7-day trial |
| Pro Yearly | slug: `pro-anual` | R$ 999.90/year, 14-day trial |

## Authentication

Obtain a token via login:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"customer@example.com","password":"password"}'
```

Use the returned token on protected routes:

```
Authorization: Bearer {token}
```

## Endpoints

### Auth

| Method | Route | Auth |
|--------|-------|------|
| POST | `/api/v1/auth/register` | No |
| POST | `/api/v1/auth/login` | No |
| POST | `/api/v1/auth/logout` | Yes |
| GET | `/api/v1/auth/me` | Yes |

### Plans (customer)

| Method | Route | Auth |
|--------|-------|------|
| GET | `/api/v1/plans` | Yes |
| GET | `/api/v1/plans/{id}` | Yes |

### Plans (admin)

| Method | Route | Auth |
|--------|-------|------|
| GET | `/api/v1/admin/plans` | Admin |
| POST | `/api/v1/admin/plans` | Admin |
| GET | `/api/v1/admin/plans/{id}` | Admin |
| PUT/PATCH | `/api/v1/admin/plans/{id}` | Admin |
| DELETE | `/api/v1/admin/plans/{id}` | Admin |
| PATCH | `/api/v1/admin/plans/{id}/activate` | Admin |

### Subscriptions

| Method | Route | Auth |
|--------|-------|------|
| GET | `/api/v1/subscriptions` | Yes |
| POST | `/api/v1/subscriptions` | Yes |
| GET | `/api/v1/subscriptions/{id}` | Yes |
| PUT/PATCH | `/api/v1/subscriptions/{id}` | Yes |
| DELETE | `/api/v1/subscriptions/{id}` | Yes |

Admin index filters: `status`, `user_id`, `plan_id`

POST body (customer):

```json
{
  "plan_id": 1,
  "payment_method": "pix"
}
```

POST body (admin):

```json
{
  "plan_id": 1,
  "user_id": 2,
  "payment_method": "pix"
}
```

### Payments

| Method | Route | Auth |
|--------|-------|------|
| GET | `/api/v1/payments` | Yes |
| GET | `/api/v1/payments/{id}` | Yes |
| GET | `/api/v1/admin/payments` | Admin |
| GET | `/api/v1/admin/payments/{id}` | Admin |
| POST | `/api/v1/admin/payments/{id}/confirm` | Admin |
| POST | `/api/v1/admin/payments/{id}/fail` | Admin |

When a customer subscribes to a plan, a `pending` payment is created automatically. The admin confirms it manually:

```json
{
  "notes": "Received via PIX"
}
```

## Typical flow

1. Customer logs in
2. Customer lists active plans (`GET /plans`)
3. Customer creates a subscription (`POST /subscriptions`)
4. Admin confirms the payment (`POST /admin/payments/{id}/confirm`)
5. Subscription status becomes `active`

## Errors

Error responses follow this format:

```json
{
  "message": "Error description",
  "errors": {}
}
```

| Status | Meaning |
|--------|---------|
| 401 | Unauthenticated |
| 403 | Forbidden |
| 404 | Resource not found |
| 422 | Validation error |

## Tests

```bash
php artisan test
```

## Insomnia collection

Import [`insomnia/Subscription-API.insomnia.json`](insomnia/Subscription-API.insomnia.json).

Set these environment variables:

- `base_url`: `http://127.0.0.1:8000`
- `token`: fill in after login

## Project structure

```
app/
├── Enums/           # Statuses and roles
├── Http/
│   ├── Controllers/Api/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Policies/
├── Services/        # Subscription and payment logic
└── Support/         # Standardized error responses
database/
├── migrations/
├── seeders/
└── factories/
tests/
├── Feature/
└── Unit/
```

## Database

Local development uses SQLite (`database/database.sqlite`). For MySQL, update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_api
DB_USERNAME=root
DB_PASSWORD=
```

## License

MIT
