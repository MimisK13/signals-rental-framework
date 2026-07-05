# Usage & Metering API

Read aggregated API and webhook usage, browse request log entries, and discover endpoint metering metadata. These meta endpoints support the API Console and external monitoring integrations.

All endpoints in this document require Sanctum bearer authentication and are declared with **price weight 0** — they are logged but do not consume weighted metering units.

## Endpoints

| Method | URL | Ability | Description |
|--------|-----|---------|-------------|
| GET | `/api/v1/usage` | `api-usage:read` | Aggregated usage for a date window |
| GET | `/api/v1/usage/export` | `api-usage:read` | Queue async CSV export of usage aggregates |
| GET | `/api/v1/request_logs` | `api-log:read` | Paginated request log detail rows |
| GET | `/api/v1/endpoints` | `system:read` | Endpoint catalogue with price weights |

## Authentication

Requires a Sanctum bearer token with the ability shown above. Requests without the required ability receive `403`.

## List Usage

```
GET /api/v1/usage
```

Returns grouped aggregates from the `usage_daily` rollup table.

### Query parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `window` | `7d` | Date preset: `24h`, `7d`, `30d`, or `custom` |
| `from` | — | Required when `window=custom`. Start date (`Y-m-d`) |
| `to` | — | Required when `window=custom`. End date (`Y-m-d`), must be on or after `from` |
| `channel` | — | Filter by metering channel: `api`, `webhook_sent`, or `webhook_received` |
| `dimension` | `endpoint` | Grouping: `token`, `endpoint`, `resource`, `day`, or `status` |
| `token_id` | — | Filter API-channel rows to a specific personal access token ID |

### Response

```json
{
    "usage": [
        {
            "key": "api.v1.members.index",
            "label": null,
            "request_count": 142,
            "weighted_units": 142
        }
    ],
    "meta": {
        "totals": {
            "request_count": 1250,
            "weighted_units": 980
        },
        "window": {
            "preset": "7d",
            "from": "2026-06-25T00:00:00+00:00",
            "to": "2026-07-01T14:30:00+00:00"
        },
        "dimension": "endpoint"
    }
}
```

| Field | Description |
|-------|-------------|
| `usage[].key` | Group key — route name, token ID, resource name, date, or status class depending on `dimension` |
| `usage[].label` | Human label when available (e.g. token name for `token` dimension) |
| `usage[].request_count` | Total requests in the window |
| `usage[].weighted_units` | Sum of price weights (weight-0 traffic contributes 0) |
| `meta.totals` | Window totals across all groups |
| `meta.window` | Resolved date window |

## Export Usage

```
GET /api/v1/usage/export
```

Accepts the same query parameters as `GET /api/v1/usage`. Dispatches an `ExportApiUsage` job and returns `202 Accepted` with a job identifier. The CSV is delivered asynchronously (same export pipeline as the Usage dashboard).

## List Request Logs

```
GET /api/v1/request_logs
```

Returns paginated detail rows from `request_logs` (API requests, outbound webhook deliveries, and inbound webhook receipts).

### Filters (Ransack)

| Parameter | Description |
|-----------|-------------|
| `q[route_name_eq]=api.v1.members.index` | Exact route name (API channel) |
| `q[status_code_eq]=200` | HTTP status code |
| `q[personal_access_token_id_eq]=3` | Token ID (API channel) |
| `q[channel_eq]=api` | Metering channel: `api`, `webhook_sent`, `webhook_received` |
| `q[created_at_gteq]=2026-07-01` | Created on or after |
| `q[created_at_lteq]=2026-07-01` | Created on or before |

### Sorts

| Parameter | Description |
|-----------|-------------|
| `sort=created_at` | Newest first (default when `sort` omitted) |
| `sort=-created_at` | Oldest first |
| `sort=status_code` | By status |
| `sort=route_name` | By route name |

### Pagination

Standard offset pagination: `?page=2&per_page=20`.

### Response

```json
{
    "request_logs": [
        {
            "id": 1,
            "channel": "api",
            "request_id": "550e8400-e29b-41d4-a716-446655440000",
            "method": "GET",
            "uri": "/api/v1/members",
            "route_name": "api.v1.members.index",
            "resource": "members",
            "operation": "index",
            "personal_access_token_id": 3,
            "user_id": 1,
            "ability": "members:read",
            "status_code": 200,
            "duration_ms": 45,
            "request_bytes": 0,
            "response_bytes": 2048,
            "price_weight": 1,
            "weighted_units": 1,
            "ip_address": "127.0.0.1",
            "user_agent": "Signals-Explorer/1.0",
            "created_at": "2026-07-01T14:30:00Z"
        }
    ],
    "meta": {
        "total": 500,
        "per_page": 20,
        "page": 1
    }
}
```

Detail log CSV export is available from the API Console Request Log UI (not exposed as a REST endpoint).

## List Endpoints

```
GET /api/v1/endpoints
```

Returns every registered v1 route with metering metadata from `ApiEndpointCatalogue` and `PriceWeightResolver`.

### Response

```json
{
    "endpoints": [
        {
            "name": "api.v1.members.index",
            "method": "GET",
            "uri": "/api/v1/members",
            "resource": "members",
            "operation": "index",
            "ability": "members:read",
            "price_weight": 1
        },
        {
            "name": "api.v1.usage.index",
            "method": "GET",
            "uri": "/api/v1/usage",
            "resource": null,
            "operation": null,
            "ability": "api-usage:read",
            "price_weight": 0
        }
    ]
}
```

Use this catalogue to discover required abilities and price weights before integrating. Weights of **0** indicate endpoints that are logged but excluded from weighted metering totals.

## Metering channels

| Channel | Source |
|---------|--------|
| `api` | `LogApiRequest` middleware on `/api/v1/*` |
| `webhook_sent` | `DeliverWebhook` job (first delivery attempt) |
| `webhook_received` | `InboundWebhookController` (valid signature only) |

## Related documentation

- [API Console (platform)](/docs/platform/api-console) — Explorer, Request Log, Usage dashboard, and Webhooks UI
- [Webhooks](/docs/api/webhooks) — outbound subscriptions and inbound receiver
