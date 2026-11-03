<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Database\Factories;

use App\Domain\Administration\Models\User;
use App\Domain\Invoices\Enums\PaymentMethod;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoicePayment;
use App\Domain\Numbering\Services\SequenceAllocator;
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
