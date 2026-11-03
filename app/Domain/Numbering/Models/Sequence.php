<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Models;

use App\Domain\Numbering\Services\SequenceAllocator;
use App\Domain\Numbering\Database\Factories\SequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Persisted counter backing {@see SequenceAllocator}.
 *
 * Each row is a named sequence whose `next_value` is the next integer the
 * allocator will hand out.
 *
 * @property string $name
 * @property int $next_value
 */
final class Sequence extends Model
{
    /** @use HasFactory<SequenceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'next_value',
    ];

    protected static function newFactory(): SequenceFactory
    {
        return SequenceFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }
}
