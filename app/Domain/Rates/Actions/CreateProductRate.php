<?php

declare(strict_types=1);

namespace App\Domain\Rates\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Rates\Data\CreateProductRateData;
use App\Domain\Rates\Data\ProductRateData;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Rules\EnsureProductRateCurrencyIsValid;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateProductRate
{
    public function __invoke(CreateProductRateData $data): ProductRateData
    {
        Gate::authorize('rates.create');
        $currency = (new EnsureProductRateCurrencyIsValid)($data->currency);

        return DB::transaction(function () use ($currency, $data): ProductRateData {
            $rate = ProductRate::create([
                'product_id' => $data->product_id,
                'rate_definition_id' => $data->rate_definition_id,
                'store_id' => $data->store_id,
                'price_category_id' => $data->price_category_id,
                'transaction_type' => $data->transaction_type,
                'price' => $data->price,
                'currency' => $currency,
                'valid_from' => $data->valid_from,
                'valid_to' => $data->valid_to,
                'priority' => $data->priority,
            ]);

            $rate->load('priceCategory');

            event(new AuditableEvent($rate, 'product_rate.created'));

            app(WebhookService::class)->dispatch('product_rate.created', [
                'product_rate' => ProductRateData::fromModel($rate)->toArray(),
            ]);

            return ProductRateData::fromModel($rate);
        });
    }
}
