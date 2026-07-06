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

## Using it in the app

### Admin notification settings

**Route:** `/admin/settings/notifications` (Admin → Preferences → Notifications)  
**Permission:** `notifications.manage` on mount and all actions

Header action **Communication log** links to `/admin/settings/communication-log` (no sidebar entry).

#### Channel providers

Card per registered driver (In-App, Real-Time, Email) showing status badge (Tested / Failing / Untested).

| Action | Purpose |
|--------|---------|
| Configure | Slide-over with provider fields (SMTP host, credentials masked on save, and so on) |
| Send test | Fires test delivery; email providers accept an optional test recipient |

#### Notification types matrix

Types group by category. Each row shows name, description, Required badge, audience (Internal / External / Both), and an enable switch.

For each audience channel row:

| Control | Purpose |
|---------|---------|
| In-App / Real-Time / Email toggles | Default channels for that type |
| Edit rules | Recipient rules modal — contact, role, specific member, subject owner, trigger actor |
| Template link | Opens communication template editor for that type |

Footer settings: log retention days, prune safety window, unsubscribe link lifetime (days).

#### Communication template editor

**Route:** `/admin/settings/communication-templates/{template}/edit`

| Panel | Contents |
|-------|----------|
| Subject + Body | Markdown body with merge-field browser (search + insert) |
| SMS stats | Segment counter when SMS channel applies |
| Version history | Snapshots with change notes; **Restore** |
| Live preview | Sample vs real entity; optional opportunity picker; rendered subject/body/HTML |

System templates are read-only — **Duplicate to customise** creates an editable copy.

### Header notification bell

The header hosts `<livewire:notifications.bell />` for every authenticated user with a linked member.

| Element | Behaviour |
|---------|-----------|
| Bell icon | Unread count badge (caps at 99+) |
| Slide-out pane | Title "Notifications", paginated list, relative timestamps |
| Mark all read / per-item Read | Clears database notifications |
| Footer link | **Notification preferences** → `/settings/notifications` |

Real-time updates arrive via **Reverb** on the user's private channel; the bell also polls every 60 seconds.

### My Notifications (user settings)

**Route:** `/settings/notifications` (Settings sidebar → Notifications)

Per notification type (grouped by category):

- **Mute** checkbox
- Channel toggles — only channels your organisation has enabled (In-app, Email, SMS, Slack, Teams)

Click **Save** to persist preferences. Users cannot enable channels the tenant has disabled.

### Communication log (admin)

**Route:** `/admin/settings/communication-log`  
**Permission:** `notifications.manage` + store scoping on opportunity subjects

**Filters:** Status, Channel, Type, Recipient, From, To — **Clear filters**

**Table:** When, Type, Recipient, Channel, Status, Subject, View

**Detail modal:** rendered body, status timeline (Queued / Sent / Delivered / Opened), **Pin / Unpin**, failure reason callout

The same log table partial appears on **invoice Communications**, **opportunity Communications**, and **member Communications** tabs — scoped to that subject.

### Opportunity and member Communications tabs

| Tab | Route | Gate |
|-----|-------|------|
| Opportunity | `/opportunities/{opportunity}/communications` | `opportunities.view` |
| Member | `/members/{member}/communications` | Member view policy |

Both render paginated communication log entries for that subject.

### Public unsubscribe

Signed URLs at `GET /communications/unsubscribe/{member}?type={notification_type_key}`.

The page shows masked email, explains the opt-out, and offers an **Unsubscribe** button (POST with CSRF). Success state confirms removal. Link lifetime follows **Unsubscribe link lifetime (days)** on the notifications admin page.

### Permissions summary

| Permission | UI effect |
|------------|-----------|
| `notifications.manage` | Admin notifications, communication log, template editor |
| Authenticated user | Bell, pane, `/settings/notifications` |
| `members.view` | Member communications tab (via member access) |

## Related documentation

- [Invoicing](/docs/platform/invoicing) — billing email triggers
- [Documents & Templates](/docs/platform/documents) — PDF attachments in emails
- [Admin Panel](/docs/platform/admin-panel) — notification and communication template settings
