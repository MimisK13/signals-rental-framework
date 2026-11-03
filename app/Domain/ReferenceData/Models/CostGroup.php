<?php

declare(strict_types=1);

namespace App\Domain\ReferenceData\Models;

use App\Domain\ReferenceData\Database\Factories\CostGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CostGroup extends Model
{
    /** @use HasFactory<CostGroupFactory> */
    use HasFactory;

    protected static function newFactory(): CostGroupFactory
    {
        return CostGroupFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
