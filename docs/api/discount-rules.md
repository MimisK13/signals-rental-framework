# Discount Rules API

JSONB criteria discount rules with auto-apply for the pricing pipeline Stage 2.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/discount_rules` | List rules |
| GET | `/api/v1/discount_rules/{id}` | Show rule |
| POST | `/api/v1/discount_rules` | Create rule |
| PUT | `/api/v1/discount_rules/{id}` | Update rule |
| DELETE | `/api/v1/discount_rules/{id}` | Delete rule |

## Authentication

Requires `pricing:read` (GET) or `pricing:write` (POST/PUT/DELETE) with `pricing.manage` permission.

## Rule criteria

Rules store JSONB **criteria** evaluated by `CriteriaMatcher` against opportunity context (member, dates, products, totals, etc.). Set `auto_apply` to evaluate on recalculation without manual application.

Collection key: `discount_rules`; singular: `discount_rule`.
