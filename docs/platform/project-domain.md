---
title: Opportunity → Project Domain Convergence
description: Current domain archaeology and the staged migration plan from the Opportunity rental aggregate to a canonical Project job aggregate.
---

# Opportunity → Project Domain Convergence

## Status

This document records the **CURRENT / IMPLEMENTED** architecture and the
**PROPOSED / FUTURE** convergence plan. It is a P0 architecture document only.

No model, table, route, API, event, or permission rename is implemented by this
document.

## 1. Executive summary

The repository currently has two different concepts with an overloaded business
vocabulary:

```text
Opportunity
    = the actual event-sourced rental/job aggregate
    = quotation and order lifecycle
    = line items, pricing, demand, availability, allocation, warehouse execution

Project
    = a newer, thin planning/commercial grouping record
    = groups multiple Opportunities, potentially across Stores
    = owns no line items, inventory scope, demand, availability, or financial totals
```

The existing Opportunity is therefore the current canonical operational job. The
existing Project is not a second operational job aggregate; it is a cross-Store
grouping parent.

The safest direction is an incremental **Opportunity identity → canonical Project
job** migration. The current Project must not be blindly promoted as-is because its
current one-to-many grouping semantics conflict with a Project that owns one
operational rental/job. It should either become an explicitly named grouping
concept later (for example `ProjectGroup`) or remain a deprecated compatibility
container during the migration.

The recommended migration is incremental. Keep the `opportunities` tables and
public contracts while introducing a canonical Project/job boundary, then move
read and write responsibilities in slices. A separate Quotation aggregate remains
a later phase.

## 2. Current Opportunity definition

`App\Models\Opportunity` is a read-optimised projection of an event-sourced
Verbs aggregate. Its lifecycle has two axes:

```text
state:  Draft → Quotation → Order
status: state-specific workflow position
```

The current Opportunity can begin as a Draft, become a customer-facing
Quotation, and become an Order whose equipment is allocated, dispatched, returned,
checked, and completed.

The same aggregate currently serves both commercial quotation work and operational
rental execution. This is the central semantic collision that the future Quotation
/Project split must resolve.

## 3. Current Opportunity responsibilities

| Responsibility | Current role | Evidence |
|---|---|---|
| Customer/company | OWNER/reference to `Member` | `member_id`, `member()` |
| Venue | OWNER/reference to `Member` | `venue_id`, `venue()` |
| Store/warehouse | OWNER of the primary operational Store context | `store_id`, `store()` |
| Dates | OWNER | operational, planning, charge, and event-logistics date fields |
| Lifecycle | OWNER | `OpportunityState`, `OpportunityStatus`, Verbs transitions |
| Line items | OWNER | `opportunity_items`, active-version filtering |
| Sections/groups | OWNER | `OpportunitySection` and item tree/path semantics |
| Products/accessories | OWNER/reference through `OpportunityItem` | itemable product/accessory relations |
| Pricing/discount/tax | OWNER/orchestrator | pricing events, totals, locks, tax and rate snapshots |
| Deal price | OWNER | `deal_total`, deal price events and mode |
| Quote versions | OWNER | `opportunity_versions`, version lifecycle |
| Planning | OWNER of the job context | planning dates and planning readers |
| Demand | SOURCE/orchestrator | items produce polymorphic `Demand` rows |
| Availability | ORCHESTRATOR/consumer | item/store/date context is passed to availability services |
| Shortages | ORCHESTRATOR/projection | computed from items, stock, demand, and resolutions |
| Asset allocation | OWNER of item-level assignments | `opportunity_item_assets` and asset events |
| Equipment preparation/dispatch/return | OWNER/orchestrator | item asset and bulk fulfilment events |
| Containers | REFERENCE/operational scope | containers and container items reference Opportunity IDs |
| Costs | OWNER | `opportunity_costs` |
| Invoices | SOURCE/reference | invoices point to an Opportunity and checkout reads quote versions |
| Purchase orders/sub-hire | REFERENCE/source context | purchase orders and virtual stock overrides use Opportunity IDs |
| Activities/files/documents | OWNER through polymorphic relations | `activities`, attachments, and documents |
| Crew/transport | PARTICIPANT in part | logistics date/instruction fields exist; a full crew aggregate does not |
| External integrations | SOURCE identity | API, webhooks, audit records, and document resolution use Opportunity identity |

This is a mixed commercial and operational aggregate, not merely a sales lead.

## 4. Current Project definition

`App\Models\Project` was introduced in commit `2c6d1c1c` (`Add project planning
workflows`). Its own docblock and platform documentation describe it as a
planning/commercial grouping for related Opportunities.

Current Project fields are:

```text
id
name
member_id       customer organisation
venue_id
owned_by        user member
status          draft / active / completed / archived
starts_at
ends_at
timestamps
deleted_at
```

It deliberately has no `store_id`, no line items, no demand, no availability
relationship, no invoice totals, and no warehouse execution state.

Its only domain collection is:

```text
Project 1 ─── * Opportunity
```

Project lifecycle dates are planning metadata and do not replace an Opportunity's
operational dates or drive availability.

## 5. Current Project responsibilities

| Responsibility | Current role | Evidence |
|---|---|---|
| Customer/venue/owner metadata | OWNER | Project fields and CRUD actions |
| Grouping Opportunities | OWNER | `Project::opportunities()` |
| Cross-Store grouping | SUPPORTED | each child Opportunity retains its own `store_id` |
| Project lifecycle | OWNER of grouping lifecycle | `ProjectStatus` transition matrix |
| Operational line items | NOT INVOLVED | no Project item relation/table |
| Planning demand | NOT INVOLVED | no Project demand path |
| Availability | NOT INVOLVED | docs explicitly keep availability on Opportunity |
| Warehouse execution | NOT INVOLVED | warehouse records reference Opportunity/item identity |
| Financial totals | NOT INVOLVED | docs explicitly defer persisted project rollups |
| Opportunity assignment | ORCHESTRATOR | attach, move, detach, delete actions |
| Project permissions/API | OWNER of grouping access | `projects.*`, `projects:read/write` |

## 6. Why existing Project exists

The implementation history and tests show that Project was introduced to group
related quotes/orders without changing their Store or operational semantics. The
tests explicitly prove:

- one Project may contain multiple Opportunities;
- those Opportunities may belong to different Stores;
- moving an Opportunity changes only `project_id`;
- the Opportunity's Store remains unchanged;
- deleting a Project soft-deletes only the Project and clears child links;
- assignment is recorded through the existing Opportunity event stream.

Therefore the current Project was introduced as a cross-Store planning/commercial
container, not as the actual rental job.

## 7. Current Opportunity ↔ Project relationship

The relationship is one-to-many and nullable:

```text
Project 1 ───────── 0..* Opportunity
                         │
                         └── opportunities.project_id nullable FK
```

An Opportunity belongs to at most one Project. A Project can contain quotes and
orders. Assignment is represented inside `OpportunityState` and projected to the
`opportunities.project_id` column by `OpportunityProjectAssigned`.

The relationship is grouping metadata only. It does not transfer:

- Store ownership;
- dates;
- line items;
- demand;
- availability;
- allocations;
- pricing;
- invoices;
- warehouse state.

## 8. Database relationship map

The current high-level database graph is:

```text
projects
└── opportunities.project_id (nullable, FK → projects.id, nullOnDelete)
    ├── opportunity_items.opportunity_id
    │   ├── opportunity_item_assets.opportunity_item_id
    │   │   └── stock_levels / assets
    │   ├── demands.source_type = OpportunityItem
    │   ├── shortage_resolutions / shortage_resolution_items
    │   └── invoice_items.opportunity_item_id (where applicable)
    ├── opportunity_versions.opportunity_id
    │   └── version_id on opportunity_items
    ├── opportunity_costs.opportunity_id
    ├── opportunity_participants.opportunity_id
    ├── opportunity_surcharge_groups.opportunity_id
    ├── shortage_acknowledgements.opportunity_id
    ├── invoices.opportunity_id (nullable, nullOnDelete)
    ├── purchase_orders.opportunity_id (nullable, nullOnDelete)
    ├── virtual_stock_intakes.primary_opportunity_id (nullable, nullOnDelete)
    ├── virtual_stock_cost_overrides.opportunity_id
    ├── containers.opportunity_id (nullable, nullOnDelete)
    └── container_items.auto/returned_from_opportunity_id (nullable, nullOnDelete)
```

There is no current `allocations` table found in the repository. Allocation is
represented by item/asset assignment projections and related asset lifecycle
records.

### Important FK behavior

| Table/column | Target | Nullability | Delete behavior |
|---|---|---:|---|
| `opportunities.project_id` | `projects.id` | nullable | null on Project delete |
| `opportunity_items.opportunity_id` | `opportunities.id` | required | cascade |
| `opportunity_versions.opportunity_id` | `opportunities.id` | required | cascade |
| `opportunity_costs.opportunity_id` | `opportunities.id` | required | cascade |
| `opportunity_participants.opportunity_id` | `opportunities.id` | required | cascade |
| `shortage_acknowledgements.opportunity_id` | `opportunities.id` | required | cascade |
| `invoices.opportunity_id` | `opportunities.id` | nullable | null on delete |
| `purchase_orders.opportunity_id` | `opportunities.id` | nullable | null on delete |
| `virtual_stock_intakes.primary_opportunity_id` | `opportunities.id` | nullable | null on delete |
| `virtual_stock_cost_overrides.opportunity_id` | `opportunities.id` | required | cascade |
| `containers.opportunity_id` | `opportunities.id` | nullable | null on delete |
| `container_items.*_opportunity_id` | `opportunities.id` | nullable | null on delete |

The `opportunities` projection is itself event-sourced: `state_id` is a unique
bridge to the Verbs state stream, while the small `id` is used by projection FKs.

## 9. Current lifecycle comparison

| Concern | Opportunity | Project |
|---|---|---|
| Primary meaning | quotation/order rental document and job | planning/grouping record |
| Lifecycle | Draft → Quotation → Order, plus operational statuses | Draft → Active → Completed → Archived |
| Customer decision | quotation version accepted/declined | none |
| Inventory commitment | derived from status/demand phase | none |
| Dispatch/return | Order sub-status and asset events | none |
| Terminal behavior | lost/dead/cancelled/complete | archived/soft-deleted |
| Event source | Verbs event stream | ordinary Eloquent CRUD plus assignment event |

The state axes must not be merged blindly. Sales states (`QuotationProvisional`,
`QuotationReserved`, `QuotationLost`, etc.) and operational states (`OrderOnHire`,
`OrderReturned`, `OrderChecked`) represent different concerns.

## 10. Item/line-item architecture

`OpportunityItem` is the canonical persisted line-item projection. It supports:

- product, text, group, accessory, and related item types;
- hierarchical `path`/tree ordering;
- quote-version scope;
- quantities, dates, prices, discounts, surcharges, and totals;
- item-level asset assignments;
- product/accessory semantics;
- planning and availability projections.

The Experimental Quotation Editor currently edits the canonical Opportunity and
reuses the same Actions, planning readers, availability services, shortage
detector, and prepared Product/Stock/Demand read path as the production editor.

No Project item model currently exists.

## 11. Demand ownership

Demand is authoritative for availability, but it is not directly keyed by
`opportunity_id` or `project_id`. `Demand` uses a polymorphic source:

```text
Demand
├── product_id
├── store_id
├── dates / buffered period
├── phase / is_active
└── source_type + source_id
        └── OpportunityItem for rental line demand
```

`OpportunityItemDemandResolver` translates Opportunity items into demand rows.
The parent Opportunity status determines the Demand phase. Draft/provisional
quotes are inactive Draft demand; reserved quotes and active orders become
Committed; dispatched/on-hire become Operational; returned/checked/completed are
Closed according to the configured release point; lost/dead/cancelled are Void.

This supports the future invariant:

```text
unaccepted quotation ≠ operational demand
```

The current implementation already keeps provisional quotation demand inactive,
but quotation and operational state still live on the same Opportunity aggregate.

## 12. Availability ownership

Availability is derived from stock and active Demand. The Opportunity does not
own availability facts; it supplies the item Product, Store, dates, and self-demand
identity to `AvailabilityService`, `OwnedCapacityReader`, and the shortage/planning
readers.

The current O2 prepared-read work is explicitly scoped to Opportunity item reads:

```text
OpportunityItem
    → prepared Product inputs
    → prepared Stock inputs
    → prepared Standard Demand inputs
    → Availability / Planning / Shortage projection
```

The future Project migration must preserve this separation. A Project should not
become a hidden availability cache or a second demand source.

## 13. Allocation ownership

Allocation is currently Opportunity-item scoped. Serialized assets are attached
through `opportunity_item_assets`, and the Verbs asset events manage allocation,
preparation, dispatch, return, checks, containers, and status transitions.

Bulk fulfilment is represented by quantity transitions on the Opportunity item.
There is no independent Project allocation aggregate today.

## 14. Warehouse workflow dependencies

The current operational chain is:

```text
OpportunityItem
    → Demand / availability
    → asset allocation or bulk quantity
    → preparation
    → container packing
    → dispatch / book-out
    → on hire
    → return / check-in
    → inspection / finalisation
```

Containers reference Opportunity IDs for dispatch and return context. Container
items retain auto-returned and returned-from Opportunity references. Warehouse
permissions and policies also currently use `opportunities.edit` for several
dispatch/return paths.

This is strong evidence that the current Opportunity, not Project, is the
operational job boundary.

## 15. Financial dependencies

Current commercial/financial ownership is also Opportunity-centered:

```text
Opportunity
├── pricing/rates/discounts/surcharges/tax snapshot
├── deal price and price locks
├── costs
├── quote versions
└── invoice checkout source
```

Invoices are separate event-sourced aggregates, but `invoices.opportunity_id` is
the nullable source link. Checkout copies active quote-version lines, costs,
surcharges, and tax into the invoice snapshot. Purchase orders and virtual-stock
cost overrides also retain Opportunity references.

Project currently stores no financial rollups. The Project documentation
explicitly defers persisted revenue, cost, margin, and profit fields.

## 16. UI/routes dependency map

### Opportunity surfaces — CURRENT / IMPLEMENTED

| Route | Surface |
|---|---|
| `/opportunities` | Opportunity DataTable |
| `/opportunities/create` | create form |
| `/opportunities/{opportunity}` | detail/Overview |
| `/opportunities/{opportunity}/edit` | edit form |
| `/opportunities/{opportunity}/assets` | asset/fulfilment tab |
| `/opportunities/{opportunity}/costs` | costs |
| `/opportunities/{opportunity}/invoices` | invoice checkout/list |
| `/opportunities/{opportunity}/participants` | participants |
| `/opportunities/{opportunity}/shortages` | shortage detection/resolution |
| `/opportunities/{opportunity}/versions` | quote versioning |
| `/opportunities/{opportunity}/activities` | activity timeline |
| `/opportunities/{opportunity}/custom-fields` | custom fields |
| `/opportunities/{opportunity}/files` | attachments |
| `/opportunities/{opportunity}/communications` | communications |
| `/opportunities/{opportunity}/documents` | documents |
| `/experimental/quotations/*` | Experimental presentation over canonical Opportunity data |
| `/planner` | timeline of Opportunity quotes/orders |

### Project surfaces — CURRENT / IMPLEMENTED

| Route | Surface |
|---|---|
| `/projects` | Project grouping DataTable and push side panels |
| `/projects/create` | compatibility entry point to create panel |
| `/projects/{project}` | grouped Opportunity detail |
| `/projects/{project}/edit` | compatibility entry point to edit panel |

No current Project route edits line items or performs planning/warehouse work.

## 17. API dependency map

Opportunity has a large public/internal API contract under `/api/v1/opportunities`:

- CRUD and lifecycle transitions;
- line-item create/update/delete and tree restructuring;
- asset allocation and fulfilment;
- pricing, discounts, surcharges, deal price, and recalculation;
- shortages, resolutions, and shortage gate;
- quote versions;
- costs, participants, invoices, and restore/archive;
- available-actions and audit-related reads.

Project has a smaller independent API:

```text
GET/POST       /api/v1/projects
GET/PATCH/DELETE /api/v1/projects/{project}
POST           /api/v1/projects/{project}/opportunities/{opportunity}/attach
DELETE         /api/v1/projects/{project}/opportunities/{opportunity}
POST           /api/v1/projects/{project}/opportunities/{opportunity}/move
```

Opportunity routes use `opportunity_id`, Opportunity resources, and
`opportunities:*` token abilities. Project routes use `project_id`, Project
resources, and `projects:*` token abilities. A rename would therefore create
substantial compatibility debt rather than being a cosmetic change.

## 18. Events/listeners dependency map

Opportunity has a large event vocabulary, including:

```text
OpportunityCreated / Updated / Deleted / Restored
OpportunityQuoted / ConvertedToOrder / RevertedToQuotation / RevertedToDraft
OpportunityStatusChanged / StatusPromoted / Reinstated / Reopened
OpportunityLocksApplied / LocksReleased
ItemAdded / Removed / Renamed / QuantityChanged / DetailsUpdated
ItemsRestructured / ItemSortOrderChanged / ItemSubstituted
PricingRecalculated / DealPriceSet / Discount... / Surcharge...
AssetAllocated / Prepared / Dispatched / Returned / Checked / Container...
OpportunityProjectAssigned
```

These events project to `opportunities`, `opportunity_items`, versions, costs,
demands, audit entries, webhooks, and availability updates. Their names are
also exposed through webhook documentation and external integrations.

Project metadata uses ordinary Actions and Eloquent persistence. Assignment is
the exception: `OpportunityProjectAssigned` mutates the Opportunity Verbs state
and projection and records audit data. This means a future identity migration
must handle both event-stream naming and projection naming; a class rename alone
is not sufficient.

## 19. Authorization dependency map

Current permissions are intentionally separate:

```text
opportunities.access/view/create/edit/delete
opportunities.unlock_rates
opportunities.dispatch_add_items
shortages.view/resolve/ignore

projects.access/view/create/edit/delete
projects:read/write
```

Project assignment requires both Project update authorization and Opportunity
update authorization. Warehouse/container paths still rely on Opportunity
permissions for operational actions. A target Project job aggregate will need a
careful permission mapping; it must not silently replace `opportunities.edit`
with `projects.edit` across warehouse operations.

## 20. Experimental Quotation Editor impact

CURRENT:

```text
Experimental Quotations
    → Opportunity route binding
    → canonical Opportunity
    → OpportunityItem tree
    → canonical Actions/read services
```

It does not own a quotation aggregate or persistence layer. Its current
performance work (prepared Products, Stock, Demand, explicit propagation, and
child shortage summary) is tied to the Opportunity item read contract.

Migration impact:

1. Keep the editor on the legacy Opportunity contract during the read-side
   migration.
2. Introduce a Project/job adapter only after the canonical read contract has a
   stable Project-facing name.
3. Do not make the Experimental editor the first place to rename item tables or
   domain Actions.
4. A future true QuotationEditor should stop writing operational Demand until a
   quotation is accepted and converted into a Project/job.

No Experimental editor rewrite is part of P0.

## 21. Multi-store implications

The current Project deliberately spans multiple Stores by grouping Opportunities.
That is not enough to make it an operational multi-Store job.

Recommendation for the target operational Project:

- preserve a primary `store_id` initially if the existing Opportunity contract
  requires one;
- do not imply that one Project-wide Store governs every line;
- if multi-Store execution is required, model Store context at an operational
  sub-job/leg or Project-item requirement boundary;
- preserve item-level `dispatch_store_id` and `return_store_id` semantics;
- do not reuse the current Project's cross-Store grouping behavior as proof that
  operational multi-Store semantics are solved.

This is a product decision for P1/P2, not a P0 schema change.

## 22. Semantic collision matrix

| Responsibility | Opportunity today | Project today | Target owner |
|---|---|---|---|
| Customer | direct owner/reference | grouping metadata | Project/job initially; Quotation later owns commercial snapshot |
| Dates | operational and charge-date authority | planning metadata only | Project/job operational dates; quotation validity separately later |
| Store | primary availability/warehouse context | intentionally absent | Project/job plus explicit item/leg Store semantics |
| Items | canonical `OpportunityItem` owner | none | ProjectItem/ProjectRequirement after semantic decision |
| Planning | item and job planning authority | grouping only | Project/job |
| Demand | item-originating source | none | ProjectItem/Requirement after acceptance |
| Availability | consumes item context | none | availability engine, queried from Project/job requirements |
| Allocation | item/asset owner | none | Project/job item/asset boundary |
| Warehouse | dispatch/return/asset lifecycle | none | Project/job |
| Pricing | quote/order pricing and locks | none | Quotation for commercial proposal; Project/job for operational agreed snapshot |
| Financials | costs and invoice source | no persisted rollups | Invoice remains separate; source boundary moves deliberately |
| Lifecycle | sales + operational lifecycle mixed | grouping lifecycle | split into Quotation lifecycle and Project operational lifecycle |
| Quote versions | owner | none | Quotation later |
| Activities/files | polymorphic Opportunity owner | Project UI metadata only | decide per artifact; do not bulk move |

## 23. Option A — Promote existing Project

### Shape

Make the existing `projects` table/model the operational aggregate and move
Opportunity responsibilities below it:

```text
Project
└── ProjectItems / Requirements
    └── Demand / Allocation / Warehouse
```

### Benefits

- final name already exists;
- new operational identity can be introduced without preserving the name
  Opportunity as the canonical job;
- clear future Quotation → Project story.

### Risks

- current Project is one-to-many and cross-Store; that is a different cardinality
  and meaning from the current operational Opportunity;
- existing Project users expect grouping behavior;
- `opportunities.project_id` would need a new meaning or removal;
- all Opportunity event state, item FKs, demands, invoices, containers, APIs, and
  permissions would need a new owner;
- highest semantic and database migration risk.

### Assessment

**Not safe as a direct promotion.** It is viable only after the current Project is
reclassified or split into a grouping concept and the operational identity is
introduced explicitly.

## 24. Option B — Opportunity becomes Project and replaces current Project

### Shape

Treat the current Opportunity aggregate as the canonical future Project/job:

```text
current Opportunity identity
    → canonical Project/job identity
```

The current Project grouping container becomes a separate future concept such as
`ProjectGroup`, or remains a compatibility container temporarily.

### Benefits

- follows actual ownership: Opportunity already owns the rental/job workflow;
- preserves current line-item, demand, availability, allocation, warehouse, and
  financial semantics while names are migrated gradually;
- avoids moving operational foreign keys to a thin grouping record;
- keeps the event-sourced aggregate as the source of truth;
- gives the future Quotation a clean boundary: quotation is no longer the
  operational Project once the split is introduced.

### Risks

- large naming/API/foreign-key compatibility surface;
- current Project grouping semantics need a new explicit home;
- public routes and webhooks cannot be renamed abruptly;
- event-stream class names and audit `auditable_type` values require a staged
  compatibility plan;
- `OpportunityItem` naming remains legacy until a separate item decision.

### Assessment

**Semantically strongest and safest operational base**, provided it is executed as
an incremental compatibility migration rather than a global rename.

## 25. Option C — Converge both aggregates

### Shape

Keep both records temporarily and build a staged canonical Project boundary that
can read from the current Opportunity operational aggregate and the current
Project grouping metadata:

```text
Project/job boundary
├── legacy Opportunity operational identity during transition
└── legacy Project grouping identity during transition
```

### Benefits

- allows read-side convergence before write-side migration;
- supports compatibility adapters and dual terminology during a controlled window;
- provides room to decide whether grouping survives as ProjectGroup;
- can reduce route/API migration risk.

### Risks

- two authorities can drift if writes are dual-written carelessly;
- a generic adapter can hide unresolved ownership questions;
- temporary relationships add query and test complexity;
- convergence without a hard authority rule becomes permanent ambiguity.

### Assessment

**Useful as the migration technique, not as the final domain model.** Use it to
execute Option B safely, with one explicit write authority per phase.

## 26. Recommended option

Recommend **Option B implemented through the staged mechanics of Option C**:

```text
Final semantic direction:
    current Opportunity operational aggregate → canonical Project/job

Migration technique:
    incremental read/write/API compatibility layers

Current Project:
    preserve as grouping semantics temporarily; later decide ProjectGroup or
    explicit archival/merge treatment
```

Do not promote the current Project table directly. It has the wrong current
cardinality and no operational ownership.

## 27. Proposed canonical Project aggregate

The target Project/job should eventually own the operational rental container,
not the commercial pre-acceptance lifecycle.

Candidate field groups mapped from current Opportunity code:

```text
identity:
    id, state/event-stream identity, number, subject/reference

customer/context:
    member_id, venue_id, owned_by, tags, addresses

operational:
    store strategy, starts_at, ends_at, planning window, event-logistics dates
    customer collecting/returning, delivery/collection instructions

commercial snapshot:
    agreed currency/rate/tax snapshot, accepted price references

execution:
    operational status, allocation/dispatch/return milestones

projection metadata:
    totals, shortage projection flags, timestamps
```

This is a conceptual mapping, not a new schema proposal to implement in P0.
The final Project must distinguish commercial state from operational state rather
than copying the current two-axis Opportunity enum wholesale.

## 28. Proposed item/requirement model

The current `OpportunityItem` contains both quote/commercial fields and
operational requirement fields. The safe P1 decision is to keep the physical
identity and behavior stable while introducing a Project-facing vocabulary.

Recommended direction:

```text
ProjectItem
    = persisted operational/commercial line during transition

ProjectRequirement
    = future operational demand intent if quotation data is separated
```

Do not rename `OpportunityItem` until the quotation boundary, versioning behavior,
and demand source semantics are decided. A direct rename would affect item tree
paths, events, APIs, shortage resolutions, invoice items, asset assignments, and
tests.

## 29. Future Quotation boundary

Future only:

```text
Quotation
├── customer-facing sections/items
├── pricing, discounts, tax presentation
├── validity and revisions
├── sent/accepted/declined lifecycle
└── no operational Demand by itself

Accepted Quotation
        ↓ conversion
Project/job
        ↓
Project items / requirements
        ↓
Demand → Allocation → Warehouse execution
```

Likely quotation responsibilities to extract from current Opportunity are:

- customer-facing quote versions and alternatives;
- pricing presentation and commercial discounts;
- quote validity/expiry;
- sent/accepted/declined states;
- quotation documents and terms.

Current implementation still combines these with order execution. P0 does not
split them.

## 30. Migration strategy

The migration must keep one authoritative write path at each stage:

```text
CURRENT
Opportunity event stream + projections
Current Project grouping parent
        ↓
TRANSITION
Project/job vocabulary and read contracts over legacy Opportunity identity
Explicit ProjectGroup compatibility for current grouping semantics
        ↓
TARGET
Independent Quotation
Canonical operational Project/job
Project items/requirements → Demand → warehouse
```

Rules:

1. No global search/replace.
2. No simultaneous FK rewrite.
3. No dual-write without an idempotent event and reconciliation plan.
4. No route/API removal until compatibility telemetry shows no consumers.
5. Keep rollback at every read/write boundary.
6. Keep event replay deterministic during every schema step.

## 31. Database migration sequence

Recommended sequence:

1. Inventory live FK usage and external consumers.
2. Add explicit schema metadata/documentation for the future Project/job
   identity; do not rename tables.
3. Introduce compatibility read models or query boundaries that expose Project
   terminology over the existing Opportunity projection.
4. Decide and document the fate of `projects` as grouping before changing its
   relationships.
5. If needed, add a nullable, backfillable Project/job identity reference with
   indexes; do not repoint all operational FKs in one migration.
6. Backfill and reconcile in batches, with counts and orphan checks.
7. Move one dependent table family at a time: items, versions/costs,
   demand-source mapping, warehouse/asset references, invoices, and external
   records.
8. Only after all consumers migrate, consider final table/column renames.
9. Keep old columns/routes readable for a deprecation window before removal.

The initial recommendation is to keep `opportunities` and
`opportunity_items` as physical tables during the entire first migration window.

## 32. PHP/model migration sequence

1. Introduce explicit domain interfaces/read contracts without changing model
   names.
2. Add Project/job-facing application services that delegate to current
   Opportunity Actions, not duplicate them.
3. Move read consumers first: planner, availability/shortage presentation,
   Experimental quotation composition, and reporting boundaries.
4. Move write consumers one Action family at a time.
5. Rename only after model event/projection compatibility is tested.
6. Keep the current `Opportunity` model as a deprecated adapter during the
   compatibility window; do not use `class Opportunity extends Project` by
   default.

Inheritance is unsafe as a first compatibility mechanism because Eloquent table
names, casts, relationships, event/projection classes, route binding, morph
types, and `auditable_type` semantics would not automatically converge.

## 33. Actions/events migration sequence

Classify before renaming:

```text
Legacy naming only:
    AddOpportunityItem, UpdateOpportunityItemDetails, ItemsRestructured,
    OpportunityItemPlanningReader, OpportunityLineItemsTreeBuilder

Commercial-specific candidates for future Quotation:
    quote versions, quote send/accept/decline, deal price, quote validity

Operational-specific candidates for future Project:
    asset allocation, prepare, dispatch, return, fulfilment, demand sync,
    shortage/availability orchestration
```

During transition, keep event class names and webhook names stable. Introduce
new Project-facing names only when a new semantic boundary exists; do not rename
an event merely because a string changed.

## 34. UI migration sequence

1. Keep `/opportunities/*` routes and existing production editor stable.
2. Introduce Project/job terminology in new read-only surfaces first.
3. Keep current Project grouping UI until a product decision names it
   `ProjectGroup` or replaces it.
4. Move operational job UI to Project-facing routes only after the backing read
   contract is stable.
5. Keep the Experimental Quotation Editor isolated; first point it at a
   compatibility contract, then later a true Quotation aggregate.
6. Preserve user-visible links with redirects and deprecation notices.

## 35. API migration sequence

1. Keep `/api/v1/opportunities` and `opportunity_id` contracts stable.
2. Add Project/job endpoints only for a clearly defined new resource, not as a
   duplicate alias with ambiguous ownership.
3. Publish response/request compatibility mappings and deprecation dates.
4. Keep webhook event names stable while adding new event versions only when
   payload semantics change.
5. Migrate internal clients before external contract removal.
6. Remove legacy Opportunity endpoints only in a separately versioned API change.

## 36. Backward compatibility strategy

Preferred compatibility layers:

- named route redirects;
- explicit adapter services;
- stable resource serializers during the window;
- read-only compatibility relations;
- event/webhook versioning and deprecation telemetry;
- database views or nullable bridge columns only when ownership is explicit.

Avoid:

- `class Opportunity extends Project` as a blanket alias;
- dual authoritative writes;
- morph-map changes without data migration;
- silent `opportunity_id`/`project_id` reinterpretation;
- broad permission aliasing without audit.

## 37. Test migration strategy

The migration blast radius is large and should be tracked by category:

| Category | Current impact | Migration requirement |
|---|---|---|
| Opportunity lifecycle/Verbs | very high | replay and projection parity |
| Opportunity items/tree | very high | identity/path/API parity |
| Planning/availability | very high | demand source and query parity |
| Shortages/resolutions | high | unresolved semantics and source IDs |
| Warehouse/assets/containers | very high | dispatch/return/asset identity |
| Financial/invoices | high | checkout and immutable snapshots |
| API | very high | route, payload, ability, webhook compatibility |
| Livewire/UI | high | route binding and authorization |
| Experimental | medium | keep isolated and canonical |
| Projects grouping | high | cardinality and delete/assignment semantics |

P1 should add contract tests before production writes move. Every phase should
run only targeted tests for the changed family plus replay, FK, and authorization
tests that prove the compatibility boundary.

## 38. Documentation changes

This document is the P0 convergence record. Existing documents remain current
descriptions of implemented behavior:

- `docs/platform/opportunities.md` — Opportunity is currently the rental/job
  aggregate.
- `docs/platform/projects.md` — Project is currently a cross-Store grouping
  container.
- `docs/platform/availability.md` — availability is derived from stock and
  Demand, with Opportunity item context.
- `docs/platform/shortages.md` — shortages are computed per Opportunity.
- `docs/platform/invoicing.md` — invoice checkout originates from Opportunity.
- `docs/platform/experimental.md` — Experimental UI uses canonical Opportunity
  data.

No existing CURRENT statement should be silently rewritten as if the future
Project architecture were already implemented.

## 39. Risks

1. **Identity risk:** the current Opportunity has both a Verbs StateId bridge and
   a projection ID used by many FKs.
2. **Cardinality risk:** current Project is one-to-many grouping; target Project
   job semantics may be one operational aggregate.
3. **Demand risk:** changing the demand source can alter availability and
   shortage behavior.
4. **Warehouse risk:** containers, assets, dispatch, return, and permissions
   embed Opportunity identity.
5. **Financial risk:** invoice and purchase-order snapshots must remain linked to
   the correct commercial source.
6. **Event replay risk:** event class names and projection handlers are replay
   contracts, not just application classes.
7. **API risk:** Opportunity endpoints, payload keys, abilities, and webhooks
   are public compatibility surfaces.
8. **Authorization risk:** Project and Opportunity permissions are separate and
   currently intentionally combined for assignment.
9. **Multi-Store risk:** current Project's cross-Store grouping must not be
   confused with operational multi-Store execution.
10. **Quotation risk:** an unaccepted quotation must not create active demand.

## 40. Unknowns requiring product decision

1. Is the target Project one operational job or can one Project span multiple
   operational Store legs?
2. Should the current grouping Project become `ProjectGroup`, `Production`, or
   another explicit concept?
3. Does a Project have one customer and one venue, or can child operational legs
   differ?
4. Which customer-facing quote data must be immutable snapshots after acceptance?
5. Are quotation revisions independent aggregates or versions within a Quotation?
6. When exactly does accepted Quotation create Project/job items and Demand?
7. Should invoices reference Quotation, Project, or both with a frozen source
   snapshot?
8. Which activities, files, documents, and communications belong to Quotation,
   Project, or both?
9. Is operational multi-Store execution required in the first Project release?
10. Which external API/webhook consumers require the `opportunity_*` names
    indefinitely?

## 41. Proposed Phase P1

P1 should be a contract-only implementation phase:

1. Record the canonical vocabulary and ownership decision in an ADR.
2. Define a Project/job read contract over the existing Opportunity projection;
   no new table and no route change.
3. Define a separate grouping read contract for the current Project.
4. Add characterization tests for item ownership, Demand source, warehouse
   identity, invoice source, and Project assignment.
5. Add explicit boundary assertions that `Project` grouping never drives
   availability or Demand.
6. Map the first read-only consumer (preferably planner/reporting) to the new
   contract.
7. Add migration telemetry for route/API/event consumers.

P1 must stop before changing write Actions, foreign keys, event names, or
quotation behavior unless the product decisions above are resolved.

## 42. Files inspected

Representative inspected files and areas:

```text
docs/platform/opportunities.md
docs/platform/projects.md
docs/platform/experimental.md
docs/platform/availability.md
docs/platform/planner.md
docs/platform/containers.md
docs/platform/shortages.md
docs/platform/invoicing.md
docs/api/opportunities.md
app/Models/Opportunity.php
app/Models/OpportunityItem.php
app/Models/Project.php
app/Models/Demand.php
app/Models/Invoice.php
app/Models/Container.php
app/Enums/OpportunityState.php
app/Enums/OpportunityStatus.php
app/Enums/ProjectStatus.php
app/Actions/Projects/*
app/Verbs/States/OpportunityState.php
app/Verbs/Events/Opportunities/*
app/Services/Availability/*
app/Services/Planning/*
app/Services/Shortages/*
app/Http/Controllers/Api/V1/OpportunityController.php
app/Http/Controllers/Api/V1/ProjectController.php
routes/web.php
routes/api.php
database/migrations/*opportunity*
database/migrations/*project*
database/migrations/*demand*
database/migrations/*container*
database/migrations/*invoice*
database/migrations/*purchase*
tests/Feature/Projects/*
tests/Feature/Opportunities/*
tests/Feature/Api/*Opportunity*
tests/Feature/Api/*Project*
```

Repository-wide searches also covered `opportunity_id`, `project_id`, events,
listeners, observers, jobs, notifications, Blade, JavaScript, API resources,
routes, migrations, and tests.

## 43. Files changed

```text
docs/platform/project-domain.md
```

No production PHP, JavaScript, Blade, route, model, migration, API, or database
behavior was changed in P0.

## 44. Final recommendation

Treat the current Opportunity as the proven operational job aggregate. Do not
promote the current Project grouping record directly. Use an incremental
convergence plan that eventually gives the operational aggregate the Project
name, while preserving the existing Project grouping concept explicitly during
the transition.

The next implementation step is a contract and characterization phase, not a
rename.

## Required decision table

```text
Current Opportunity is primarily:
    MIXED (commercial + operational rental/job)

Current Project is primarily:
    GROUPING

Opportunity currently owns canonical line items:
    YES

Opportunity currently drives Demand:
    YES

Opportunity currently drives Availability:
    YES (through OpportunityItem context and Availability services)

Opportunity currently drives warehouse execution:
    YES

Existing Project currently owns operational line items:
    NO

Existing Project can be promoted safely:
    NO (not directly; only partially after reclassification and staged convergence)

Recommended canonical job aggregate:
    OPPORTUNITY RENAMED TO PROJECT

Recommended migration type:
    INCREMENTAL

Expected:
    INCREMENTAL

Quotation should eventually be independent from Project:
    YES

Unaccepted quotation should create Demand:
    NO

Expected:
    NO
```

OPPORTUNITY TO PROJECT CONVERGENCE AUDIT COMPLETE — READY FOR P1
