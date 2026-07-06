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

## Using it in the app

### Navigation

| Screen | Route | Nav | Gate |
|--------|-------|-----|------|
| Containers index | `/containers` | Warehouse → Containers ("Cases, flightcases & kits") | `containers.view` |
| Container show | `/containers/{container}` | — | `containers.view` + store |
| Pack | `/containers/{container}/pack` | — | `containers.pack` |
| Repack | `/containers/{container}/repack/{opportunity}` | — | `containers.create` + `opportunities.edit` |
| Product container config | `/products/{product}/container-config` | Product tab (serialised products) | View all; save needs `containers.manage_templates` |

### Product container config tab

**Tab:** Container Config (serialised products only)

| Field | Purpose |
|-------|---------|
| Containerable | Whether the product can act as a container |
| Availability Mode | Kit / Hybrid / Transport behaviour |
| Check-in Mode | How returns are processed |
| Max Nesting Depth | Nested container limit |
| Repack on Return | Enable repack workflow after kit return |
| Warehouse Addable at Dispatch | Allow adds during dispatch |
| Warehouse Add Default Charge | Default charge for warehouse adds |
| Template Slots | Product, quantity, binding for default pack list |

**Save Container Config** requires **`containers.manage_templates`**. Without it the form is view-only with a warning banner.

### Containers index

**Scan barcode** field resolves an existing container or housing — may navigate to show, open pack, or start repack.

**Filters:** search, store, mode chips (Transport / Kit / Hybrid), status chips (defaults to Active)

**Table:** Barcode, Name, Status, Mode, Store, Type (Temporary / Product-backed), Items

Scan-create and repack-from-return flows require **`containers.create`** and **`opportunities.edit`** respectively.

### Pack page

**Route:** `/containers/{container}/pack`

Scan-to-pack workflow while the container is **open**:

- **Strict checklist** vs **free list** scan modes (configured on the container)
- Inline **conflict dialog** when a scan hits an asset already elsewhere — options include transfer, auto-return, and nesting resolution
- **Seal** when packing is complete

### Container show

**Actions** (each policy-gated): Pack items, Repack session, Seal, Dissolve, Unseal (requires reason), Dispatch (kit/transport), Return kit, Verify check-in, Transfer items, Save scan mode

**Panels:** barcode, status, mode, check-in mode, scan mode, store, **contents tree**, **audit timeline**

**Dissolve-on-dispatch:** transport containers configured for this mode dissolve automatically when dispatched from an opportunity — contents move to the dispatch workflow; the container record completes.

**Seal / unseal:** sealed containers carry a compliance manifest; unseal prompts for a reason.

### Repack-on-return

**Route:** `/containers/{container}/repack/{opportunity}`

Editable scan mode after kit return — scan items back into the container with lineage preserved. Also reachable from the index scan box ("start repack from return" modal).

### Opportunity Assets tab

**Route:** `/opportunities/{id}/assets` (visible for Order or Quotation+Reserved)

Sub-tabs: Functions, Allocate, Prepare, Book out, Check in

| Workflow | Container behaviour |
|----------|---------------------|
| Dispatch | Transport dissolve-on-dispatch; kit dispatch ties container to opportunity; amendment modal for held items under WarnPartial policy |
| Verify check-in | Post-return verification on kit containers |
| Repack split view | Shows repack progress with lineage after return |

**Set container** (bulk Functions menu) assigns containers to opportunity lines. Dispatch and return reuse the same container policies as standalone container pages (`containers.dispatch`, `containers.return`, plus **`opportunities.edit`**).

### Permissions summary

| Permission | UI effect |
|------------|-----------|
| `containers.view` | Index, show, Warehouse nav link |
| `containers.create` | Temporary containers, repack sessions |
| `containers.pack` | Pack page |
| `containers.seal` / `dissolve` / `transfer` | Show page actions |
| `containers.dispatch` / `return` | Dispatch/return (also needs `opportunities.edit` on opportunity) |
| `containers.manage_templates` | Product container config save |
| `opportunities.edit` | Assets tab mutations, repack |

## Related documentation

- [Containers API](/docs/api/containers) — full REST lifecycle
- [Stock Levels](/docs/platform/stock-levels) — serialised asset inventory
- [Opportunities](/docs/platform/opportunities) — dispatch and return on order lines
