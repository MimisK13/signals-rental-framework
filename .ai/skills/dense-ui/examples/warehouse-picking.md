# Dense Warehouse Picking

Operator: a picker scanning equipment for a project or container.

    Project: PRJ-24018 · Athens Festival · Return to warehouse     [Online]
    Scan item or barcode [________________________] [Scan]

    Current item: L-Acoustics K2       Required 12   Picked 8   Remaining 4
    Last scan: K2-004                    [Accepted]
    Recent scans: K2-001, K2-002, K2-003, K2-004
    Progress: 8 / 12

Keep project/order/container context, persistent scan target, current item, quantity, exception, recent scans, and progress visible. Maintain focus after a successful scan. Duplicate scans must be explicit and reversible; scanner errors need immediate visible text/icon feedback and retry. Quantity correction is a touch-sized action, not a tiny modal.

Information grouping remains dense, but scan/action targets are 44px or larger for tablets, gloves, and scanners. Show sync/offline state and prevent silent duplication during retry. This is a touch adaptation of Dense UI, not a switch to a peer “comfortable mode.”

