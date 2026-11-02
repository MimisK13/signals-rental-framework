<?php

declare(strict_types=1);

namespace App\Domain\Rates\Actions;

use App\Domain\Rates\Models\ProductRate;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteProductRate
{
    public function __invoke(ProductRate $rate): void
    {
        Gate::authorize('rates.delete');

        DB::transaction(function () use ($rate): void {
            event(new AuditableEvent($rate, 'product_rate.deleted'));

            app(WebhookService::class)->dispatch('product_rate.deleted', [
                'id' => $rate->id,
            ]);

            $rate->delete();
        });
    }
}
