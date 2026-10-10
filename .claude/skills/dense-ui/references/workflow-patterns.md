# Enterprise workflow patterns

## Workflow classification

| Class | Operator goal | Default anatomy | Example |
|---|---|---|---|
| Record management | Find, inspect, create, edit records | List/table, form, detail | Asset Registry |
| Operational workflow | Process items through repeated states | Workspace with queue/context/progress/next action | Warehouse Picking |
| Transactional workflow | Commit a consequential business event | Context + line items + validation + finalize result | Purchase Order Receiving |
| Planning workflow | Allocate resources across time/context | Planning table/board + conflicts + detail | Project Equipment Planning |
| Review/inspection | Compare evidence and resolve exceptions | Table/history/inspection workspace | Stocktake |
| Monitoring | Detect change or exceptions | Dashboard/exception queue | Warehouse overview |
| Configuration | Change system behavior/preferences | Grouped settings form + explicit save | User roles |

CRUD is appropriate for record management. It is not the default anatomy for operational, transactional, planning, review, or monitoring workflows.

## Operational Distance

Operational Distance is the unnecessary interaction and context switching between operator goal, required information, decision, action, and confirmed result. Reduce clicks, taps, transitions, modals, scrolling, pointer travel, repeated search, repeated input, and focus loss only when safety, clarity, accessibility, auditability, and domain correctness remain intact.

Bad status path: Assets → open record → Actions → Change Status → modal → choose → Save → return to list.

Better frequent-safe path: Assets → row action → Change Status → immediate result, while preserving filters and list context. High-consequence state changes still require context and confirmation.

## Next valid action

Workflow pages SHOULD emphasize the next valid action, not every lifecycle action equally. Draft → Submit; Submitted → Receive; Partially Received → Continue Receiving; Received → Close/Review. Actions must reflect current state and permission.

## Context preservation

When returning from detail or completing an operation, preserve filters, sorting, saved view, page/cursor, scroll position, active tab, project/warehouse context, and focus where practical. Repeated operators should not reconstruct their work after every item.

## Search modes

Separate global application search, local list search, exact/prefix identifier lookup, scanner lookup, and command search. Define matching behavior and empty states for each. Empty dataset, empty filter result, and empty search result require different recovery actions.

## Workflow trees

Warehouse Picking:
- project/order/container context
- progress
- persistent scan target
- current item identity, required quantity, picked quantity, state
- exception feedback
- recent scans

Warehouse Return:
- project/container context
- scan target
- returned item and serial
- duplicate/wrong-project feedback
- remaining expected items
- completion action

Stocktake:
- stocktake identity/store
- expected vs observed quantities
- unresolved/discrepant items
- scanner/manual entry
- progress and completion review

Purchase Order Receiving:
- purchase-order context
- receive line-item table
- received quantity
- discrepancy state
- confirmation/result

Asset Inspection:
- filtered list
- selected record
- key metadata/status
- history and inspection actions
- next/previous navigation

Bulk Update:
- selection count/scope
- action and consequence
- progress
- partial failure details
- result summary

+

## Operational workflow trees

### Warehouse Picking

~~~mermaid
flowchart TD
 A[Project/order context] --> B[Progress]
 B --> C[Persistent scan target]
 C --> D[Current item: identity/required/picked/state]
 D --> E{Scan result}
 E -->|Accepted| F[Update progress and recent scans; refocus]
 E -->|Duplicate/wrong state| G[Explain exception; recover]
 F --> H[Next item or complete]
~~~

### Warehouse Return

~~~mermaid
flowchart TD
 A[Project/container context] --> B[Scan target]
 B --> C{Returned identifier}
 C -->|Valid| D[Show serial/result and remaining expected]
 C -->|Duplicate/wrong project| E[Explain and recover]
 D --> F{Remaining items?}
 F -->|Yes| B
 F -->|No| G[Review and complete return]
~~~

### Stocktake

~~~mermaid
flowchart TD
 A[Stocktake context] --> B[Scan or manual entry]
 B --> C[Compare expected and observed]
 C --> D{Difference?}
 D -->|No| E[Mark counted; advance]
 D -->|Yes| F[Flag discrepancy for review]
 E --> G[Progress]
 F --> G
 G --> H{All items counted?}
 H -->|No| B
 H -->|Yes| I[Review unresolved items; complete]
~~~

### Purchase Order Receiving

~~~mermaid
flowchart TD
 A[Purchase order context] --> B[Receive line item]
 B --> C[Enter or scan quantity]
 C --> D{Validation/discrepancy}
 D -->|Valid| E[Update received quantity]
 D -->|Conflict| F[Show discrepancy and review]
 E --> G{More lines?}
 G -->|Yes| B
 G -->|No| H[Confirm receipt and show result]
~~~

### Asset Inspection

~~~mermaid
flowchart TD
 A[Filtered asset list] --> B[Select record]
 B --> C[Show bounded detail, state, history]
 C --> D{Next inspection?}
 D -->|Yes| E[Next/previous while preserving list context]
 E --> B
 D -->|No| F[Open full record or finish]
~~~

### Bulk Update

~~~mermaid
flowchart TD
 A[Select records] --> B[Show count and scope]
 B --> C[Choose action]
 C --> D{Consequential mutation?}
 D -->|Yes| E[Confirm consequence]
 D -->|No| F[Execute]
 E --> F
 F --> G[Progress/result]
 G --> H{Partial failure?}
 H -->|Yes| I[Show failure reasons and retry eligible]
 H -->|No| J[Success summary]
~~~
