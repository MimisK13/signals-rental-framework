<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Domain\Tax\Database\Factories\TaxZoneMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tax_zone_id
 * @property string $country_code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TaxZoneMember extends Model
{
    /** @use HasFactory<TaxZoneMemberFactory> */
    use HasFactory;

    protected static function newFactory(): TaxZoneMemberFactory
    {
        return TaxZoneMemberFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'tax_zone_id',
        'country_code',
    ];

    /**
     * @return BelongsTo<TaxZone, $this>
     */
    public function taxZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class);
    }
}
