# Dense Stock Levels

Operator: planner locating shortages and deciding whether to reorder or transfer.

    Stock levels
    Search | Store | Category | Exception | More filters

    SKU       Description   Store          Location        Held   Available   Reserved   On order   Reorder   Exception
    XLR-5M    XLR 5m        Thessaloniki   Audio/Cables     120          87         33          40        60   —
    CAT6-10M  CAT6 10m      Thessaloniki   Network          48          6         42           0        20   Shortage

Right-align quantities and currency, use tabular numerals, show units where ambiguity exists, and keep exception/status text near the comparable quantities. Primary columns are SKU/description, Available, Reserved, Exception; Store/Location and On order are secondary. Use server-side filtering/pagination; do not render an unbounded warehouse catalog.

