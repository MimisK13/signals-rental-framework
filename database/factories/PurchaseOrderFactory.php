<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => app(SequenceAllocator::class)->next('purchase_orders'),
            'state_id' => snowflake_id(),
            'number' => 'PO-'.fake()->unique()->numerify('####'),
            'po_type' => 'purchase',
            'status' => 'draft',
            'supplier_id' => Member::factory()->organisation(),
            'store_id' => Store::factory(),
            'currency_code' => 'GBP',
            'exchange_rate' => '1',
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'rounding_adjustment' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (): array => [
            'status' => 'received',
            'sent_at' => now()->subDay(),
            'received_at' => now(),
        ]);
    }

    public function partiallyReceived(): static
    {
        return $this->state(fn (): array => [
            'status' => 'partially_received',
            'sent_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
