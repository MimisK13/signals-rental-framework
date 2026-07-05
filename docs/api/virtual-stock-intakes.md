# Virtual Stock Intakes API

Virtual stock intakes represent supplier stock entering the availability pool as signed supply.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/virtual_stock_intakes` | List intakes |
| GET | `/api/v1/virtual_stock_intakes/{id}` | Show intake |
| POST | `/api/v1/virtual_stock_intakes` | Create draft intake |
| PUT | `/api/v1/virtual_stock_intakes/{id}` | Update draft intake |
| DELETE | `/api/v1/virtual_stock_intakes/{id}` | Delete draft intake |
| POST | `/api/v1/virtual_stock_intakes/{id}/confirm` | Confirm intake |
| POST | `/api/v1/virtual_stock_intakes/{id}/receive` | Record receipt |
| POST | `/api/v1/virtual_stock_intakes/{id}/return` | Return to supplier |
| POST | `/api/v1/virtual_stock_intakes/{id}/cancel` | Cancel intake |
| POST | `/api/v1/virtual_stock_intakes/{id}/apportion` | Run cost apportionment |
| PUT | `/api/v1/virtual_stock_intakes/{id}/cost_overrides` | Set per-opportunity cost override |
| DELETE | `/api/v1/virtual_stock_intakes/{id}/cost_overrides/{opportunity}` | Clear cost override |

## Authentication

Requires `virtual_stock:read` (GET) or `virtual_stock:write` (mutations). Cost fields gated by **`costs.view`**.

## List intakes

```
GET /api/v1/virtual_stock_intakes
```

Standard Ransack filters on status, supplier, store, opportunity, dates. Includes: `supplier`, `store`, `opportunity`, `items`, `purchase_order`.

## Apportionment

```
POST /api/v1/virtual_stock_intakes/{id}/apportion
```

Runs one of five apportionment strategies across linked opportunities. Date changes on opportunities trigger recalculation via the cost event stream.

## Response shape

Collection key: `virtual_stock_intakes`; singular: `virtual_stock_intake`. Money as decimal strings.

## Webhooks

`virtual_stock_intake.created`, `virtual_stock_intake.confirmed`, `virtual_stock_intake.received`, `virtual_stock_intake.returned`, `virtual_stock_intake.cancelled`.
