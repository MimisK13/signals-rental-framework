<?php

namespace App\Actions\Tax;

use App\Data\Tax\CreateZoneTaxRuleData;
use App\Data\Tax\TaxRuleData;
use App\Events\AuditableEvent;
use App\Models\TaxRule;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class CreateZoneTaxRule
{
    public function __invoke(CreateZoneTaxRuleData $data): TaxRuleData
    {
        Gate::authorize('tax-classes.manage');

        $taxRule = TaxRule::create([
            'name' => $data->name,
            'description' => $data->description,
            'organisation_tax_class_id' => null,
            'product_tax_class_id' => null,
            'tax_rate_id' => null,
            'seller_zone_id' => $data->seller_zone_id,
            'buyer_zone_id' => $data->buyer_zone_id,
            'buyer_type' => $data->buyer_type,
            'has_tax_id' => $data->has_tax_id,
            'tax_category_id' => $data->tax_category_id,
            'result_type' => $data->result_type,
            'legal_text' => $data->legal_text,
            'priority' => $data->priority,
            'is_active' => $data->is_active,
        ]);

        event(new AuditableEvent($taxRule, 'tax_rule.created'));

        app(WebhookService::class)->dispatch('tax_rule.created', [
            'tax_rule' => TaxRuleData::fromModel($taxRule)->toArray(),
        ]);

        return TaxRuleData::fromModel($taxRule);
    }
}
