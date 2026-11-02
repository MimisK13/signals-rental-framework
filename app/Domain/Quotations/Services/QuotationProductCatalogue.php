<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Quotations\Models\Quotation;
use App\Domain\Rates\Enums\RateTransactionType;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Services\RateEngine\RateResolver;
use App\Models\Accessory;
use App\Models\Product;
use App\Services\FileService;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final readonly class QuotationProductCatalogue
{
    public function __construct(
        private RateResolver $rateResolver,
        private FileService $fileService,
    ) {}

    /** @return list<array<string, mixed>> */
    public function search(Quotation $quotation, string $query, int $limit = 12): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($query));
        $products = Product::query()
            ->where('is_active', true)
            ->where(function ($builder) use ($escaped): void {
                $builder->whereRaw('lower(name) like ?', ['%'.$escaped.'%'])
                    ->orWhereRaw('lower(coalesce(sku, \'\')) like ?', ['%'.$escaped.'%']);
            })
            ->orderBy('name')
            ->limit(max(1, min(50, $limit)))
            ->get();

        return $this->results($quotation, $products);
    }

    /** @return list<array<string, mixed>> */
    public function index(Quotation $quotation): array
    {
        return $this->results(
            $quotation,
            Product::query()->where('is_active', true)->orderBy('name')->get(),
        );
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function results(Quotation $quotation, Collection $products): array
    {
        $products->load(['components', 'accessories' => function ($query): void {
            $query->orderBy('sort_order')->with('accessoryProduct');
        }]);
        $rates = $this->rateResolver->resolveMany(
            $products,
            RateTransactionType::Rental,
            $quotation->store_id,
            $quotation->starts_at,
        );

        return $products->map(function (Product $product) use ($quotation, $rates): array {
            $rate = $rates[$product->id] ?? null;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'default_rate' => $this->rateAmount($rate, $quotation->currency_code),
                'accessories' => $product->accessories->map(function (Accessory $accessory): ?array {
                    $linked = $accessory->accessoryProduct;

                    return $linked === null ? null : [
                        'id' => $linked->id,
                        'name' => $linked->name,
                        'sku' => $linked->sku,
                        'ratio' => (string) $accessory->quantity,
                        'included' => (bool) $accessory->included,
                        'zero_priced' => (bool) $accessory->zero_priced,
                    ];
                })->filter()->values()->all(),
                'is_combination' => $product->components->isNotEmpty(),
                'availability' => null,
                'image_url' => $this->fileService->signedUrlOrNull($product->icon_thumb_url),
                'initials' => Str::of($product->name)->explode(' ')->take(2)->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->implode(''),
            ];
        })->values()->all();
    }

    private function rateAmount(?ProductRate $rate, string $currency): ?string
    {
        return $rate !== null ? (string) Money::ofMinor($rate->price, $currency)->getAmount() : null;
    }
}
