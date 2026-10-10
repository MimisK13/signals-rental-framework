# Dense Purchase Order Receiving

Workflow class: transactional and repeated operational workflow.

    PO-2026-0041 · Audio Supply BV · Thessaloniki       [Partially received]
    Receive items

    Product        Ordered   Received   This receipt   Difference   State
    XLR-5M               50         20            30          —      Ready
    CAT6-10M             20         20             0          —      Complete
    M32 flightcase        2          0              1         +1      Conflict

    [Confirm receipt] [Save draft] [Review discrepancy]

Use one receiving workspace with a line-item table, not a modal per line. Keep purchase-order context, quantities, discrepancy state, and finalizing action together. Confirm the receipt because it changes authoritative stock/transaction state; preserve the operator’s line edits when validation fails and show partial results when the operation is batch-processed.

