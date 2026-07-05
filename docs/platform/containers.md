---
title: Containers & Kits
description: Serialised container pack/seal/dispatch/return lifecycle and warehouse UI.
---

## Overview

**Containers** group serialised assets into dispatchable units — lighting kits, road cases, hybrid kits, and transport boxes. The lifecycle covers configuration, pack/unpack, seal/unseal, dissolve, dispatch, return, and repack.

Warehouse UI routes:

| Route | Purpose |
|-------|---------|
| `/containers` | Container index |
| `/containers/{container}` | Detail and status |
| `/containers/{container}/pack` | Pack UI |
| `/containers/{container}/repack/{opportunity}` | Repack-on-return for an opportunity |

Product-level **container templates** configure default pack lists and policies at `/products/{product}` (container config tab) and via `GET/PUT /api/v1/products/{product}/container_template`.

## Container modes

| Mode | Behaviour |
|------|-----------|
| **Kit** | Contents held from individual availability as a unit |
| **Hybrid** | Mixed fixed/variable contents |
| **Transport** | Road case / flight case; supports **dissolve-on-dispatch** |

## Lifecycle

```
open → sealed → dispatched → returned → (repack) → open
```

| Event | Description |
|-------|-------------|
| **Pack / unpack** | Add or remove serialised items while **open** |
| **Seal / unseal** | Lock container with compliance manifest |
| **Dissolve** | Manual dissolve with warn-and-resolve for nested packing |
| **Dissolve-on-dispatch** | Transport mode — container dissolves when dispatched |
| **Dispatch / return** | Kit dispatch and kit return tie container to an opportunity |
| **Repack-on-return** | Editable scan mode repack after return |

Temporary containers can be created ad hoc for one-off jobs (`POST /api/v1/containers/temporary`).

## Compliance manifest

Sealed containers carry a **compliance manifest** listing packed serial numbers. Retrieve via `GET /api/v1/containers/{container}/compliance`.

## Dissolve-on-dispatch

Transport containers configured for **dissolve-on-dispatch** unpack their contents into the dispatch workflow automatically — the container record completes while assets move to the opportunity.

## Repack-on-return

After kit return, **repack-on-return** lets operators scan items back into the container (`POST /api/v1/containers/repack/start` and per-item repack). UI trigger at `/containers/{container}/repack/{opportunity}`.

## Plugin and webhook seams

Container pack, seal, dissolve, dispatch, return, and repack events appear in the **webhook catalogue** for integrations (barcode scanners, WMS, etc.).

## Related documentation

- [Containers API](/docs/api/containers) — full REST lifecycle
- [Stock Levels](/docs/platform/stock-levels) — serialised asset inventory
- [Opportunities](/docs/platform/opportunities) — dispatch and return on order lines
