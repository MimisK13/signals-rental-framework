# Document Templates API

CRUD for per-store sandboxed Blade document templates with versioning, defaults, and import/export.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/document_templates` | List templates |
| POST | `/api/v1/document_templates` | Create template |
| GET | `/api/v1/document_templates/{id}` | Show template |
| PUT | `/api/v1/document_templates/{id}` | Update template |
| DELETE | `/api/v1/document_templates/{id}` | Delete template |
| POST | `/api/v1/document_templates/{id}/duplicate` | Duplicate template |
| POST | `/api/v1/document_templates/{id}/set_default` | Set as store/type default |
| POST | `/api/v1/document_templates/{id}/restore_default` | Restore system default content |
| POST | `/api/v1/document_templates/import` | Import from export file |
| GET | `/api/v1/document_templates/{id}/export` | Export template JSON |
| POST | `/api/v1/document_templates/{id}/versions/{version}/restore` | Restore version snapshot |

## Authentication

Requires `documents:read` (GET) or `documents:write` (mutations) with `document_templates.view` / `document_templates.manage` permissions.

## List templates

```
GET /api/v1/document_templates
```

Filters: `store_id`, `document_type`, `is_active`, `is_default`, `name`, `slug`. Aliases: `type`, `active`, `default`.

Sort: `name`, `document_type`, `version`, `last_edited_at`, `created_at`.

## Create / update

Body includes `name`, `document_type`, `store_id`, `body` (sandboxed Blade source), and optional `is_active`.

Updates bump version history automatically.

## Import / export

Export returns portable JSON for backup or migration. Import creates or updates from the same format via `POST /api/v1/document_templates/import`.

## Response shape

Collection under `document_templates` key; single resource under `document_template`.
