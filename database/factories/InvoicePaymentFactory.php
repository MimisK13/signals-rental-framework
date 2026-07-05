<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => app(SequenceAllocator::class)->next('invoice_payments'),
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->numberBetween(1000, 50000),
            'payment_method' => fake()->randomElement(array_column(PaymentMethod::cases(), 'value')),
            'number' => 'PAY-'.fake()->unique()->numerify('########'),
            'reference' => fake()->optional()->bothify('PAY-########'),
            'paid_at' => now(),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
