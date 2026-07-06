# Notification Preferences API

System and per-user notification channel preferences.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/notification_preferences` | List authenticated user's preferences |
| PUT | `/api/v1/notification_preferences` | Bulk update user preferences |
| GET | `/api/v1/members/{id}/notification_preferences` | List member preferences (admin) |
| PUT | `/api/v1/members/{id}/notification_preferences` | Update member preferences (admin) |
| GET | `/api/v1/notification_settings` | List system notification settings |
| PUT | `/api/v1/notification_settings` | Update system notification settings |

## Authentication

| Endpoint | Ability |
|----------|---------|
| User preferences | `notification-preferences:read` / `notification-preferences:write` |
| Member preferences | `notification-preferences:read` / `notification-preferences:write` plus member admin permission |
| Notification settings | `notification-settings:read` / `notification-settings:write` with `notifications.manage` |

## Update preferences

```
PUT /api/v1/notification_preferences
```

Body: `{ "preferences": [ { "notification_type": "…", "channels": { "database": true, "email": false, … } } ] }`

Returns updated rows under `notification_preferences`.

## System settings

`GET/PUT /api/v1/notification_settings` configure system-wide defaults that apply unless overridden by user or member preferences. Admin UI: `/admin/settings/notifications`.
