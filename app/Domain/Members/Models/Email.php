<?php

declare(strict_types=1);

namespace App\Domain\Members\Models;

use App\Contracts\HasSchema;
use App\Domain\Lists\Models\ListValue;
use App\Domain\Members\Database\Factories\EmailFactory;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $emailable_type
 * @property int $emailable_id
 * @property string $address
 * @property int|null $type_id
 * @property bool $is_primary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Email extends Model implements HasSchema
{
    /** @use HasFactory<EmailFactory> */
    use HasFactory;

    protected static function newFactory(): EmailFactory
    {
        return EmailFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'emailable_type',
        'emailable_id',
        'address',
        'type_id',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('address')->label('Email Address')->required()->searchable()->filterable()->sortable();
        $builder->relation('type_id')->label('Type')
            ->relation('type', 'belongsTo', ListValue::class, 'name')
            ->filterable();
        $builder->boolean('is_primary')->label('Primary')->filterable()->sortable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ListValue::class, 'type_id');
    }
}
