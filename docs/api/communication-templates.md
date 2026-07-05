# Communication Templates API

Merge-field communication templates for billing and customer emails with version restore.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/communication_templates` | List templates |
| GET | `/api/v1/communication_templates/{id}` | Show template |
| POST | `/api/v1/communication_templates` | Create template |
| PUT | `/api/v1/communication_templates/{id}` | Update template |
| DELETE | `/api/v1/communication_templates/{id}` | Delete template |
| GET | `/api/v1/communication_templates/{id}/versions` | List version snapshots |
| POST | `/api/v1/communication_templates/{id}/versions/{version}/restore` | Restore version |

## Authentication

Requires `communication-templates:read` (GET) or `communication-templates:write` (mutations) with `notifications.manage` permission.

## Templates

Templates use merge-field syntax for billing communications (invoice email, receipt, statement). Updates create version snapshots restorable via the versions endpoints.

Collection key: `communication_templates`; singular: `communication_template`.

Admin editor: `/admin/settings/communication-templates/{template}/edit`.
