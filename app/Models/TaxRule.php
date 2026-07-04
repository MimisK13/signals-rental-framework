<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Services\SchemaBuilder;
use Database\Factories\TaxRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property TaxResultType|null $result_type
 */
class TaxRule extends Model implements HasSchema
{
    /** @use HasFactory<TaxRuleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'organisation_tax_class_id',
        'product_tax_class_id',
        'tax_rate_id',
        'seller_zone_id',
        'buyer_zone_id',
        'buyer_type',
        'has_tax_id',
        'tax_category_id',
        'result_type',
        'legal_text',
        'priority',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organisation_tax_class_id' => 'integer',
            'product_tax_class_id' => 'integer',
            'tax_rate_id' => 'integer',
            'seller_zone_id' => 'integer',
            'buyer_zone_id' => 'integer',
            'buyer_type' => TaxBuyerType::class,
            'has_tax_id' => 'boolean',
            'tax_category_id' => 'integer',
            'result_type' => TaxResultType::class,
            'priority' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function isZoneRule(): bool
    {
        return $this->seller_zone_id !== null
            && $this->buyer_zone_id !== null
            && $this->result_type !== null;
    }

    public function isClassMapRule(): bool
    {
        return $this->organisation_tax_class_id !== null
            && $this->product_tax_class_id !== null
            && $this->tax_rate_id !== null;
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->searchable()->filterable();
        $builder->relation('organisation_tax_class_id')->label('Organisation Tax Class')
            ->relation('organisationTaxClass', 'belongsTo', OrganisationTaxClass::class, 'name')
            ->filterable();
        $builder->relation('product_tax_class_id')->label('Product Tax Class')
            ->relation('productTaxClass', 'belongsTo', ProductTaxClass::class, 'name')
            ->filterable();
        $builder->relation('tax_rate_id')->label('Tax Rate')
            ->relation('taxRate', 'belongsTo', TaxRate::class, 'name')
            ->filterable();
        $builder->relation('seller_zone_id')->label('Seller Zone')
            ->relation('sellerZone', 'belongsTo', TaxZone::class, 'name')
            ->filterable();
        $builder->relation('buyer_zone_id')->label('Buyer Zone')
            ->relation('buyerZone', 'belongsTo', TaxZone::class, 'name')
            ->filterable();
        $builder->enum('buyer_type')->label('Buyer Type')->filterable();
        $builder->boolean('has_tax_id')->label('Requires Tax ID')->filterable();
        $builder->relation('tax_category_id')->label('Tax Category')
            ->relation('taxCategory', 'belongsTo', TaxCategory::class, 'name')
            ->filterable();
        $builder->enum('result_type')->label('Result Type')->filterable();
        $builder->integer('priority')->label('Priority')->sortable();
        $builder->date('effective_from')->label('Effective From')->filterable()->sortable();
        $builder->date('effective_to')->label('Effective To')->filterable()->sortable();
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<OrganisationTaxClass, $this>
     */
    public function organisationTaxClass(): BelongsTo
    {
        return $this->belongsTo(OrganisationTaxClass::class);
    }

    /**
     * @return BelongsTo<ProductTaxClass, $this>
     */
    public function productTaxClass(): BelongsTo
    {
        return $this->belongsTo(ProductTaxClass::class);
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * @return BelongsTo<TaxZone, $this>
     */
    public function sellerZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'seller_zone_id');
    }

    /**
     * @return BelongsTo<TaxZone, $this>
     */
    public function buyerZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'buyer_zone_id');
    }

    /**
     * @return BelongsTo<TaxCategory, $this>
     */
    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }
}
