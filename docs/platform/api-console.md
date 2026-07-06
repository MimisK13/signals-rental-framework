---
title: API Console
description: Monitor API activity, explore endpoints interactively, review weighted usage, and manage outbound and inbound webhooks.
---

## Overview

The **API Console** is a dedicated section of the web UI for operators and integrators who need visibility into API traffic, billing-oriented usage metrics, and webhook configuration. It lives under the **API** sidebar section (gated on `api.access` and finer-grained permissions per area) and is reachable at `/api`.

The console summary page shows rolling **7-day** request and weighted-unit totals, your personal API token count, active outbound webhook subscriptions, and inbound webhook source count. From there you can jump into four areas: **Explorer**, **Request Log**, **Usage**, and **Webhooks**.

| Area | Route | Permission |
|------|-------|------------|
| Summary | `/api` | `api.access` |
| Explorer | `/api/explorer` | `api.explorer.use` |
| Request Log | `/api/logs` | `api.logs.view` |
| Usage | `/api/usage` | `api.usage.view` |
| Webhooks | `/api/webhooks` | `webhooks.manage` |

## Explorer

**Route:** `/api/explorer`

The Explorer is an interactive request builder backed by the same endpoint catalogue the metering system uses (`ApiEndpointCatalogue`). Pick a resource group and endpoint, configure query parameters and custom headers, choose an API token (or paste a raw bearer token), and send the request without leaving the browser.

### Token selection

- **Saved token** — select one of your Sanctum personal access tokens from the dropdown. The Explorer uses that token's abilities when issuing the request.
- **Raw token** — paste a bearer token directly (useful for testing tokens created elsewhere).

### Ephemeral token behaviour

When you execute a request with a **saved** token, the Explorer creates a short-lived **ephemeral** Sanctum token that copies the selected token's abilities, uses it for the single HTTP call, and **deletes it immediately afterward**. Your saved token is never sent to the browser's network layer in plain form for the actual request — only a masked preview is shown in the UI.

Responses display status code, timing, and formatted JSON. Explorer requests are logged like any other API call and carry the endpoint's declared price weight.

## Request Log

**Route:** `/api/logs`

The Request Log browses **detail rows** for all metered traffic. Three channels are supported:

| Channel | Description |
|---------|-------------|
| `api` | Inbound REST API requests (via `LogApiRequest` middleware) |
| `webhook_sent` | Outbound webhook delivery attempts (`DeliverWebhook` job) |
| `webhook_received` | Inbound webhook posts to `/webhooks/receive/{source}` |

Each row shows method, URI or event, status, duration, byte counts, price weight, weighted units, token (API channel), and timestamp. Expand a row to inspect captured headers and bodies when body capture is enabled in config.

### Filters

- **Date window** — `24h`, `7d`, `30d`, or **Custom** (from/to dates), using the same `DateWindow` presets as Usage
- **Status class** — success, client error, server error (derived from HTTP status)
- **Token** — filter API requests by personal access token (API channel only)
- **Method** — filter API requests by HTTP method (API channel only)

Filter state is persisted in the URL. **Export CSV** queues an asynchronous export job filtered to the active channel and filters.

Detail rows are retained for `detail_retention_days` (default 90) and pruned by the `api:prune-logs` scheduled command. Permanent rollups live in `usage_daily`.

## Usage dashboard

**Route:** `/api/usage`

The Usage dashboard reads **aggregated** data from the `usage_daily` table — not the detail log. It answers "how much happened, and how much did it cost in weighted units?" for a chosen window.

### Date windows

| Preset | Range |
|--------|-------|
| `24h` | Today from midnight through now |
| `7d` | Last 7 calendar days (default) |
| `30d` | Last 30 calendar days |
| `Custom` | Explicit from/to dates (inclusive) |

Windows match the `DateWindow` helper used by the Usage API.

### Channel filter

Filter to **All**, **API**, **Webhook sent**, or **Webhook received**, or leave on All to combine channels.

### Breakdowns

The dashboard surfaces:

- **Summary totals** — request count and weighted units for the window
- **Daily trend** — requests per day
- **By token** — API traffic grouped by personal access token
- **By endpoint / event** — top endpoints (API) or events (webhooks)
- **By resource** — grouped by API resource or webhook event group
- **By status class** — success vs client vs server errors

**Export CSV** queues the same aggregates via the `ExportApiUsage` job. The REST equivalent is `GET /api/v1/usage/export`.

## Webhooks

**Route:** `/api/webhooks`

The Webhooks area has two tabs:

### Outbound subscriptions

Manage webhook endpoints that receive signed HTTPS POSTs when Signals events fire. Create, edit, enable/disable, and inspect delivery logs. Outbound deliveries are metered on the `webhook_sent` channel; each event's weight comes from `WebhookEventRegistry` (see Price weights below).

### Inbound sources

Register external systems that POST events **into** Signals. Each source has:

| Field | Description |
|-------|-------------|
| Name / slug | Human name and URL slug (used in the receive URL) |
| Secret | HMAC signing secret (encrypted at rest, write-only in the UI after creation) |
| Signature algorithm | Default `sha256` |
| Signature header | Header name the sender uses (default `X-Signature`) |
| Expected events | Optional allow-list of event names |
| Price weight | Integer 0–4 stored on the source row |
| Active | Inactive or unknown slugs return `404` |

#### Receive URL

Each active source accepts POSTs at:

```
{your-app-url}/webhooks/receive/{slug}
```

The route is **unauthenticated** — security is HMAC signature verification via `VerifyInboundWebhookSignature` middleware. On success the controller returns `200` with `{"status":"received"}` and dispatches `InboundWebhookReceived`. Invalid signatures return `401` with `{"message":"Invalid signature"}`. Unknown or inactive slugs return `404`.

See [Webhooks API](/docs/api/webhooks) for signature verification details.

## Price weights

Price weights are integers from **0 to 4** that express how "expensive" an operation is for metering and (in Signals Cloud) plan enforcement. Every metered action records both a **request count** (always incremented when logged) and **weighted units** (incremented by the weight).

**Weight 0 = logged but not metered.** The request still appears in detail logs and request counts roll up to `usage_daily`, but `weighted_units` increment by zero — so weight-0 traffic is visible but does not consume weighted quota.

### Where weights are declared

| Traffic | Weight source |
|---------|---------------|
| REST API endpoints | Code — `#[PriceWeight(n)]` attribute on controller methods, resolved by `PriceWeightResolver` and exposed in `ApiEndpointCatalogue` |
| Outbound webhook events | Code — per-event weight in `WebhookEventRegistry` (default 1) |
| Inbound webhook sources | Database — `inbound_webhook_sources.price_weight` column, editable per source in the console |
| Meta endpoints (usage, logs, endpoints, export) | Code — weight **0** so monitoring your own usage is free |

This **code-vs-database asymmetry** is intentional: API and outbound event weights ship with the framework and change on deploy; inbound source weights are tenant-configurable because each integration's cost profile differs.

### Write pipeline

1. `LogApiRequest` middleware (terminate) or webhook handlers call `UsageRecorder::record()` or `incrementDailyUsage()`
2. When Redis buffering is enabled, entries queue in a Redis list
3. `api:flush-logs` (scheduled every minute) drains the buffer into `request_logs` and updates `usage_daily` rollups
4. `api:prune-logs` (scheduled daily) deletes detail rows older than the retention window

See [Usage & Metering API](/docs/api/usage) for the REST surfaces and [framework-plans/api-usage-metering.md](/framework-plans/api-usage-metering.md) for the full architecture.
