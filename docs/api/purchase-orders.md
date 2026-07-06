# Purchase Orders API

Event-sourced purchase orders for sub-hire procurement with send, receive, and cancel lifecycle.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/purchase_orders` | List purchase orders |
| GET | `/api/v1/purchase_orders/{id}` | Show a purchase order |
| POST | `/api/v1/purchase_orders` | Create draft PO |
| PUT | `/api/v1/purchase_orders/{id}` | Update draft PO |
| DELETE | `/api/v1/purchase_orders/{id}` | Delete draft PO |
| POST | `/api/v1/purchase_orders/{id}/send` | Send to supplier |
| POST | `/api/v1/purchase_orders/{id}/receive` | Record receipt |
| POST | `/api/v1/purchase_orders/{id}/cancel` | Cancel PO |

## Authentication

Requires `purchase_orders:read` (GET) or `purchase_orders:write` (POST/PUT/DELETE) with `purchase_orders.view`, `purchase_orders.create`, `purchase_orders.edit`, `purchase_orders.delete`, `purchase_orders.send`, `purchase_orders.receive` permissions as applicable.

Cost fields require **`costs.view`** — users without it see POs without cost breakdown.

## List purchase orders

```
GET /api/v1/purchase_orders
```

### Filters

`number`, `status`, `po_type`, `supplier_id`, `store_id`, `opportunity_id`, `expected_at`, `starts_at`, `ends_at`, `sent_at`, `received_at`, `cancelled_at`, `created_at`, `updated_at`.

### Includes

`?include=supplier,store,opportunity,items`

## Receive

```
POST /api/v1/purchase_orders/{id}/receive
```

Records goods received and triggers virtual stock intake workflow. Body validates receipt lines and quantities per `ReceivePurchaseOrderData`.

## Response shape

Money totals as decimal strings. Collection key: `purchase_orders`; singular: `purchase_order`.

## Webhooks

`purchase_order.created`, `purchase_order.sent`, `purchase_order.received`, `purchase_order.cancelled`.
