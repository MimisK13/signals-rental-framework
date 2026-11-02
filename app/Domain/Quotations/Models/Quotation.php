<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Models;

use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Database\Factories\QuotationFactory;
use App\Domain\Quotations\Enums\QuotationDealPriceMode;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Models\Member;
use App\Models\Store;
use App\Models\Traits\FormatsMoney;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $state_id
 * @property int|null $project_id
 * @property int $member_id
 * @property int|null $venue_id
 * @property int $store_id
 * @property int|null $owned_by
 * @property string $number
 * @property string $subject
 * @property string|null $reference
 * @property string|null $description
 * @property QuotationStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $valid_until
 * @property string $currency_code
 * @property string $exchange_rate
 * @property bool $prices_include_tax
 * @property int|null $active_version_id
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_version_id
 * @property int $version_count
 * @property int $charge_excluding_tax_total
 * @property int $tax_total
 * @property int $charge_including_tax_total
 * @property int $charge_total
 * @property int|null $deal_total
 * @property QuotationDealPriceMode $deal_price_mode
 * @property int|null $deal_price_original_total
 * @property int $editor_revision
 */
final class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use FormatsMoney, HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'project_id',
        'member_id',
        'venue_id',
        'store_id',
        'owned_by',
        'number',
        'subject',
        'reference',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'valid_until',
        'currency_code',
        'exchange_rate',
        'prices_include_tax',
        'active_version_id',
        'accepted_version_id',
        'version_count',
        'charge_excluding_tax_total',
        'tax_total',
        'charge_including_tax_total',
        'charge_total',
        'deal_total',
        'deal_price_mode',
        'deal_price_original_total',
        'editor_revision',
        'accepted_at',
        'accepted_by',
        'created_by',
    ];

    protected static function newFactory(): QuotationFactory
    {
        return QuotationFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'valid_until' => 'datetime',
            'exchange_rate' => 'decimal:8',
            'prices_include_tax' => 'boolean',
            'version_count' => 'integer',
            'charge_excluding_tax_total' => 'integer',
            'tax_total' => 'integer',
            'charge_including_tax_total' => 'integer',
            'charge_total' => 'integer',
            'deal_total' => 'integer',
            'deal_price_mode' => QuotationDealPriceMode::class,
            'deal_price_original_total' => 'integer',
            'editor_revision' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'venue_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'owned_by');
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Member, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'accepted_by');
    }

    /** @return HasMany<QuotationVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(QuotationVersion::class)->orderBy('version_number');
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'active_version_id');
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function acceptedVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'accepted_version_id');
    }

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }
}
