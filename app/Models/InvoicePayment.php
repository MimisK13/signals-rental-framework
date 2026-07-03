<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Traits\FormatsMoney;
use Database\Factories\InvoicePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payment row on an invoice projection.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $amount
 * @property string $payment_method
 * @property string|null $reference
 * @property Carbon $paid_at
 * @property string|null $notes
 * @property int|null $created_by
 */
class InvoicePayment extends Model
{
    /** @use HasFactory<InvoicePaymentFactory> */
    use FormatsMoney, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method',
        'reference',
        'paid_at',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        if ($this->relationLoaded('invoice') && $this->invoice !== null) {
            return $this->invoice->currency_code;
        }

        return $this->baseFormattingCurrency();
    }

    public function paymentMethodEnum(): PaymentMethod
    {
        return PaymentMethod::from($this->payment_method);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
