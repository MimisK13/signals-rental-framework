# Document Sequences API

Read and update store-scoped numbering sequences for invoices, credit notes, opportunities, members, payments, purchase orders, and other numbered entities.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/document_sequences` | List sequences |
| PUT | `/api/v1/document_sequences/{id}` | Update sequence configuration |

## Authentication

Requires `documents:read` (GET) or `documents:write` (PUT) with appropriate document/numbering permissions.

## List sequences

```
GET /api/v1/document_sequences
```

Returns sequences for the current store scope with entity type, prefix, padding, next number preview, and collision guard metadata.

## Update sequence

```
PUT /api/v1/document_sequences/{id}
```

Update prefix, padding, suffix, or next-value configuration. Live preview in admin UI mirrors API behaviour — see [Documents & Templates platform doc](/docs/platform/documents).

## Concurrency

Number assignment uses collision guards so concurrent issue/checkout operations cannot duplicate numbers within a store.
