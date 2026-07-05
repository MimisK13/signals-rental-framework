<?php

namespace App\Data\Invoices;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class RecordPaymentData extends Data
{
    public function __construct(
        #[Min(1)]
        public int $amount,
        public PaymentMethod $payment_method = PaymentMethod::BankTransfer,
        #[Max(255)]
        public ?string $reference = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
