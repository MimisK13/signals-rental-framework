<?php

declare(strict_types=1);

namespace App\Domain\Rates\Actions;

use App\Domain\Rates\Data\ProductRateData;
use App\Domain\Rates\Data\UpdateProductRateData;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Rules\EnsureProductRateCurrencyIsValid;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateProductRate
{
    public function __invoke(ProductRate $rate, UpdateProductRateData $data): ProductRateData
    {
        Gate::authorize('rates.edit');

        $attributes = $data->toArray();
        if (array_key_exists('currency', $attributes)) {
            $attributes['currency'] = (new EnsureProductRateCurrencyIsValid)((string) $attributes['currency']);
        }

        return DB::transaction(function () use ($attributes, $rate): ProductRateData {
            // toArray() omits Optional (untouched) fields but keeps explicit nulls,
            // so a client can clear a nullable field by sending it as null.
            $rate->update($attributes);

            $rate->refresh();
            $rate->load('priceCategory');

            event(new AuditableEvent($rate, 'product_rate.updated'));

            app(WebhookService::class)->dispatch('product_rate.updated', [
                'product_rate' => ProductRateData::fromModel($rate)->toArray(),
            ]);

            return ProductRateData::fromModel($rate);
        });
    }
}
