# Dense Warehouse Return

Workflow class: operational workflow.

    Return to warehouse · Project PRJ-24018 · Container FC-001     [Online]
    Scan returned asset or barcode [________________________] [Scan]

    Last scan: M32-001 · Serial 123456                  [Accepted]
    Expected remaining: 4 items
    Recent scans: M32-001, DL32-014, K2-004
    [Finish return]

Keep project/container context, persistent scan focus, serial identity, duplicate/wrong-project feedback, remaining expected items, and completion action visible. A successful scan returns focus to the scan target. A duplicate is explicit and recoverable. Do not reset the operation to a generic list after every scan.

