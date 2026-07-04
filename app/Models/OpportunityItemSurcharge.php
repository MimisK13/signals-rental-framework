<?php

namespace App\Models;

use App\Models\Traits\FormatsMoney;
use Database\Factories\OpportunityItemSurchargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityItemSurcharge extends Model
{
    /** @use HasFactory<OpportunityItemSurchargeFactory> */
    use FormatsMoney, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'opportunity_item_id',
        'surcharge_id',
        'name',
        'surcharge_type',
        'percent_value',
        'fixed_value_minor',
        'amount',
        'product_tax_class_id',
        'is_manual_override',
        'is_disabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_value' => 'decimal:2',
            'fixed_value_minor' => 'integer',
            'amount' => 'integer',
            'is_manual_override' => 'boolean',
            'is_disabled' => 'boolean',
        ];
    }

    public function resolvedName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        if ($this->relationLoaded('surcharge')) {
            return $this->surcharge !== null ? $this->surcharge->name : 'Surcharge';
        }

        $relatedName = $this->surcharge()->value('name');

        return is_string($relatedName) && $relatedName !== '' ? $relatedName : 'Surcharge';
    }

    public function resolvedSurchargeType(): string
    {
        if ($this->surcharge_type !== null && $this->surcharge_type !== '') {
            return $this->surcharge_type;
        }

        if ($this->percent_value !== null) {
            return 'percent';
        }

        if ($this->fixed_value_minor !== null) {
            return 'fixed';
        }

        if ($this->relationLoaded('surcharge') && $this->surcharge !== null) {
            return $this->surcharge->typeValue();
        }

        $surcharge = $this->surcharge()->first(['percent_value', 'fixed_value_minor']);

        if ($surcharge instanceof Surcharge) {
            return $surcharge->typeValue();
        }

        return 'fixed';
    }

    public function resolvedPercentValue(): ?string
    {
        if ($this->percent_value !== null) {
            return (string) $this->percent_value;
        }

        if ($this->relationLoaded('surcharge')) {
            return $this->surcharge?->percent_value !== null
                ? (string) $this->surcharge->percent_value
                : null;
        }

        $value = $this->surcharge()->value('percent_value');

        return $value !== null ? (string) $value : null;
    }

    public function resolvedFixedValueMinor(): ?int
    {
        if ($this->fixed_value_minor !== null) {
            return (int) $this->fixed_value_minor;
        }

        if ($this->relationLoaded('surcharge')) {
            return $this->surcharge?->fixed_value_minor;
        }

        $value = $this->surcharge()->value('fixed_value_minor');

        return $value !== null ? (int) $value : null;
    }

    protected function moneyFormattingCurrency(): string
    {
        $item = $this->relationLoaded('opportunityItem')
            ? $this->opportunityItem
            : $this->opportunityItem()->with('opportunity:id,currency_code')->first();

        $currency = $item?->opportunity?->currency_code;

        return is_string($currency) && $currency !== '' ? $currency : $this->baseFormattingCurrency();
    }

    /**
     * @return BelongsTo<OpportunityItem, $this>
     */
    public function opportunityItem(): BelongsTo
    {
        return $this->belongsTo(OpportunityItem::class);
    }

    /**
     * @return BelongsTo<Surcharge, $this>
     */
    public function surcharge(): BelongsTo
    {
        return $this->belongsTo(Surcharge::class);
    }

    /**
     * @return BelongsTo<ProductTaxClass, $this>
     */
    public function productTaxClass(): BelongsTo
    {
        return $this->belongsTo(ProductTaxClass::class);
    }
}
