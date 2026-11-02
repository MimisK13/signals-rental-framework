<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Contracts\HasSchema;
use App\Domain\Tax\Database\Factories\TaxRuleFactory;
use App\Domain\Tax\Enums\TaxBuyerType;
use App\Domain\Tax\Enums\TaxResultType;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $description
 * @property int|null $organisation_tax_class_id
 * @property int|null $product_tax_class_id
 * @property int|null $tax_rate_id
 * @property int|null $seller_zone_id
 * @property int|null $buyer_zone_id
 * @property TaxBuyerType|null $buyer_type
 * @property bool|null $has_tax_id
 * @property int|null $tax_category_id
 * @property TaxResultType|null $result_type
 * @property string|null $legal_text
 * @property int $priority
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TaxRule extends Model implements HasSchema
{
    /** @use HasFactory<TaxRuleFactory> */
    use HasFactory;

    protected static function newFactory(): TaxRuleFactory
    {
        return TaxRuleFactory::new();
    }

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
