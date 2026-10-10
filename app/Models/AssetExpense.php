<?php

namespace App\Models;

use App\Enums\AssetExpenseType;
use App\Models\Traits\FormatsMoney;
use Database\Factories\AssetExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetExpense extends Model
{
    /** @use HasFactory<AssetExpenseFactory> */
    use FormatsMoney, HasFactory;

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
