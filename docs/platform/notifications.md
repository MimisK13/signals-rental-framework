---
title: Notifications & Comms
description: Notification channels, communication templates, customer emails, preferences, and the communication log.
---

## Overview

The **Notification & Communication Engine** delivers multi-channel notifications (database, email, broadcast) with a three-layer resolution pipeline: system defaults → user preferences → per-send overrides. Customer billing communications persist to **`communication_log`** with opt-out and unsubscribe support.

## Channel provider registry

Channels register through a **provider registry**:

| Built-in channel | Purpose |
|------------------|---------|
| **Database** | In-app notification records |
| **Email** | Queued SMTP/email driver delivery |
| **Broadcast** | Real-time via Reverb |

Plugins can register additional channel providers via the Plugin SDK.

Test a provider configuration: `POST /api/v1/channel_providers/{channel_provider}/test`.

## Three-layer resolution

1. **System settings** — `/admin/settings/notifications` defines default channels per notification type
2. **User preferences** — per-user overrides in profile settings and via API
3. **Dispatch-time overrides** — action classes can force channels for critical sends

Event→notification wiring includes a **replay guard** so projection replays do not duplicate sends.

## Notification bell

The header **notification bell** loads database notifications and refreshes on open. Real-time updates arrive via **Reverb** on the user's private channel. Event-specific bodies replace generic placeholders.

## Communication templates

**Route:** `/admin/settings/communication-templates/{template}/edit`

Merge-field templates power billing emails (invoice, receipt, statement) and customer communications. Templates support **version snapshots and restore**, parallel to document templates.

API: [Communication Templates](/docs/api/communication-templates) — CRUD plus version restore.

## Customer communications

Billing actions dispatch customer emails through `DispatchBillingCustomerCommunication`:

- Invoice issued / emailed
- Payment receipt
- Member statement

Each send is logged in **`communication_log`**. Customers can **opt out** via signed unsubscribe links.

| Setting | Key | Purpose |
|---------|-----|---------|
| Unsubscribe URL TTL | `notifications.unsubscribe_url_ttl_days` | Signed link expiry (default 90 days) |
| Prune safety window | Effective prune respects TTL + safety margin | Prevents premature log deletion |

## Communication log

Administrators with appropriate permissions can browse dispatched communications — channel, template, recipient, status, and timestamps. Read-only API: [Communication Log](/docs/api/communication-log).

## Notification preferences API

| Endpoint | Purpose |
|----------|---------|
| `GET/PUT /api/v1/notification_preferences` | Authenticated user's preferences |
| `GET/PUT /api/v1/members/{id}/notification_preferences` | Member-level preferences (admin) |
| `GET/PUT /api/v1/notification_settings` | System-wide notification settings |

See [Notification Preferences API](/docs/api/notification-preferences).

## Related documentation

- [Invoicing](/docs/platform/invoicing) — billing email triggers
- [Documents & Templates](/docs/platform/documents) — PDF attachments in emails
- [Admin Panel](/docs/platform/admin-panel) — notification and communication template settings
