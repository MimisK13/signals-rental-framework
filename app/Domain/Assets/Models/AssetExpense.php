<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Domain\Assets\Database\Factories\AssetExpenseFactory;
use App\Domain\Assets\Enums\AssetExpenseType;
use App\Domain\Repairs\Models\Repair;
use App\Models\Member;
use App\Models\Traits\FormatsMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property int|null $repair_id
 * @property AssetExpenseType $expense_type
 * @property int $amount
 * @property string $currency_code
 * @property string $exchange_rate
 * @property string $base_currency_code
 * @property int $base_amount
 * @property Carbon $occurred_at
 * @property int|null $supplier_member_id
 * @property string $description
 * @property string|null $reference
 */
class AssetExpense extends Model
{
    /** @use HasFactory<AssetExpenseFactory> */
    use FormatsMoney, HasFactory;

    protected static function newFactory(): AssetExpenseFactory
    {
        return AssetExpenseFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'asset_id',
        'repair_id',
        'expense_type',
        'amount',
        'currency_code',
        'exchange_rate',
        'base_currency_code',
        'base_amount',
        'occurred_at',
        'supplier_member_id',
        'description',
        'reference',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expense_type' => AssetExpenseType::class,
            'amount' => 'integer',
            'base_amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'supplier_member_id');
    }

    /**
     * @return BelongsTo<Repair, $this>
     */
    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    protected function moneyFormattingCurrency(): string
    {
        return $this->base_currency_code ?: $this->baseFormattingCurrency();
    }
}
