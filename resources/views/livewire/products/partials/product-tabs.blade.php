@php
    // Centralise tab-count loading so every product tab shows correct, consistent
    // counts regardless of which relations the individual page component loaded.
    $product->loadCount(['stockLevels', 'accessories', 'attachments', 'rates', 'supplierCosts', 'activities']);
@endphp
<x-signals.module-tabs
    :tabs="[
        ['name' => 'overview', 'label' => 'Overview', 'route' => route('products.show', $product)],
        ['name' => 'stock', 'label' => 'Stock', 'route' => route('products.stock', $product), 'count' => $product->stock_levels_count ?? 0],
        ...(auth()->user()?->can('availability.view') ? [['name' => 'availability', 'label' => 'Availability', 'route' => route('products.availability', $product)]] : []),
        ...($product->stock_method === \App\Enums\StockMethod::Serialised && auth()->user()?->can('stock.view') ? [['name' => 'utilization', 'label' => 'Utilization', 'route' => route('products.utilization', $product)]] : []),
        ...($product->stock_method === \App\Enums\StockMethod::Serialised && auth()->user()?->can('stock.view') ? [['name' => 'financials', 'label' => 'Financials', 'route' => route('products.financials', $product)]] : []),
        ...($product->stock_method === \App\Enums\StockMethod::Serialised && auth()->user()?->can('stock.view') ? [['name' => 'investment', 'label' => 'Investment', 'route' => route('products.investment', $product)]] : []),
        ...($product->stock_method === \App\Enums\StockMethod::Serialised && auth()->user()?->can('stock.view') ? [['name' => 'inspections', 'label' => 'Inspections', 'route' => route('products.inspections', $product)]] : []),
        ['name' => 'accessories', 'label' => 'Accessories', 'route' => route('products.accessories', $product), 'count' => $product->accessories_count ?? 0],
        ...(auth()->user()?->can('rates.view') ? [['name' => 'rates', 'label' => 'Rates', 'route' => route('products.rates', $product), 'count' => $product->rates_count ?? 0]] : []),
        ...(auth()->user()?->can('costs.view') ? [['name' => 'supplier-costs', 'label' => 'Supplier Costs', 'route' => route('products.supplier-costs', $product), 'count' => $product->supplier_costs_count ?? 0]] : []),
        ...($product->stock_method === \App\Enums\StockMethod::Serialised ? [['name' => 'container-config', 'label' => 'Container Config', 'route' => route('products.container-config', $product)]] : []),
        ['name' => 'activities', 'label' => 'Activities', 'route' => route('products.activities', $product), 'count' => $product->activities_count ?? 0],
        ['name' => 'custom-fields', 'label' => 'Custom Fields', 'route' => route('products.custom-fields', $product)],
        ['name' => 'files', 'label' => 'Files', 'route' => route('products.files', $product), 'count' => $product->attachments_count ?? 0],
    ]"
    :active="$activeTab"
/>
