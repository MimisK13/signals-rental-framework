<?php

namespace App\Actions\Tax;

use App\Events\AuditableEvent;
use App\Models\TaxRule;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class DeleteTaxRule
{
    public function __invoke(TaxRule $taxRule): void
    {
        Gate::authorize('tax-classes.manage');

        $id = $taxRule->id;

        event(new AuditableEvent($taxRule, 'tax_rule.deleted', [
            'name' => $taxRule->name,
            'organisation_tax_class_id' => $taxRule->organisation_tax_class_id,
            'product_tax_class_id' => $taxRule->product_tax_class_id,
            'tax_rate_id' => $taxRule->tax_rate_id,
            'seller_zone_id' => $taxRule->seller_zone_id,
            'buyer_zone_id' => $taxRule->buyer_zone_id,
            'buyer_type' => $this->backedEnumValue($taxRule->buyer_type),
            'result_type' => $this->backedEnumValue($taxRule->result_type),
            'priority' => $taxRule->priority,
            'is_active' => $taxRule->is_active,
        ]));

        $taxRule->delete();

        app(WebhookService::class)->dispatch('tax_rule.deleted', [
            'id' => $id,
        ]);
    }

    private function backedEnumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
