<?php

declare(strict_types=1);

namespace App\Domain\Products\Presentation\Livewire;

use App\Domain\API\Services\RansackFilter;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Presentation\Concerns\LoadsCustomFieldValues;
use App\Domain\CustomFields\Presentation\Concerns\ReKeysCustomFieldErrors;
use App\Domain\Products\Actions\CreateProduct;
use App\Domain\Products\Actions\UpdateProduct;
use App\Domain\Products\Data\CreateProductData;
use App\Domain\Products\Data\UpdateProductData;
use App\Domain\Products\Enums\ProductType;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\ReferenceData\Models\CostGroup;
use App\Domain\ReferenceData\Models\Country;
use App\Domain\ReferenceData\Models\RevenueGroup;
use App\Domain\Stock\Enums\AllowedStockType;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxCategory;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Form extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;

    public ?int $productId = null;

    public string $name = '';

    public string $productType = 'rental';

    public bool $isActive = true;

    public string $description = '';

    public ?int $productGroupId = null;

    public string $productGroupSearch = '';

    public int $allowedStockType = 1;

    public int $stockMethod = 1;

    public ?string $weight = null;

    public ?string $barcode = null;

    public ?string $sku = null;

    public string $replacementCharge = '0.00';

    public string $bufferPercent = '0.0';

    public int $postRentUnavailability = 0;

    public bool $accessoryOnly = false;

    public bool $system = false;

    public bool $discountable = true;

    public ?int $taxClassId = null;

    public ?int $purchaseTaxClassId = null;

    public ?int $saleTaxCategoryId = null;

    public ?int $purchaseTaxCategoryId = null;

    public ?int $rentalRevenueGroupId = null;

    public ?int $saleRevenueGroupId = null;

    public ?int $subRentalCostGroupId = null;

    public string $subRentalPrice = '0.00';

    public ?int $purchaseCostGroupId = null;

    public string $purchasePrice = '0.00';

    public ?int $countryOfOriginId = null;

    public string $countrySearch = '';

    /** @var list<string> */
    public array $tags = [];

    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    public function mount(?Product $product = null): void
    {
        // Pre-populate product type from ?type= query param
        $type = request()->query('type');
        if (is_string($type) && in_array($type, array_column(ProductType::cases(), 'value'))) {
            $this->productType = $type;
        }

        // Force bulk stock method for Sale products
        if ($this->productType === 'sale') {
            $this->stockMethod = StockMethod::Bulk->value;
        }

        if ($product?->exists) {
            $allowedStockType = $product->getAttribute('allowed_stock_type');
            $this->productId = $product->id;
            $this->name = $product->name;
            $this->productType = $product->product_type->value;
            $this->isActive = $product->is_active;
            $this->description = $product->description ?? '';
            $this->productGroupId = $product->product_group_id;
            $this->allowedStockType = $allowedStockType instanceof AllowedStockType
                ? $allowedStockType->value
                : (int) ($allowedStockType ?? 1);
            $this->stockMethod = $product->stock_method->value;
            $this->weight = $product->weight;
            $this->barcode = $product->barcode;
            $this->sku = $product->sku;
            $this->replacementCharge = $this->majorAmount($product->replacement_charge ?? 0);
            $this->bufferPercent = $product->buffer_percent ?? '0.0';
            $this->postRentUnavailability = $product->post_rent_unavailability ?? 0;
            $this->accessoryOnly = $product->accessory_only ?? false;
            $this->system = $product->system ?? false;
            $this->discountable = $product->discountable ?? true;
            $this->taxClassId = $product->tax_class_id;
            $this->purchaseTaxClassId = $product->purchase_tax_class_id;
            $this->saleTaxCategoryId = $product->sale_tax_category_id;
            $this->purchaseTaxCategoryId = $product->purchase_tax_category_id;
            $this->rentalRevenueGroupId = $product->rental_revenue_group_id;
            $this->saleRevenueGroupId = $product->sale_revenue_group_id;
            $this->subRentalCostGroupId = $product->sub_rental_cost_group_id;
            $this->subRentalPrice = $this->majorAmount($product->sub_rental_price ?? 0);
            $this->purchaseCostGroupId = $product->purchase_cost_group_id;
            $this->purchasePrice = $this->majorAmount($product->purchase_price ?? 0);
            $this->countryOfOriginId = $product->country_of_origin_id;
            $this->tags = $product->tag_list ?? [];

            // Force bulk stock method for Sale products
            if ($this->productType === 'sale') {
                $this->stockMethod = StockMethod::Bulk->value;
            }

            // Load existing custom field values
            $this->customFieldValues = $this->loadCustomFieldValuesFrom($product);
        }
    }

    /**
     * When product type changes, enforce Sale = Bulk stock method.
     */
    public function updatedProductType(string $value): void
    {
        if ($value === 'sale') {
            $this->stockMethod = StockMethod::Bulk->value;
        }
    }

    /**
     * Prevent Serialised stock method for Sale products.
     */
    public function updatedStockMethod(): void
    {
        if ($this->productType === 'sale') {
            $this->stockMethod = StockMethod::Bulk->value;
        }
    }

    /**
     * Select a product group from the autocomplete results.
     */
    public function selectProductGroup(int $id): void
    {
        $this->productGroupId = $id;
        $this->productGroupSearch = '';
    }

    /**
     * Select a country from the autocomplete results.
     */
    public function selectCountry(int $id): void
    {
        $this->countryOfOriginId = $id;
        $this->countrySearch = '';
    }

    /**
     * @return Collection<int, ProductGroup>
     */
    #[Computed]
    public function productGroupResults(): Collection
    {
        if (strlen($this->productGroupSearch) < 1) {
            return new Collection;
        }

        return ProductGroup::query()
            ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->productGroupSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Country>
     */
    #[Computed]
    public function countryResults(): Collection
    {
        if (strlen($this->countrySearch) < 1) {
            return new Collection;
        }

        return Country::query()
            ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->countrySearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    /**
     * Get the selected product group name for display.
     */
    #[Computed]
    public function selectedProductGroupName(): ?string
    {
        if (! $this->productGroupId) {
            return null;
        }

        return ProductGroup::query()->where('id', $this->productGroupId)->value('name');
    }

    /**
     * Get the selected country name for display.
     */
    #[Computed]
    public function selectedCountryName(): ?string
    {
        if (! $this->countryOfOriginId) {
            return null;
        }

        return Country::query()->where('id', $this->countryOfOriginId)->value('name');
    }

    private function moneyCurrency(): string
    {
        $currency = settings('company.base_currency');

        return is_string($currency) && $currency !== '' ? $currency : 'GBP';
    }

    private function majorAmount(int $minorAmount): string
    {
        return (string) Money::ofMinor($minorAmount, $this->moneyCurrency())->getAmount();
    }

    private function minorAmount(string|int|float|null $majorAmount): int
    {
        $value = $majorAmount === null || $majorAmount === '' ? '0' : (string) $majorAmount;

        return Money::of($value, $this->moneyCurrency(), roundingMode: RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();
    }

    public function save(): void
    {
        $nameUniqueRule = Rule::unique('products', 'name');

        if ($this->productId) {
            $nameUniqueRule->ignore($this->productId);
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'replacementCharge' => ['required', 'numeric', 'min:0'],
            'subRentalPrice' => ['required', 'numeric', 'min:0'],
            'purchasePrice' => ['required', 'numeric', 'min:0'],
        ]);

        $payload = [
            'name' => $this->name,
            'product_type' => $this->productType,
            'is_active' => $this->isActive,
            'description' => $this->description ?: null,
            'product_group_id' => $this->productGroupId,
            'allowed_stock_type' => $this->allowedStockType,
            'stock_method' => $this->stockMethod,
            'weight' => $this->weight,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'replacement_charge' => $this->minorAmount($this->replacementCharge),
            'buffer_percent' => $this->bufferPercent,
            'post_rent_unavailability' => $this->postRentUnavailability,
            'accessory_only' => $this->accessoryOnly,
            'system' => $this->system,
            'discountable' => $this->discountable,
            'tax_class_id' => $this->taxClassId,
            'purchase_tax_class_id' => $this->purchaseTaxClassId,
            'sale_tax_category_id' => $this->saleTaxCategoryId,
            'purchase_tax_category_id' => $this->purchaseTaxCategoryId,
            'rental_revenue_group_id' => $this->rentalRevenueGroupId,
            'sale_revenue_group_id' => $this->saleRevenueGroupId,
            'sub_rental_cost_group_id' => $this->subRentalCostGroupId,
            'sub_rental_price' => $this->minorAmount($this->subRentalPrice),
            'purchase_cost_group_id' => $this->purchaseCostGroupId,
            'purchase_price' => $this->minorAmount($this->purchasePrice),
            'country_of_origin_id' => $this->countryOfOriginId,
            'tag_list' => $this->tags !== [] ? $this->tags : null,
            'custom_fields' => $this->customFieldValues,
        ];

        if (! Gate::check('costs.view')) {
            unset($payload['purchase_price']);
        }

        try {
            if ($this->productId) {
                $product = Product::findOrFail($this->productId);
                $result = (new UpdateProduct)($product, UpdateProductData::from($payload));
            } else {
                $result = (new CreateProduct)(CreateProductData::from($payload));
            }
        } catch (ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->referenceData()['customFields']->pluck('name')->all());
        }

        $this->redirect(route('products.show', $result->id), navigate: true);
    }

    /**
     * @return array{
     *     taxClasses: Collection<int, ProductTaxClass>,
     *     taxCategories: Collection<int, TaxCategory>,
     *     revenueGroups: Collection<int, RevenueGroup>,
     *     costGroups: Collection<int, CostGroup>,
     *     customFields: Collection<int, CustomField>
     * }
     */
    #[Computed]
    public function referenceData(): array
    {
        return [
            'taxClasses' => ProductTaxClass::query()->orderBy('name')->get(),
            'taxCategories' => TaxCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'revenueGroups' => RevenueGroup::query()->orderBy('name')->get(['id', 'name']),
            'costGroups' => CostGroup::query()->orderBy('name')->get(['id', 'name']),
            'customFields' => CustomField::query()
                ->forModule('Product')
                ->active()
                ->with(['group', 'listName.values'])
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    /**
     * Distinct existing product tags, used as autocomplete suggestions.
     *
     * @return list<string>
     */
    public function tagSuggestions(): array
    {
        return Product::query()
            ->whereNotNull('tag_list')
            ->pluck('tag_list')
            ->flatMap(fn ($tags): array => is_array($tags) ? $tags : [])
            ->filter(fn ($tag): bool => is_string($tag) && $tag !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $isEditing = $this->productId !== null;
        $ref = $this->referenceData();
        $product = $isEditing ? Product::query()->findOrFail($this->productId) : null;

        return [
            'isEditing' => $isEditing,
            'tagSuggestions' => $this->tagSuggestions(),
            'product' => $product === null ? null : $product->loadCount(['stockLevels', 'accessories', 'attachments']),
            'productTypes' => ProductType::cases(),
            'stockMethods' => StockMethod::cases(),
            'taxClasses' => $ref['taxClasses'],
            'taxCategories' => $ref['taxCategories'],
            'revenueGroups' => $ref['revenueGroups'],
            'costGroups' => $ref['costGroups'],
            'customFields' => $ref['customFields'],
            'groupedCustomFields' => $ref['customFields']->groupBy(
                fn (CustomField $field): string => $field->group === null ? 'General' : $field->group->name,
            ),
        ];
    }

    public function render(): View
    {
        return view('products::form');
    }
}
