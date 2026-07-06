# Credit Notes API

Read-only endpoints for issued credit notes. Credit notes are created via invoice nested routes, not direct POST to this collection.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/credit_notes` | List credit notes |
| GET | `/api/v1/credit_notes/{id}` | Show a credit note |
| POST | `/api/v1/invoices/{id}/credit_notes` | Issue credit note (on [Invoices API](/docs/api/invoices)) |

## Authentication

Requires `credit-notes:read` (GET) or `invoices:write` with `invoices.credit` permission to issue via invoice route.

## List credit notes

```
GET /api/v1/credit_notes
```

### Filters

Ransack on: `number`, `status`, `member_id`, `store_id`, `invoice_id`, `currency_code`, `issued_at`, `created_at`, `updated_at`.

### Includes

`?include=member,store,invoice,items`

## Response shape

```json
{
    "credit_note": {
        "id": 1,
        "number": "0000000045",
        "status": "issued",
        "invoice_id": 12,
        "currency_code": "GBP",
        "subtotal": "-100.00",
        "tax_total": "-20.00",
        "total": "-120.00"
    }
}
```

Tax mirrors the source invoice with negated amounts. All money fields are decimal strings.

## Webhooks

`credit_note.issued` — see [Webhooks](/docs/api/webhooks).
