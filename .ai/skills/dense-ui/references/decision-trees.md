# Dense UI decision trees

These trees encode default decisions. Exceptions must name the workflow constraint.

## Table vs cards vs structured list

```mermaid
flowchart TD
 A[Multiple records] --> B{Compare common attributes?}
 B -->|Yes| T[Table/data grid]
 B -->|No| C{Each record independently selectable or visual?}
 C -->|Yes| K[Cards may be appropriate]
 C -->|No| L[Structured list]
 T --> D{Mobile?}
 D -->|Yes| E[Keep identity + key attribute; pop-in/stack lower priority]
 D -->|No| F[Dense rows with sortable/filterable columns]
```

## Page vs drawer vs modal vs inline

```mermaid
flowchart TD
 A[Operation] --> B{Complex, multi-step, high consequence?}
 B -->|Yes| P[Dedicated page]
 B -->|No| C{Context must remain visible?}
 C -->|Yes| D{Small bounded edit/inspection?}
 D -->|Yes| R[Drawer / side panel]
 D -->|No| P
 C -->|No| E{Short focused decision or small form?}
 E -->|Yes| M[Modal]
 E -->|No| I[Popover or inline]
```

## Tabs vs sections

```mermaid
flowchart TD
 A[Content groups] --> B{Stable peer sections of one entity?}
 B -->|Yes| T[Tabs]
 B -->|No| C{Must be visible together to complete task?}
 C -->|Yes| S[Sections on one page]
 C -->|No| D{Independent workflow/context?}
 D -->|Yes| P[Separate page/workflow]
 D -->|No| S
```

## Visible vs overflow action

```mermaid
flowchart TD
 A[Action] --> B{Frequent and primary to this context?}
 B -->|Yes| V[Visible primary/secondary action]
 B -->|No| C{Destructive or high consequence?}
 C -->|Yes| O[Overflow + explicit confirmation]
 C -->|No| D{Needed for row comparison/task speed?}
 D -->|Yes| V
 D -->|No| O[Trailing overflow]
```

## Dense desktop vs touch adaptation

```mermaid
flowchart TD
 A[Workflow] --> B{Mouse + keyboard desktop?}
 B -->|Yes| D[Dense dimensions and keyboard-first interaction]
 B -->|No| C{Touch, gloves, scanner, or mobile?}
 C -->|Yes| T[Keep dense information grouping; enlarge hit targets and feedback]
 C -->|No| D
```

## Column priority

```mermaid
flowchart TD
 A[Column] --> B{Needed to identify, decide, or act?}
 B -->|Yes| P[PRIMARY: retain]
 B -->|No| C{Frequently needed context?}
 C -->|Yes| S[SECONDARY: pop-in, detail, or user-hideable]
 C -->|No| T[TERTIARY: hide or personalize]
```

## Status emphasis

```mermaid
flowchart TD
 A[Status] --> B{Requires immediate action or indicates loss/failure?}
 B -->|Yes| C[Critical/danger emphasis + text/icon]
 B -->|No| D{Requires attention soon?}
 D -->|Yes| W[Warning emphasis + text/icon]
 D -->|No| N[Quiet neutral/positive/informational treatment]
```

## One vs two-column form

```mermaid
flowchart TD
 A[Field group] --> B{Long, dependent, high-risk, or substantial help?}
 B -->|Yes| O[One column]
 B -->|No| C{Short independent fields with obvious pairing?}
 C -->|Yes| T[Two columns at wide desktop]
 C -->|No| O
 T --> D[Collapse to one column at narrow width]
```
+

## V3 workflow classification

~~~mermaid
flowchart TD
 A[Requested screen] --> B{Primarily managing records?}
 B -->|Yes| R[Record management: list/form/detail]
 B -->|No| C{Repeatedly processing items through states?}
 C -->|Yes| O[Operational workspace: queue/context/progress/next action]
 C -->|No| D{Committing a consequential business event?}
 D -->|Yes| T[Transactional workflow: context/lines/validation/finalize]
 D -->|No| E{Allocating resources across time/context?}
 E -->|Yes| P[Planning workflow: table/conflicts/detail]
 E -->|No| F{Reviewing evidence or exceptions?}
 F -->|Yes| I[Inspection workflow: table/history/resolve]
 F -->|No| G{Monitoring changing conditions?}
 G -->|Yes| M[Dashboard/exception queue]
 G -->|No| Cfg[Configuration form/workspace]
~~~

## CRUD vs operational workspace

~~~mermaid
flowchart TD
 A[Screen request] --> B{Primary goal is record lifecycle management?}
 B -->|Yes| C[CRUD/list-form-detail]
 B -->|No| D{Repeated processing, state transitions, or physical work?}
 D -->|Yes| W[Operational workspace]
 D -->|No| E{Planning, review, transaction, or monitoring?}
 E -->|Yes| S[Specialized workflow anatomy]
 E -->|No| C
~~~

## Hide vs disable vs read-only

~~~mermaid
flowchart TD
 A[Action or field] --> B{Sensitive or irrelevant here?}
 B -->|Yes| H[Hide]
 B -->|No| C{Value must be inspected but not edited?}
 C -->|Yes| R[Read-only]
 C -->|No| D{Relevant but temporarily unavailable?}
 D -->|Yes| E{Clear way to enable?}
 E -->|Yes| X[Disable with explanation]
 E -->|No| U[Show unavailable reason or hide]
 D -->|No| V[Enable]
~~~

## Confirmation vs immediate action

~~~mermaid
flowchart TD
 A[Mutation] --> B{Destructive, irreversible, broad, external, or authoritative?}
 B -->|Yes| C{Undo/reversal safe and available?}
 C -->|Yes| U[Act then offer undo/reversal]
 C -->|No| Q[Contextual confirmation]
 B -->|No| I[Immediate action with clear result]
~~~

## Inline edit vs form

~~~mermaid
flowchart TD
 A[Edit request] --> B{Frequent, simple, low/moderate consequence?}
 B -->|Yes| C{One value or simple invariant?}
 C -->|Yes| I[Inline edit]
 C -->|No| F[Focused form/drawer/page]
 B -->|No| D{Complex, high-risk, or multi-field invariant?}
 D -->|Yes| F
 D -->|No| I
~~~

## Saved view needed?

~~~mermaid
flowchart TD
 A[List workflow] --> B{Operators repeatedly reconstruct same slice?}
 B -->|Yes| V[Saved views justified]
 B -->|No| C{Shared queue or stable team operating view?}
 C -->|Yes| V
 C -->|No| D[Filters/columns without saved view]
~~~

## History table vs timeline

~~~mermaid
flowchart TD
 A[History] --> B{Need compare/filter/sort actor/time/change?}
 B -->|Yes| T[Dense history table]
 B -->|No| C{Chronological narrative primary?}
 C -->|Yes| L[Timeline/activity feed]
 C -->|No| T
~~~

## Autosave vs explicit save

~~~mermaid
flowchart TD
 A[Edited value] --> B{Low-risk isolated preference?}
 B -->|Yes| A1[Autosave with clear saved state]
 B -->|No| C{Complex, financial, dependent, or review-required?}
 C -->|Yes| S[Explicit Save]
 C -->|No| D[Choose by consequence and user expectation]
~~~

## Wizard vs single form

~~~mermaid
flowchart TD
 A[Form workflow] --> B{Sequence/dependencies make order meaningful?}
 B -->|Yes| C{Uncommon or complex enough to need progress?}
 C -->|Yes| W[Wizard]
 C -->|No| F[Single grouped form]
 B -->|No| F
~~~

## Dashboard vs workspace

~~~mermaid
flowchart TD
 A[Screen goal] --> B{Monitor, summarize, detect exceptions?}
 B -->|Yes| D[Dashboard/exception queue]
 B -->|No| C{Actively process items repeatedly?}
 C -->|Yes| W[Operational workspace]
 C -->|No| D
~~~

## Bulk action confirmation

~~~mermaid
flowchart TD
 A[Selection exists] --> B{Action mutates or deletes records?}
 B -->|No| I[Immediate result with count]
 B -->|Yes| C{Broad, consequential, or irreversible?}
 C -->|Yes| Q[Confirm scope and consequence]
 C -->|No| I
 I --> R[Progress/result + partial failures]
 Q --> R
~~~

## Immediate vs long-running feedback

~~~mermaid
flowchart TD
 A[Operation] --> B{Completes effectively immediately?}
 B -->|Yes| I[Immediate state/result]
 B -->|No| C{Meaningful progress available?}
 C -->|Yes| P[Progress with cancel where safe]
 C -->|No| G[Background status and completion notification]
~~~

## Drawer vs page vs modal

~~~mermaid
flowchart TD
 A[Contextual operation] --> B{Complex, multi-step, or high-consequence?}
 B -->|Yes| P[Dedicated page]
 B -->|No| C{Context should remain visible?}
 C -->|Yes| D{Bounded inspection/edit?}
 D -->|Yes| R[Drawer]
 D -->|No| P
 C -->|No| M[Short modal or inline operation]
~~~

+

## Focus, selection, and active record

~~~mermaid
flowchart TD
 A[Table interaction] --> B{Keyboard target?}
 B -->|Yes| F[Focus-visible: receives keyboard input]
 B -->|No| C{Included in batch?}
 C -->|Yes| S[Selected: part of selection set]
 C -->|No| D{Shown in detail pane?}
 D -->|Yes| O[Active/open record]
 D -->|No| N[Ordinary row]
~~~

## Search mode

~~~mermaid
flowchart TD
 A[Search request] --> B{Across application domains?}
 B -->|Yes| G[Global search]
 B -->|No| C{Identifier/scanner input?}
 C -->|Yes| I[Exact or prefix lookup]
 C -->|No| L[Local list search]
~~~

## Empty state

~~~mermaid
flowchart TD
 A[No results] --> B{No records exist?}
 B -->|Yes| D[Empty dataset: create/import guidance]
 B -->|No| C{Filters active?}
 C -->|Yes| F[Empty filter result: clear/refine filters]
 C -->|No| S[Empty search result: revise query or exact-match hint]
~~~

