# Dense UI anti-patterns

Each pattern is a review signal, not a ban without context.

| Anti-pattern | Symptom | Operational cost | Preferred alternative | Legitimate exception |
|---|---|---|---|---|
| Card Soup | Every field/section/table is a rounded card | Adds borders, shadows, and scan stops | Sections, dividers, key-value grids, tables | Independent selectable entities or strongly separated contexts |
| Button Wall | Many equal-emphasis actions compete | Slows target finding and increases errors | One primary, few secondary, overflow | Safety-critical frequent actions may stay visible |
| Badge Rainbow | Every status is saturated | Masks real exceptions | Quiet semantic hierarchy with text/icon | High-risk alert may dominate |
| Padding Inflation | 48–72px rows and huge page gaps | Fewer records visible, more scrolling | Dense tokens and purposeful whitespace | Touch or accessibility adaptation |
| Hero Header in CRUD | Large decorative title/banner | Pushes task content below fold | Compact identity/status/actions header | Onboarding or narrative overview with proven need |
| Icon Confetti | Icons before every label/action | Visual noise and slower parsing | Use icons for scan/meaning; text for unfamiliar actions | Repeated familiar status/action icon with accessible name |
| Modal Everything | Complex forms/workflows in dialogs | Context loss, focus/error complexity | Dedicated page or drawer | Short, bounded, reversible action |
| Hidden Critical Actions | Important action buried in overflow | Missed workflow or unsafe delay | Visible contextual primary action | Rare/destructive action with explicit discoverability |
| Tiny Touch UI | Desktop 28–32px targets on scanner/tablet | Mis-scans and operator fatigue | 44px+ physical targets, compact grouping | Pointer-only desktop controls |
| Spreadsheet Everywhere | Every screen is editable grid | Poor hierarchy, accidental edits | Read-only table + focused edit form | True batch-edit workflow |
| Dashboard KPI Abuse | Many large metrics with little action | Consumes space without decisions | Few actionable metrics + exception table | Executive overview with distinct purpose |
| Excessive Nested Panels | Background/card/card/field card | Deep visual hierarchy hides relationships | Shallow surfaces and dividers | Real nesting such as modal over workspace |
| Placeholder-as-Label | Meaning disappears after typing | Slower data entry and accessibility failure | Persistent visible labels | Search hint in addition to label |
| Truncation Without Access | Text cut with no full value | Wrong selection/decision | Tooltip/focus/detail accessible full value | Non-semantic decorative text |
| Horizontal Scroll Without Priority | Columns disappear unpredictably | Identity/context loss | Priority columns, sticky identity, pop-in | Comparison task requiring full table |
| Equal Visual Weight Everywhere | Title, metadata, warnings look alike | Exceptions missed, scan time increases | Hierarchy by task importance | Rare audit screens where equal review is intended |

+

## V3 anti-patterns

| Anti-pattern | Symptom | Operational cost | Better pattern | Exception |
|---|---|---|---|---|
| CRUD EVERYTHING | Every workflow is list/create/edit/delete | State transitions and next actions disappear | Classify workflow; use operational/transaction/planning anatomy | True record management |
| CLICK MAZE | Frequent work crosses pages/modals repeatedly | High operational distance and context loss | Keep required context and action together | High-consequence review |
| MODAL CHAIN | Modal opens another modal repeatedly | Focus confusion and error recovery cost | Page or bounded drawer | Short confirmation over a focused edit |
| ACTION HIDING | Frequent core action buried in deep overflow | Slower repeated work and poor discoverability | Keep core/frequent action visible | Rare/admin action |
| CONFIRM EVERYTHING | Every mutation asks for confirmation | Confirmation fatigue and habituation | Immediate result or undo for low-risk actions | Destructive/consequential action |
| SILENT FAILURE | Action fails without reason or recovery | Repeated attempts and loss of trust | Local actionable error with retry/review | Security-sensitive details must remain hidden |
| DISABLED MYSTERY | Control is disabled with no explanation | Operator cannot diagnose next step | Explain state/permission/domain reason | Irrelevant action should be hidden |
| CONTEXT RESET | Filters, scroll, focus, view reset after action | Repetitive reconstruction and missed work | Preserve context on return | Context intentionally changes |
| TOAST STORM | Many transient notifications compete | Feedback becomes noise and can be missed | Inline summary, result panel, or quiet status | Low-risk isolated success |
| DASHBOARDIZATION | Active workspace replaced by KPIs/charts | Operators cannot perform the task efficiently | Operational workspace + exception summary | Monitoring-only screen |
| TIMELINE EVERYTHING | All history is a decorative timeline | Comparison/filtering becomes slow | Dense history table when audit fields matter | Narrative activity |
| PERSONALIZATION CHAOS | Users can remove identity/status/action structure | Inconsistent unsafe views | Mandatory columns and reset defaults | Non-critical optional metadata |
| WORKFLOW AS FORM | Multi-state operation is one giant edit form | Next action, progress, and exceptions disappear | Workflow workspace with state/result | Simple record edit |

