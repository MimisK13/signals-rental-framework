<?php

namespace Database\Factories;

use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\Surcharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityItemSurcharge>
 */
class OpportunityItemSurchargeFactory extends Factory
{
    protected $model = OpportunityItemSurcharge::class;

    public function definition(): array
    {
        return [
            'opportunity_item_id' => OpportunityItem::factory(),
            'surcharge_id' => Surcharge::factory(),
            'name' => null,
            'surcharge_type' => null,
            'percent_value' => null,
            'fixed_value_minor' => null,
            'amount' => 500,
            'product_tax_class_id' => null,
            'is_manual_override' => false,
            'is_disabled' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OpportunityItemSurcharge $row): void {
            if ($row->name !== null || $row->surcharge_id === null) {
                return;
            }

            $surcharge = Surcharge::query()->find($row->surcharge_id);

            if ($surcharge instanceof Surcharge) {
                $row->fill($surcharge->snapshotAttributes());
            }
        });
    }

    public function manual(int $amount): static
    {
        return $this->state(fn (): array => [
            'amount' => $amount,
            'is_manual_override' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'is_disabled' => true,
        ]);
    }
}
