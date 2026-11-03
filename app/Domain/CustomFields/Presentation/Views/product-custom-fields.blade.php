<section class="w-full">
    @include('products::partials.product-header', ['product' => $product, 'subpage' => 'Custom Fields'])
    @include('products::partials.product-tabs', ['product' => $product, 'activeTab' => 'custom-fields'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="max-w-2xl space-y-8">
            <x-signals.custom-fields-display :grouped="$grouped" :values="$values" emptyMessage="No custom fields have been configured for products." />
        </div>
    </div>
</section>
