# Documents API

Generate, finalise, void, and share PDF/HTML documents from documentable sources (invoices, credit notes, opportunities, purchase orders, etc.).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/documents` | List generated documents |
| GET | `/api/v1/documents/{id}` | Show a document |
| POST | `/api/v1/documents/generate` | Generate a document |
| POST | `/api/v1/documents/batch_generate` | Queue batch generation |
| POST | `/api/v1/documents/{id}/finalise` | Finalise rendered output |
| POST | `/api/v1/documents/{id}/void` | Void a document |
| POST | `/api/v1/documents/{id}/public_link` | Create public share link |
| PUT | `/api/v1/documents/{id}/public_link` | Configure PIN/expiry on link |
| DELETE | `/api/v1/documents/{id}/public_link` | Revoke public link |
| GET | `/api/v1/document_types` | List document types |
| GET | `/api/v1/document_types/{type}/fields` | Field browser for template type |

## Authentication

| Ability | Operations |
|---------|------------|
| `documents:read` | GET |
| `documents:write` | POST generate, finalise, void, public_link |

Permissions: `documents.view`, `documents.create`, `documents.share`.

## Generate document

```
POST /api/v1/documents/generate
```

| Field | Type | Description |
|-------|------|-------------|
| `documentable_type` | string | Source model type |
| `documentable_id` | integer | Source record ID |
| `document_type` | string | Template type key |
| `store_id` | integer | Store for template/branding |
| `async` | boolean | Queue on exports queue (202 + job_id) |

Returns `201` with `document` or `202` with `job_id` when async.

## List documents

```
GET /api/v1/documents
```

Filters: `store_id`, `document_type`, `status`, `documentable_type`, `documentable_id`, `document_number`, `created_at`.

Alias: `q[type_eq]` maps to `document_type`.

Includes download URL when the caller may view the document.

## Public links

Public URLs use `{app-url}/d/{token}` (configurable prefix). Optional PIN and expiry configured via PUT on public_link.

## Response shape

```json
{
    "document": {
        "id": 1,
        "document_number": "INV-000123",
        "document_type": "invoice",
        "status": "finalised",
        "download_url": "https://…",
        "public_link": {
            "token": "…",
            "expires_at": "2026-08-01T00:00:00.000Z",
            "requires_pin": true
        }
    }
}
```

Finalised documents regenerate from `rendered_data` snapshots.

## Webhooks

`document.generated`, `document.finalised`, `document.voided`, `pdf_generated`.
