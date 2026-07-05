<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\CreateSurchargeData;
use App\Data\Pricing\SurchargeData;
use App\Events\AuditableEvent;
use App\Models\Surcharge;
use Illuminate\Support\Facades\Gate;

class CreateSurcharge
{
    public function __invoke(CreateSurchargeData $data): SurchargeData
    {
        Gate::authorize('create', Surcharge::class);

        $surcharge = Surcharge::query()->create([
            'surcharge_group_id' => $data->surcharge_group_id,
            'name' => $data->name,
            'percent_value' => $data->percent_value,
            'fixed_value_minor' => $data->fixed_value_minor,
            'criteria' => $data->criteria ?? [],
            'is_default' => $data->is_default,
            'product_tax_class_id' => $data->product_tax_class_id,
            'valid_from' => $data->valid_from,
            'valid_to' => $data->valid_to,
            'sort_order' => $data->sort_order,
        ]);

        $surcharge->load('productTaxClass');

        event(new AuditableEvent($surcharge, 'surcharge.created'));

        return SurchargeData::fromModel($surcharge);
    }
}
