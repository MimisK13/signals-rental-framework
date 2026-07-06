# Communication Log API

Read-only log of dispatched customer communications.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/communication_log` | List log entries |
| GET | `/api/v1/communication_log/{id}` | Show log entry |

## Authentication

Requires `communications:read` ability and `notifications.manage` permission.

## List entries

```
GET /api/v1/communication_log
```

Ransack filters on recipient, channel, template, status, documentable, and timestamps. Read-only — entries are created by the dispatch pipeline, not via API POST.

Collection key: `communication_log`; singular entry in show response per controller convention.

See [Notifications & Comms platform doc](/docs/platform/notifications).
