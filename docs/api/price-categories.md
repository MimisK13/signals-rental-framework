# Price Categories API

Price categories and tiers threaded through the rate engine (pricing pipeline Stage 1).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/price_categories` | List price categories |
| GET | `/api/v1/price_categories/{id}` | Show category |
| POST | `/api/v1/price_categories` | Create category |
| PUT | `/api/v1/price_categories/{id}` | Update category |
| DELETE | `/api/v1/price_categories/{id}` | Delete category |

## Authentication

Requires `pricing:read` (GET) or `pricing:write` (POST/PUT/DELETE) with `pricing.manage` permission.

## Tiers

Categories include tier definitions consumed by the rate engine when resolving line prices. Configure tiers in the request body on create/update.

Collection key: `price_categories`; singular: `price_category`.
