# Dense Purchase Order

Operator: procurement staff creating and approving a financially significant order.

    Procurement / Purchase orders
    PO-2026-0041                         [Draft] [Save] [⋯]
    Supplier: Audio Supply BV · Delivery: Thessaloniki · Required: 2026-11-12

    Supplier and delivery
    Supplier          Audio Supply BV
    Currency          EUR                 Payment terms       Net 30
    Delivery location Thessaloniki        Requested date     2026-11-12

    Line items
    Product             Description          Qty     Unit price       Tax       Line total
    XLR-5M              XLR cable 5m          50          €8.50       24%          €425.00
    CAT6-10M            CAT6 cable 10m        20         €12.00       24%          €240.00

    Subtotal €665.00      Tax €159.60      Total €824.60
    [Save draft] [Submit for approval] [Cancel]

Use a dedicated page, not a modal. Supplier/delivery fields use a grouped form; line items use a table with right-aligned quantities/prices/totals and visible validation. Keep Draft quiet; approval/rejection/conflict states carry stronger semantic treatment. Destructive cancellation is separated and confirmed.

