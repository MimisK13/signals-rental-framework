# Dense Stocktake

Workflow class: review/inspection workflow, not generic CRUD.

    Stocktake ST-2026-041 · Thessaloniki · In progress
    Progress 184 / 420 items · 7 discrepancies · 3 unresolved

    Scan item or barcode [________________________] [Scan]

    Item        Expected   Observed   Difference   State        Last scan
    XLR-5M            120        118          -2    Review       14:32
    M32-001             1          1           0    Counted      14:31
    CAT6-10M           48         49          +1    Review       14:29

    [Review discrepancies] [Pause] [Complete stocktake]

Primary information is stocktake context, progress, expected/observed discrepancy, and next action. Keep recent scan feedback and unresolved work visible. Completion requires review context; do not model this as a single record edit page.

