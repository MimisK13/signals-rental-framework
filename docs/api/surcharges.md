# Surcharges API

Individual surcharges with per-surcharge tax classes (pricing pipeline Stage 3).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/surcharges` | List surcharges |
| GET | `/api/v1/surcharges/{id}` | Show surcharge |
| POST | `/api/v1/surcharges` | Create surcharge |
| PUT | `/api/v1/surcharges/{id}` | Update surcharge |
| DELETE | `/api/v1/surcharges/{id}` | Delete surcharge |

## Authentication

Requires `pricing:read` (GET) or `pricing:write` (POST/PUT/DELETE) with `pricing.manage` permission.

Toggle surcharge groups on opportunities: `POST /api/v1/opportunities/{id}/toggle_surcharge_group`. Override line surcharge: `POST /api/v1/opportunities/{id}/items/{item}/override_surcharge`.

Surcharge line snapshots on opportunities survive source surcharge deletion.

Collection key: `surcharges`; singular: `surcharge`.
