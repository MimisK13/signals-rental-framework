<?php

namespace App\Models;

use Database\Factories\TaxZoneMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxZoneMember extends Model
{
    /** @use HasFactory<TaxZoneMemberFactory> */
    use HasFactory;

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
