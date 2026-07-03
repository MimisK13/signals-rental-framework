<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\SurchargeData;
use App\Data\Pricing\UpdateSurchargeData;
use App\Events\AuditableEvent;
use App\Models\Surcharge;
use Illuminate\Support\Facades\Gate;

class UpdateSurcharge
{
    public function __invoke(Surcharge $surcharge, UpdateSurchargeData $data): SurchargeData
    {
        Gate::authorize('update', $surcharge);

        $payload = array_filter([
            'name' => $data->name,
            'percent_value' => $data->percent_value,
            'fixed_value_minor' => $data->fixed_value_minor,
            'criteria' => $data->criteria,
            'is_default' => $data->is_default,
            'product_tax_class_id' => $data->product_tax_class_id,
            'valid_from' => $data->valid_from,
            'valid_to' => $data->valid_to,
            'sort_order' => $data->sort_order,
        ], fn (mixed $value): bool => $value !== null);

        if (array_key_exists('percent_value', $payload) && $payload['percent_value'] !== null) {
            $payload['fixed_value_minor'] = null;
        }

        if (array_key_exists('fixed_value_minor', $payload) && $payload['fixed_value_minor'] !== null) {
            $payload['percent_value'] = null;
        }

        $surcharge->update($payload);
        $surcharge->load('productTaxClass');

        event(new AuditableEvent($surcharge, 'surcharge.updated'));

        return SurchargeData::fromModel($surcharge);
    }
}
