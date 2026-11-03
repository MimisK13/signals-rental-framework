<section class="w-full">
    @include('products::partials.product-header', ['product' => $product, 'subpage' => 'Stock'])
    @include('products::partials.product-tabs', ['product' => $product, 'activeTab' => 'stock'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Stock\Models\StockLevel::class"
            :searchable="['item_name', 'asset_number', 'serial_number', 'barcode']"
            :with="['store']"
            :scopes="$scopes"
            default-sort="item_name"
            empty-message="No stock levels found for this product."
            actions-view="stock::partials.row-actions"
            toolbar-view="products::partials.stock-toolbar"
            entity-type="stock-levels"
            :key="'product-stock-' . $product->id"
        />
    </div>
</section>
