# Vanilla CSS reference

These framework-independent classes implement the normative Dense desktop defaults. Touch overrides enlarge physical targets without changing information priority.

    :root {
      --dense-control-height: 32px; --dense-table-row-height: 36px;
      --dense-table-header-height: 36px; --dense-toolbar-height: 40px;
      --dense-tab-height: 36px; --dense-selection-column: 32px;
      --dense-cell-padding-x: 8px; --dense-cell-padding-y: 8px;
      --dense-space-1: 2px; --dense-space-2: 4px; --dense-space-3: 6px;
      --dense-space-4: 8px; --dense-space-5: 12px; --dense-space-6: 16px; --dense-space-7: 24px;
      --dense-radius-sm: 4px; --dense-radius-md: 6px;
      --dense-border: #d8dce2; --dense-border-strong: #aeb5bf;
      --dense-surface: #fff; --dense-surface-subtle: #f0f2f5;
      --dense-text: #1f2933; --dense-text-secondary: #52606d; --dense-text-muted: #697586;
      --dense-focus: #0b63ce;
    }
    .dense-shell { display: grid; grid-template-columns: 224px minmax(0, 1fr); min-height: 100vh; background: #f5f6f8; color: var(--dense-text); }
    .dense-workspace { min-width: 0; padding: var(--dense-space-6) var(--dense-space-7); background: var(--dense-surface); }
    .dense-page-header { display: flex; align-items: start; justify-content: space-between; gap: var(--dense-space-6); margin-bottom: var(--dense-space-6); }
    .dense-page-title { margin: 0; font-size: 20px; line-height: 28px; font-weight: 600; }
    .dense-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: var(--dense-space-3); min-height: var(--dense-toolbar-height); padding: var(--dense-space-3) 0; border-block: 1px solid var(--dense-border); }
    .dense-input, .dense-select, .dense-button { min-height: var(--dense-control-height); border: 1px solid var(--dense-border-strong); border-radius: var(--dense-radius-sm); font: inherit; }
    .dense-input, .dense-select { padding: 0 var(--dense-space-3); }
    .dense-button { padding: 0 var(--dense-space-4); background: var(--dense-surface); }
    .dense-button--primary { color: #fff; border-color: #0b63ce; background: #0b63ce; }
    .dense-icon-button { width: var(--dense-control-height); min-height: var(--dense-control-height); padding: 0; }
    .dense-input:focus-visible, .dense-select:focus-visible, .dense-button:focus-visible, .dense-tab:focus-visible { outline: 2px solid var(--dense-focus); outline-offset: 2px; }
    .dense-table-wrap { overflow: auto; }
    .dense-table { width: 100%; border-collapse: collapse; font-size: 14px; line-height: 20px; }
    .dense-table th, .dense-table td { height: var(--dense-table-row-height); padding: var(--dense-cell-padding-y) var(--dense-cell-padding-x); border-bottom: 1px solid var(--dense-border); text-align: left; vertical-align: middle; }
    .dense-table th { height: var(--dense-table-header-height); background: var(--dense-surface-subtle); font-weight: 600; }
    .dense-table .is-numeric { text-align: right; font-variant-numeric: tabular-nums; }
    .dense-table .checkbox-cell { width: var(--dense-selection-column); padding-inline: 0; text-align: center; }
    .dense-table .row-actions { width: 40px; text-align: right; }
    .dense-status { display: inline-flex; align-items: center; gap: var(--dense-space-2); border-radius: 999px; font-size: 12px; line-height: 16px; }
    .dense-key-value { display: grid; grid-template-columns: minmax(120px, 180px) minmax(0, 1fr); gap: var(--dense-space-3) var(--dense-space-6); }
    .dense-key-value dt { color: var(--dense-text-secondary); }
    .dense-key-value dd { margin: 0; font-variant-numeric: tabular-nums; }
    .dense-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--dense-space-5) var(--dense-space-6); }
    .dense-tabs { display: flex; gap: var(--dense-space-5); min-height: var(--dense-tab-height); border-bottom: 1px solid var(--dense-border); }
    .dense-tab { min-height: var(--dense-tab-height); padding: 0; border: 0; border-bottom: 2px solid transparent; background: none; font: inherit; }
    .dense-tab[aria-selected="true"] { border-bottom-color: currentColor; font-weight: 600; }
    @media (max-width: 800px) { .dense-shell { grid-template-columns: 1fr; } .dense-workspace { padding: var(--dense-space-5); } .dense-form-grid { grid-template-columns: 1fr; } .dense-table [data-priority="tertiary"] { display: none; } }
    @media (pointer: coarse) { .dense-button, .dense-input, .dense-select, .dense-icon-button, .dense-tab { min-height: 44px; } .dense-table th, .dense-table td { min-height: 44px; padding-block: 12px; } }

Use semantic classes such as .dense-input, .dense-table, and .dense-toolbar; avoid broad global selectors. Use native table markup and explicit priority attributes for responsive behavior.
