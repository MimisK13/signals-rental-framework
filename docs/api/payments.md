# Payments API

Record payments and refunds against **issued invoices**. There is no standalone `GET /api/v1/payments` index — list payments through invoice includes or the Finance UI.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/api/v1/invoices/{id}/payments` | Record a payment |
| POST | `/api/v1/invoices/{id}/refunds` | Record a refund |

## Authentication

Requires `payments:write` ability and `payments.record` permission.

## Record payment

```
POST /api/v1/invoices/{id}/payments
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `amount` | string | Yes | Decimal string in invoice currency |
| `paid_at` | string | No | ISO 8601 datetime (defaults to now) |
| `method` | string | No | Payment method reference |
| `reference` | string | No | External reference (e.g. bank ref) |
| `notes` | string | No | Internal notes |

Supports **`Idempotency-Key`** header (same semantics as checkout).

### Response

Returns updated invoice under `invoice` key with refreshed `amount_paid` and `amount_outstanding`.

## Record refund

```
POST /api/v1/invoices/{id}/refunds
```

Same body shape as payments. Reduces net paid amount and increases outstanding balance.

## Including payments on invoice show

```
GET /api/v1/invoices/{id}?include=payments
```

Each payment includes `amount` as a decimal string, `paid_at`, `method`, and `reference`.

## Webhooks

`payment.recorded` and `payment.refunded` payloads embed payment DTO fields — see [Webhooks](/docs/api/webhooks) and [Invoices API](/docs/api/invoices) for field reference.
