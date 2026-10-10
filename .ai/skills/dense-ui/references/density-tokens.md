# Authoritative Dense tokens

The first column is the normative desktop default. Values use px for implementation clarity; use rem or equivalent when the host system supports zoom and user font settings. These values are interpretations from SAP compact density, Carbon/Fluent/Atlassian token practice, and observed operational table patterns—not vendor mandates.

| Token | Value | Default / override | Evidence / reasoning |
|---|---:|---|---|
| `--dense-control-height` | 32px | Desktop default; 44px touch | SAP compact uses 32px rows/toolbars while touch areas remain larger. |
| `--dense-input-height` | 32px | Desktop; 44px touch | Keeps forms compact without 28px controls becoming hard to operate. |
| `--dense-button-height` | 32px | Desktop; 44px touch | Supports repeated mouse/keyboard actions; touch needs a larger target. |
| `--dense-icon-button-size` | 32px | Desktop; 44px touch | Fixed target makes toolbars predictable. |
| `--dense-table-row-height` | 36px | Desktop; 44px touch/scanner | 14px text + 20px line-height + 8px vertical rhythm; avoids tiny rows. |
| `--dense-table-header-height` | 36px | Desktop; 44px touch | Keeps headers scannable and sortable. |
| `--dense-toolbar-height` | 40px | Desktop; 48px touch | Toolbar needs control alignment and active-filter visibility. |
| `--dense-tab-height` | 36px | Desktop; 44px touch | Stable peer navigation without oversized chrome. |
| `--dense-nav-item-height` | 32px | Desktop; 44px touch | Repeated navigation remains compact but operable. |
| `--dense-selection-column` | 32px | Fixed; 44px touch | Narrow centered checkbox cell preserves table semantics. |
| `--dense-cell-padding-x` | 8px | Desktop; 12px touch | Enough separation for scanning; not padding inflation. |
| `--dense-cell-padding-y` | 8px | Desktop; 12px touch | Produces the 36px table row with 20px line-height. |
| `--dense-space-1` | 2px | Micro | Icon/label and badge internals. |
| `--dense-space-2` | 4px | Micro | Inline related elements. |
| `--dense-space-3` | 6px | Component | Compact control groups. |
| `--dense-space-4` | 8px | Component | Toolbar and field rhythm. |
| `--dense-space-5` | 12px | Group | Related field/section separation. |
| `--dense-space-6` | 16px | Section | Major groups and page header/content gap. |
| `--dense-space-7` | 24px | Page | Major regions only. |
| `--dense-radius-sm` | 4px | Controls | Restrained enterprise radius. |
| `--dense-radius-md` | 6px | Panels/overlays | Bounded context, not ordinary sections. |
| `--dense-border` | `#d8dce2` | Structural | Dividers beat decorative elevation. |
| `--dense-border-strong` | `#aeb5bf` | Focused/active structure | Stronger boundary where needed. |
| `--dense-surface` | `#ffffff` | Workspace | Primary work surface. |
| `--dense-surface-subtle` | `#f0f2f5` | Toolbar/group | Shallow hierarchy. |
| `--dense-text` | `#1f2933` | Primary text | High legibility. |
| `--dense-text-secondary` | `#52606d` | Context | Readable but subordinate. |
| `--dense-text-muted` | `#697586` | Metadata only | Never for required content. |

## Touch rule

Visual density and physical hit target are separate. A dense scanner or tablet screen MAY keep 36px rows and compact grouping while exposing 44px or larger buttons, scan targets, quantity controls, and focus areas. Accessibility and device ergonomics override these desktop defaults.

