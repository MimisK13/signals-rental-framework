<?php

namespace App\Models;

use App\Enums\SupplierBillStatus;
use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasStoreScoping;
use Database\Factories\SupplierBillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierBill extends Model
{
    /** @use HasFactory<SupplierBillFactory> */
    use FormatsMoney, HasFactory, HasStoreScoping, SoftDeletes;

    protected $fillable = [
        'supplier_id', 'store_id', 'purchase_order_id', 'document_number', 'status',
        'issued_at', 'due_at', 'subtotal', 'tax_total', 'total', 'currency_code',
        'exchange_rate', 'notes', 'reference',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierBillStatus::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        return $this->currency_code ?: $this->baseFormattingCurrency();
    }

    public function isAuthoritative(): bool
    {
        return $this->statusEnum()->isAuthoritative();
    }

    public function statusEnum(): SupplierBillStatus
    {
        return SupplierBillStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return BelongsTo<Member, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'supplier_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return HasMany<SupplierBillItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class);
    }
}
