# Tax Zones API

Manage zone-aware tax configuration, buyer types, and zone rules for EU/UK compliance.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/tax_zones` | List tax zones |
| GET | `/api/v1/tax_zones/{id}` | Show zone with rules |
| POST | `/api/v1/tax_zones` | Create zone |
| PUT | `/api/v1/tax_zones/{id}` | Update zone |
| DELETE | `/api/v1/tax_zones/{id}` | Delete zone |

## Authentication

Requires `tax-classes:read` (GET) or `tax-classes:write` (POST/PUT/DELETE) with tax administration permissions.

## Zone rules

Each zone includes rules mapping **buyer type** and **product tax category** to treatments (standard, reverse charge, zero-rated, exempt, outside scope). See [Tax & Compliance platform doc](/docs/platform/tax).

Admin UI: `/admin/settings/tax/zones`.

Collection key: `tax_zones`; singular: `tax_zone`.
