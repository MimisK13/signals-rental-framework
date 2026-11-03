<?php

declare(strict_types=1);

namespace App\Domain\Rates\Presentation\Livewire;

use App\Domain\Currency\Models\Currency;
use App\Domain\Products\Models\Product;
use App\Domain\Rates\Actions\CreateProductRate;
use App\Domain\Rates\Actions\UpdateProductRate;
use App\Domain\Rates\Data\CreateProductRateData;
use App\Domain\Rates\Data\UpdateProductRateData;
use App\Domain\Rates\Enums\RateTransactionType;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Rules\EnsureProductRateCurrencyIsValid;
use App\Domain\Rates\Services\RateEngine\ProductRateOverlapChecker;
use App\Models\PriceCategory;
use App\Models\Store;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductRateForm extends Component
{
    public Product $product;

    public ?int $rateId = null;

    public ?int $rateDefinitionId = null;

    public string $transactionType = 'rental';

    public string $price = '';

    public string $currency = 'GBP';

    public ?int $storeId = null;

    public ?int $priceCategoryId = null;

    public ?string $validFrom = null;

    public ?string $validTo = null;

    public int $priority = 0;

    public function mount(Product $product, ?ProductRate $rate = null): void
    {
        Gate::authorize('rates.view');

        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments', 'rates']);
        $this->currency = (string) (settings('company.base_currency') ?? 'GBP');
        $this->validFrom = now()->toDateString();

        if ($rate?->exists) {
            abort_unless($rate->product_id === $product->id, 404);

            $this->rateId = $rate->id;
            $this->rateDefinitionId = $rate->rate_definition_id;
            $this->transactionType = RateTransactionType::from(
                (string) $rate->getRawOriginal('transaction_type'),
            )->value;
            $this->price = (string) Money::ofMinor($rate->price, $rate->currency)->getAmount();
            $this->currency = $rate->currency;
            $this->storeId = $rate->store_id;
            $this->priceCategoryId = $rate->price_category_id;
            $this->validFrom = $rate->valid_from?->toDateString();
            $this->validTo = $rate->valid_to?->toDateString();
            $this->priority = $rate->priority;
        }
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.($this->rateId ? ' — Edit Rate' : ' — Assign Rate'));
    }

    public function save(): void
    {
        $this->validate([
            'rateDefinitionId' => ['required', 'integer', 'exists:rate_definitions,id'],
            'transactionType' => ['required', new Enum(RateTransactionType::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'storeId' => ['nullable', 'integer', 'exists:stores,id'],
            'priceCategoryId' => ['nullable', 'integer', 'exists:price_categories,id'],
            'validFrom' => ['nullable', 'date'],
            'validTo' => ['nullable', 'date', 'after_or_equal:validFrom'],
            'priority' => ['integer'],
        ]);

        $this->currency = (new EnsureProductRateCurrencyIsValid)($this->currency);

        $minor = Money::of($this->price, $this->currency, roundingMode: RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();

        $payload = [
            'rate_definition_id' => $this->rateDefinitionId,
            'transaction_type' => $this->transactionType,
            'price' => $minor,
            'currency' => $this->currency,
            'store_id' => $this->storeId,
            'price_category_id' => $this->priceCategoryId,
            'valid_from' => $this->validFrom ?: null,
            'valid_to' => $this->validTo ?: null,
            'priority' => $this->priority,
        ];

        if ($this->rateId !== null) {
            $rate = $this->product->rates()->findOrFail($this->rateId);
            $result = (new UpdateProductRate)($rate, UpdateProductRateData::from($payload));
        } else {
            $result = (new CreateProductRate)(CreateProductRateData::from(['product_id' => $this->product->id, ...$payload]));
        }

        $overlaps = app(ProductRateOverlapChecker::class)->overlapping(
            $this->product->id,
            $this->storeId,
            RateTransactionType::from($this->transactionType),
            $this->priority,
            $this->validFrom ?: null,
            $this->validTo ?: null,
            $result->id,
            $this->priceCategoryId,
        );

        if ($overlaps->isNotEmpty()) {
            session()->flash('rate-overlap-warning', $overlaps->count());
        }

        $this->redirect(route('products.rates', $this->product), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array
    {
        return [
            'isEditing' => $this->rateId !== null,
            'rateDefinitions' => RateDefinition::query()->orderByDesc('is_preset')->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'priceCategories' => PriceCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'transactionTypes' => RateTransactionType::cases(),
            'currencies' => Currency::query()->enabled()->orderBy('code')->get(['code', 'name']),
        ];
    }

    public function render(): View
    {
        return view('rates::product-rate-form', $this->viewData());
    }
}
