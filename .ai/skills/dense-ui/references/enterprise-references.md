# Enterprise evidence and cross-system matrix

Verification date: 2026-10-09. Product behavior changes; re-check links before making product-specific claims. Sources below are official/public. Statements are classified as FACT, OBSERVATION, INTERPRETATION, or RULE.

## Rentman case study

Official starting points: [Rentman](https://rentman.io/), [Warehouse workflow](https://support.rentman.io/hc/en-us/sections/14204209111826-Warehouse-Workflow), [Warehouse booking screen](https://support.rentman.io/hc/en-us/articles/14004547874834-Overview-of-the-Booking-Screen-in-the-Warehouse-module), [Warehouse list view](https://support.rentman.io/hc/en-us/articles/15125682136850-Warehouse-Module-List-View), [Using columns](https://support.rentman.io/hc/en-us/articles/360012581679-Using-Columns-in-your-Overview), [Saved views](https://support.rentman.io/hc/en-us/articles/26916975812370-Customizing-Overviews-using-Views), [Project equipment planning](https://support.rentman.io/hc/en-us/articles/360013730520-Plan-Equipment-on-a-Project), [Equipment booking](https://support.rentman.io/hc/en-us/articles/13901986722322-Equipment-Booking-in-Rentman), [Warehouse tile view](https://support.rentman.io/hc/en-us/articles/15125679219858-Warehouse-Module-Tile-View), [Tracking log](https://support.rentman.io/hc/en-us/articles/20860250675474-Warehouse-Tracking-Log).

- **FACT:** Rentman documents configurable overview columns, sorting, multi-level sorting, grouping, column resize, pinning, and saved column/view sets. It documents filters by stock location/date and warehouse list/tile views.
- **FACT:** The booking screen documents grouping by equipment group or warehouse location, sorting by name/code/location, combining equipment, and virtual-combination handling.
- **FACT:** Rentman documents equipment/project status distinctions, scan return, serial-number workflows, containers/combinations, and permissions around warehouse projects/locations.
- **INTERPRETATION:** operational users need configurable, saved, exception-oriented views and context-aware grouping; a fixed “one true table” is less useful than a dense table with controlled personalization.
- **RULE:** Dense UI list pages MUST expose common filters, active filters, column/view controls, and predictable grouping/sorting. Warehouse screens SHOULD group by physical retrieval path when the task is picking/packing. Do not copy Rentman styling or assume its statuses fit another domain.
- **ADOPT:** column personalization, saved views, grouping/sorting, project/warehouse context, scan-return concepts.
- **ADAPT:** use low-noise status hierarchy and semantic tokens; preserve native table semantics and keyboard access.
- **REJECT:** copying vendor colors, exact layout, or undocumented behavior.

## SAP Fiori

Official: [Fiori Web Guidelines](https://experience.sap.com/fiori-design-web/), [Content density](https://experience.sap.com/fiori-design-web/cozy-compact/), [Table overview](https://experience.sap.com/fiori-design-web/table-overview/), [Responsive table](https://experience.sap.com/fiori-design-web/responsive-table/), [Input field](https://experience.sap.com/fiori-design-web/input-field/).

- **FACT:** SAP documents compact for mouse/keyboard and cozy for touch, stable font sizing, smaller compact controls/spaces, larger touch areas, and application-level density consistency.
- **FACT:** SAP responsive tables preserve identifier/key attributes and move lower-priority data to labelled pop-in areas; desktop-centric tables are appropriate for larger datasets.
- **INTERPRETATION:** density is a coherent system and responsive behavior must follow information priority, not arbitrary shrinking.
- **RULE:** Dense desktop is normative; touch is an application-level adaptation. Keep identity/key attributes visible and move secondary data into labelled responsive presentation.

## Odoo

Official: [Odoo](https://www.odoo.com/), [Search, filter, and group records](https://www.odoo.com/documentation/17.0/applications/essentials/search.html).

- **FACT:** Odoo documents a view-level search bar with Search, Filter, Group By, and Favorites.
- **OBSERVATION:** Odoo record workflows commonly pair list/search views with form views, status/action areas, breadcrumbs, and relational fields.
- **INTERPRETATION:** persistent query controls reduce navigation and repeated setup.
- **RULE:** keep search and common filters adjacent to the list; expose saved views and grouping without turning the toolbar into a wall of equal buttons.

## ERPNext / Frappe

Official: [ERPNext](https://erpnext.com/), [Frappe Desk](https://docs.frappe.io/framework/user/en/desk).

- **FACT:** Frappe Desk documents list views, filters, sorting, paging, tags, alternate views, forms, timelines, and attachments.
- **INTERPRETATION:** predictable list/detail/form transitions and related-record context support operational work.
- **RULE:** use a shared shell and record context across list, form, and detail; keep generated defaults subject to task and accessibility review.

## Microsoft Dynamics 365

Official: [Dynamics 365](https://www.microsoft.com/en-us/dynamics-365), [Dynamics 365 filtering options](https://learn.microsoft.com/en-us/dynamics365/fin-ops-core/dev-itpro/user-interface/filtering).

- **FACT:** Dynamics 365 Finance and Operations documents filter panes, quick filters, grid column filtering/sorting, and advanced filter/sort.
- **INTERPRETATION:** filters can scale from fast single-field refinement to complex query construction without permanently consuming page space.
- **RULE:** show common quick filters inline and advanced filtering in a panel/page; keep the current filter state visible and reversible.
- **LIMITATION:** do not infer current visual styling or all Dynamics products from the marketing page or old screenshots.

## Mature design systems

- [IBM Carbon data table](https://carbondesignsystem.com/components/data-table/usage/), [Carbon accessibility](https://carbondesignsystem.com/guidelines/accessibility/overview/): **FACT/INTERPRETATION**—data tables and state tokens support structured comparison, focus, hover, selection, and contextual state; use tokenized states rather than ad-hoc colors.
- [Microsoft Fluent 2 accessibility](https://fluent2.microsoft.design/accessibility), [Fluent list usage](https://fluent2.microsoft.design/components/web/react/core/list/usage): **FACT/INTERPRETATION**—accessibility is foundational; choose list/grid semantics from interaction rather than appearance.
- [Atlassian spacing](https://atlassian.design/foundations/spacing), [Atlassian typography](https://atlassian.design/foundations/typography), [Atlassian tokens](https://atlassian.design/tokens/design-tokens/): **FACT/INTERPRETATION**—a tokenized spacing/typography system supports rhythm, grouping, and consistent adaptation; do not copy brand values wholesale.

## Cross-system pattern matrix

| Pattern | Rentman | SAP | Odoo | ERPNext/Frappe | Dynamics | Dense UI decision |
|---|---|---|---|---|---|---|
| Application shell | Module workspace, overview context | Launchpad/app shell | App menus, breadcrumbs | Desk/workspace | Workspaces/pages | Domain nav + compact workspace; keep levels distinct |
| Sidebar | Module/domain navigation | App/global navigation | App navigation | Workspace/sidebar | Workspace navigation | Global nav for domains; local nav for record sections |
| Page header | Overview/project context | Object/page header | Record title/status/actions | Doc title/status/actions | Page title/command bar | Compact identity + status + few actions |
| Breadcrumbs | Contextual project/module path | Hierarchy | Common record context | Desk/document context | Page hierarchy | Keep above identity when hierarchy matters |
| Local tabs | Project/equipment sections | Object page sections | Notebook/tabs | Form tabs/sections | FastTabs/sections | Tabs only for stable peer sections |
| Tables | Configurable columns, grouping | Responsive/desktop table types | List views | List views | Grids | Tables first for comparison |
| Row density | Operational list/booking | Compact mouse/keyboard | Compact records | Compact records | Dense grids | 36px normative row; touch override |
| Filters/search | Filters, views, date/location | Filter bars/table settings | Search + Filter + Group By + Favorites | Filters/tags | Quick/filter pane/advanced | Common filters inline; advanced progressive |
| Configurable columns | Explicit add/hide/pin/sets | Personalization/table settings | View fields | List customization | Grid personalization | User-controlled secondary/tertiary columns |
| Row actions | Context menus/more options | Toolbar/context actions | Record links/action menus | Doc actions | Command bars/menus | Identity link + trailing overflow |
| Bulk actions | Status/warehouse operations | Selection/toolbars | List actions | Bulk actions | Grid actions | Selection summary + safe confirmation |
| Forms | Project/equipment fields | Forms/field groups | Form views/notebooks | Doc forms/tabs | Record forms | Grouped visible-label forms; one/two column by field complexity |
| Detail pages | Project/equipment context | Object pages | Record/form | Doc/timeline/related | Record pages | Compact header + key-value + related tables |
| Status | Equipment/project statuses | Semantic states | Status bars/labels | Doc statuses | Record status | Text/icon always; saturation by actionability |
| Key-value metadata | Equipment/project context | Object attributes | Record fields | Doc fields | Record fields | Structured grid, not card per field |
| Cards | Tile view for specific warehouse overview | Not default table structure | Kanban/context use | Workspace/dashboard uses | Context-dependent | Cards only for selectable/independent groups |
| Toolbars | Filters/views/columns | Table toolbar | Search/action controls | List controls | Command bar/filter | Few visible actions; overflow secondary |
| Drawers | Context dependent | Flexible column/panels | Context dependent | Context dependent | Side panes | Preserve surrounding list only for bounded tasks |
| Modals | Short actions/confirmations | Focused dialogs | Short dialogs | Short dialogs | Dialogs | Never complex multi-step workflow |
| Warehouse/scanner | Strongest operational evidence | Touch adaptation | Domain dependent | Domain dependent | Domain dependent | Dense information + touch-sized targets |
| Responsive | List/tile alternatives | Pop-in or task-specific mobile | Responsive views | Responsive Desk | Device-specific layouts | Preserve workflow, not desktop pixels |

The matrix is not a vote. The rules are selected for operational efficiency, scanability, predictable interaction, accessibility, and implementation clarity.

+

## V3 interaction and workflow evidence

- **Rentman FACT:** role permissions distinguish access to projects/stock locations, changing project status, packing without scanning, damage/loss stock changes, combination emptying, and bulk edits. **INTERPRETATION:** permission-aware operations should explain whether the blocker is authorization or current workflow/domain state. **RULE:** keep the reason truthful and do not expose sensitive information. Sources: https://support.rentman.io/hc/en-us/articles/22185539659666-User-Role-Permissions-in-Rentman and https://support.rentman.io/hc/en-us/articles/360013838520-How-to-bulk-edit-items
- **Rentman FACT:** saved Views combine filters, tags, columns, dates, and visibility; overviews support configurable columns and grouping. **INTERPRETATION:** saved operational slices reduce repeated setup. **RULE:** implement saved views when operators repeatedly reconstruct the same queue; preserve mandatory identity/action columns. Source: https://support.rentman.io/hc/en-us/articles/26916975812370-Customizing-Overviews-using-Views
- **SAP FACT:** actions belong near affected content; page/header actions, table actions, footer/finalizing actions, and row/field actions have different scope. SAP documents hiding unauthorized actions and disabling actions that are temporarily unavailable, with explanations where useful. **INTERPRETATION:** action hierarchy and reason-correct availability reduce operational distance. **RULE:** place actions by scope, keep frequent/core actions discoverable, and distinguish hidden, disabled, read-only, and unavailable. Source: https://experience.sap.com/fiori-design-web/action-placement/
- **SAP FACT:** toast is for short non-semantic success; it should not carry errors, warnings, or confirmation decisions. **INTERPRETATION:** routine success should not interrupt the workflow. **RULE:** use inline/banner/dialog for persistent or consequential feedback. Source: https://experience.sap.com/fiori-design-web/toast-web-component/
- **Carbon FACT:** inline, toast, and modal notifications have different disruption and persistence; dialogs interrupt and are suitable for required decisions or destructive confirmation. **INTERPRETATION:** feedback type should follow consequence and reading requirement. **RULE:** do not use toast for critical or long messages; keep actionable errors near the affected context. Sources: https://www.carbondesignsystem.com/building-blocks/core/components/notification and https://www.carbondesignsystem.com/building-blocks/core/patterns/dialogs
- **SAP FACT:** table toolbar actions apply to a table and row actions should be limited to substantial use cases. **INTERPRETATION:** bulk/context actions belong near the table; row action walls increase scan cost. **RULE:** reveal bulk actions with selection and use one visible row action plus overflow when appropriate. Source: https://experience.sap.com/fiori-design-web/explore_group/toolbar/
- **Rentman FACT:** task views can switch between board/list, filter, group, sort, and share based on permissions. **INTERPRETATION:** view mode is a workflow choice, not a universal replacement. **RULE:** use board/card views for assignment/overview when appropriate; use tables for comparison/detail. Source: https://support.rentman.io/hc/en-us/articles/38422661951506-Task-Boards-Views-Grouping-and-Sharing

The V3 rules are interpretations and final Dense UI decisions, not claims that any vendor requires Operational Distance, master-detail, or a specific permission message.

