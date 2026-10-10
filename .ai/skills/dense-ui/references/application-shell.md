# Application shell and layout standards

## Preferred shell

```text
Application
├── Global navigation: domains, recents, favorites, search
└── Workspace
    ├── Breadcrumb / context
    ├── Compact page header: identity, status, primary actions
    ├── Local tabs or section navigation when needed
    ├── Toolbar: search, common filters, active filters, view controls
    └── Main content: table, form, detail, planning surface, or scanner
```

Global navigation MUST name major domains. Local navigation MUST name peer sections of the current entity/workflow. Contextual actions MUST affect the current context. Do not mix these levels in one undifferentiated sidebar.

## Page header

Use a compact, non-marketing header. Breadcrumb sits above or adjacent to the identity. The title is 20px/28px semibold; secondary identity/context is 13–14px secondary text; status sits beside the title or in the metadata line; primary actions trail on desktop and collapse into a clear action menu on narrow screens. Keep vertical header spacing to 8px between lines and 12–16px before content.

Example:

```text
Assets / Audio / M32-001
M32-001                                      [Available] [Edit] [⋯]
Midas M32 · Thessaloniki · Audio / Consoles
```

## Screen trees

```text
Dense List Page
├── Context / Breadcrumb
├── Page Header: title + count + primary action
├── Toolbar: search + common filters + active filters + columns/views
└── Data Table: selection + identity + operational context + status + overflow

Dense Detail Page
├── Breadcrumb
├── Compact Record Header: identity + status + actions
├── Key Summary: key-value grid
├── Local Tabs: stable peer sections
└── Tab Content: compact sections + related tables

Dense Form Page
├── Breadcrumb
├── Page Header: identity/context + save/cancel
├── Grouped form sections
├── Inline field errors and summary
└── Action row: save, secondary, destructive separated

Warehouse Scanner
├── Project/order/container context
├── Persistent scan target and connection state
├── Current item + required/picked quantity
├── Immediate success/error feedback
├── Quantity correction / exception action
└── Recent scans + progress

Planning View
├── Context and date range
├── Search/filter/availability controls
├── Grouped equipment/resource table
├── Conflict/exception column
└── Planning actions and detail side panel

Dashboard
├── Context and time range
├── Small number of actionable summary metrics
├── Exception/work queue table
└── Drill-down links; no decorative KPI wall
```

Use the table-first decision tree in `decision-trees.md` when the screen type is ambiguous. This shell is a normative starting point, not a license to add every region to every page.
