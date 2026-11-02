<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills\Models;

use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\SupplierBills\Database\Factories\SupplierBillFactory;
use App\Domain\SupplierBills\Enums\SupplierBillStatus;
use App\Models\Member;
use App\Models\Store;
use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasStoreScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $store_id
 * @property int|null $purchase_order_id
 * @property string $document_number
 * @property SupplierBillStatus $status
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property string $currency_code
 * @property string $exchange_rate
 * @property string|null $notes
 * @property string|null $reference
 */
class SupplierBill extends Model
{
    /** @use HasFactory<SupplierBillFactory> */
    use FormatsMoney, HasFactory, HasStoreScoping, SoftDeletes;

    protected static function newFactory(): SupplierBillFactory
    {
        return SupplierBillFactory::new();
    }

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
