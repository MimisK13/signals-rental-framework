# Invoices API

CRUD and lifecycle endpoints for invoices — event-sourced billing documents with projected read models.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/invoices` | List invoices |
| GET | `/api/v1/invoices/{id}` | Show an invoice |
| POST | `/api/v1/invoices` | Create a draft invoice |
| PUT | `/api/v1/invoices/{id}` | Update a draft invoice |
| DELETE | `/api/v1/invoices/{id}` | Soft-delete a draft invoice |
| POST | `/api/v1/opportunities/{id}/invoices` | Checkout — generate invoice from opportunity |
| POST | `/api/v1/invoices/{id}/issue` | Issue a draft invoice |
| POST | `/api/v1/invoices/{id}/void` | Void an issued invoice |
| POST | `/api/v1/invoices/{id}/payments` | Record a payment |
| POST | `/api/v1/invoices/{id}/refunds` | Record a refund |
| POST | `/api/v1/invoices/{id}/credit_notes` | Issue a credit note |
| POST | `/api/v1/invoices/{id}/email` | Send billing email |
| GET | `/api/v1/invoices/{id}/pdf` | Download invoice PDF |

## Authentication

Requires a Sanctum bearer token:

| Ability | Operations |
|---------|------------|
| `invoices:read` | GET |
| `invoices:write` | POST, PUT, DELETE |

Matching permissions: `invoices.view`, `invoices.create`, `invoices.edit`, `invoices.delete`, `invoices.issue`, `invoices.void`, `invoices.credit`. Payments use `payments.record` with `payments:write`.

## Checkout from opportunity

```
POST /api/v1/opportunities/{id}/invoices
```

Generates a standard, deposit, or pro-forma draft invoice from the opportunity's active quote version with a **frozen tax snapshot**.

| Field | Type | Description |
|-------|------|-------------|
| `type` | string | `standard`, `deposit`, or `proforma` (default `standard`) |

Supports **`Idempotency-Key`** header — scoped to bearer token; replays 2xx/422 for 24 hours; 409 on payload mismatch; 425 while in-flight.

Requires `invoices:write` and `invoices.create`.

## List invoices

```
GET /api/v1/invoices
```

### Filters

Ransack predicates on: `number`, `type`, `status`, `member_id`, `store_id`, `opportunity_id`, `currency_code`, `issued_at`, `due_at`, `paid_at`, `created_at`, `updated_at`.

Example: `?q[status_eq]=issued&q[member_id_eq]=42`

### Includes

`?include=member,store,opportunity,items,payments,credit_notes`

### Sort

`sort=number`, `sort=total`, `sort=amount_outstanding`, `sort=issued_at`, `sort=created_at`

## Response shape

Single resource under `invoice` key. Money fields are **decimal strings**:

```json
{
    "invoice": {
        "id": 1,
        "number": "0000000123",
        "type": "standard",
        "status": "issued",
        "currency_code": "GBP",
        "subtotal": "1000.00",
        "tax_total": "200.00",
        "total": "1200.00",
        "amount_paid": "500.00",
        "amount_outstanding": "700.00",
        "tax_summary": [],
        "issued_at": "2026-07-01T10:00:00.000Z",
        "due_at": "2026-07-31T10:00:00.000Z"
    }
}
```

## Issue and void

```
POST /api/v1/invoices/{id}/issue
POST /api/v1/invoices/{id}/void
```

Issue transitions draft → issued and assigns document number. Void cancels an issued invoice per policy.

## Payments and credit notes

Payment and refund endpoints are documented on [Payments API](/docs/api/payments). Credit note listing is on [Credit Notes API](/docs/api/credit-notes).

## Webhooks

`invoice.created`, `invoice.issued`, `invoice.voided`, `invoice.overdue`, `payment.recorded`, `payment.refunded`, `credit_note.issued` — see [Webhooks](/docs/api/webhooks).
