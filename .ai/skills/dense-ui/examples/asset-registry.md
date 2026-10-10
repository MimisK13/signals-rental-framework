# Dense Asset Registry

Operator: rental operations staff comparing serialized assets and opening the correct record.

    Assets                                      [New asset]
    Search | Status | Store | Category | More filters | 2 active | Columns | Views

    [ ] Asset number     Product       Status       Store          Location          Serial        ⋯
    [ ] M32-001          Midas M32     Available    Thessaloniki   Audio / Consoles   123456        ⋯
    [ ] DL32-014          Midas DL32   Repair       Thessaloniki   Audio / I/O        987654        ⋯

PRIMARY columns are checkbox, Asset number, Product, Status. SECONDARY are Store, Location, Serial. TERTIARY are acquisition date, supplier, and financial metadata. Use 36px rows, 36px header, 8px cell padding, 14px table text, left-aligned identity/text, and right-aligned numeric/date values when comparison benefits. Status is text/icon plus restrained semantic treatment.

The asset number is the primary link. Frequent operation may have one visible action; View/Edit/Duplicate/Delete belong in trailing overflow. Selection reveals a selection count and bulk actions; destructive changes require confirmation.

Keyboard: Tab reaches search, filters, column/view controls, table; Enter opens the identity; Space selects; Escape closes menus; focus returns to the invoking row action. Mobile keeps Asset number/Product/Status and exposes Store/Location/Serial in labelled stacked detail or a record page. It never hides asset identity to preserve tertiary columns.

