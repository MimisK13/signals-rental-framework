# Dense Master-Detail Asset Inspection

Workflow class: rapid inspection workflow.

    Assets (filtered: Store = Thessaloniki, Status = Available)
    ┌─────────────────────────────┬──────────────────────────────┐
    │ M32-001  Midas M32          │ M32-001              Available │
    │ M32-002  Midas M32          │ Midas M32 · Thessaloniki      │
    │ DL32-014 Midas DL32         │ Serial: 123456                │
    │                             │ Recent history                │
    │                             │ [Open full record] [Edit]     │
    └─────────────────────────────┴──────────────────────────────┘

Use master-detail when inspecting many bounded records without losing filters, list context, or scroll position. Keyboard Up/Down moves through the master list; Enter opens the full record. Focused row, selected row, and active detail record may differ and must not share one visual state. On mobile, switch to list → detail navigation.

