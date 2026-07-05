# Discount Categories API

Manage discount category groupings used by the pricing pipeline Stage 2.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/discount_categories` | List categories |
| GET | `/api/v1/discount_categories/{id}` | Show category |
| POST | `/api/v1/discount_categories` | Create category |
| PUT | `/api/v1/discount_categories/{id}` | Update category |
| DELETE | `/api/v1/discount_categories/{id}` | Delete category |

## Authentication

Requires `pricing:read` (GET) or `pricing:write` (POST/PUT/DELETE) with `pricing.manage` permission.

## List categories

```
GET /api/v1/discount_categories
```

Ransack filters and offset pagination. Collection key: `discount_categories`.

Apply a category to an opportunity via `POST /api/v1/opportunities/{id}/apply_discount_category` — see [Opportunities API](/docs/api/opportunities).
