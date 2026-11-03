<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Quotations\Database\Factories\QuotationVersionFactory;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Enums\QuotationVersionType;
use App\Models\Traits\FormatsMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $state_id
 * @property int $quotation_id
 * @property int $version_number
 * @property QuotationVersionType $version_type
 * @property string|null $label
 * @property bool $is_active
 * @property QuotationVersionStatus $status
 * @property int $charge_excluding_tax_total
 * @property int $tax_total
 * @property int $charge_including_tax_total
 * @property int $charge_total
 * @property string $currency_code
 * @property string $exchange_rate
 * @property bool $prices_include_tax
 */
final class QuotationVersion extends Model
{
    /** @use HasFactory<QuotationVersionFactory> */
    use FormatsMoney, HasFactory;

    public $incrementing = false;

    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'quotation_id',
        'version_number',
        'parent_version_id',
        'superseded_by_version_id',
        'version_type',
        'label',
        'is_active',
        'status',
        'charge_excluding_tax_total',
        'tax_total',
        'charge_including_tax_total',
        'charge_total',
        'currency_code',
        'exchange_rate',
        'prices_include_tax',
        'notes',
        'created_by',
        'sent_at',
        'sent_to',
        'sent_via',
        'accepted_at',
        'accepted_by',
        'declined_at',
        'decline_reason',
    ];

    protected static function newFactory(): QuotationVersionFactory
    {
        return QuotationVersionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'version_type' => QuotationVersionType::class,
            'is_active' => 'boolean',
            'status' => QuotationVersionStatus::class,
            'charge_excluding_tax_total' => 'integer',
            'tax_total' => 'integer',
            'charge_including_tax_total' => 'integer',
            'charge_total' => 'integer',
            'exchange_rate' => 'decimal:8',
            'prices_include_tax' => 'boolean',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function supersededByVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_version_id');
    }

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'version_id')->orderBy('path');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Member, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sent_to');
    }

    /** @return BelongsTo<Member, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'accepted_by');
    }
}
