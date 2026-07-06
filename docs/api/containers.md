# Containers API

Containers group serialised assets into dispatchable units (kits, hybrid kits, transport boxes). The API covers the full pack/seal/dispatch/return lifecycle.

Packing a kit or hybrid-fixed container holds its contents from individual availability via container demand; unpacking releases it.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/containers` | List containers |
| POST | `/api/v1/containers` | Create container |
| POST | `/api/v1/containers/temporary` | Create temporary container |
| GET | `/api/v1/containers/{container}` | Show container with packed contents |
| PATCH | `/api/v1/containers/{container}` | Update container metadata |
| GET | `/api/v1/containers/{container}/compliance` | Compliance manifest (sealed) |
| POST | `/api/v1/containers/{container}/pack` | Pack serialised item |
| POST | `/api/v1/containers/{container}/unpack` | Unpack serialised item |
| POST | `/api/v1/containers/{container}/transfer` | Transfer packed items between containers |
| POST | `/api/v1/containers/{container}/seal` | Seal container |
| POST | `/api/v1/containers/{container}/unseal` | Unseal container |
| POST | `/api/v1/containers/{container}/dissolve` | Dissolve container |
| POST | `/api/v1/containers/{container}/dispatch` | Dispatch kit to opportunity |
| POST | `/api/v1/containers/{container}/return` | Return kit from opportunity |
| POST | `/api/v1/containers/repack/start` | Start repack session |
| POST | `/api/v1/containers/{container}/repack` | Repack single item |
| POST | `/api/v1/containers/{container}/verify-checkin` | Verify check-in scan |

Product container templates: `GET/PUT /api/v1/products/{product}/container_template`.

## Authentication

Requires `containers:read` (GET) or `containers:write` (POST/PATCH) with matching `containers.view`, `containers.pack`, `containers.seal`, etc. permissions.

## List containers

```
GET /api/v1/containers
```

Ransack filters: `name`, `barcode`, `status`, `scan_mode`, `is_temporary`, `product_id`, `store_id`, `opportunity_id`, `created_at`, `updated_at`.

Includes: `?include=product,store,active_items`

## Lifecycle notes

| Action | Constraint |
|--------|------------|
| **Pack / unpack** | Container must be `open` |
| **Seal** | Requires packed contents; produces compliance manifest |
| **Dissolve** | Warn-and-resolve nested packing; may transfer items |
| **Dispatch / return** | Kit mode — ties container to opportunity |
| **Repack** | Post-return scan workflow |

Transport containers may use **dissolve-on-dispatch** (configured on product template) — see [Containers & Kits platform doc](/docs/platform/containers).

## Pack item

```
POST /api/v1/containers/{container}/pack
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `serialised_item_id` | int | Yes | Serialised stock level to pack |
| `position` | string | No | Slot/position label |
| `notes` | string | No | Free-text note |

Returns `201` with `container_item`. Rejected with `422` when container is not open, item is at wrong store, already packed, or committed to another opportunity.

## Unpack item

```
POST /api/v1/containers/{container}/unpack
```

Body: `{ "serialised_item_id": 88 }`. Returns `200` with closed membership row.

## Webhooks

`container.created`, `container.item.packed`, `container.item.unpacked`, `container.sealed`, `container.unsealed`, `container.dissolved`, `container.dispatched`, `container.returned`, `container.repack.started`, `container.repack.item` — see [Webhooks](/docs/api/webhooks).
