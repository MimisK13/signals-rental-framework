# Surcharge Groups API

Group optional surcharges for toggling on opportunities (pricing pipeline Stage 3).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/surcharge_groups` | List surcharge groups |
| GET | `/api/v1/surcharge_groups/{id}` | Show group |
| POST | `/api/v1/surcharge_groups` | Create group |
| PUT | `/api/v1/surcharge_groups/{id}` | Update group |
| DELETE | `/api/v1/surcharge_groups/{id}` | Delete group |

## Authentication

Requires `pricing:read` (GET) or `pricing:write` (POST/PUT/DELETE) with `pricing.manage` permission.

Collection key: `surcharge_groups`; singular: `surcharge_group`.
