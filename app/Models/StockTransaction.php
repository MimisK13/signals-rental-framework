<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\TransactionType;
use App\Services\SchemaBuilder;
use Database\Factories\StockTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class StockTransaction extends Model implements HasSchema
{
    /** @use HasFactory<StockTransactionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'stock_level_id',
        'store_id',
        'source_id',
        'source_type',
        'transaction_type',
        'transaction_at',
        'quantity',
        'description',
        'manual',
        'actor_user_id',
        'quantity_before',
        'quantity_after',
        'reversal_of_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'transaction_at' => 'datetime',
            'quantity' => 'decimal:2',
            'quantity_before' => 'decimal:2',
            'quantity_after' => 'decimal:2',
            'manual' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('stock_level_id')->label('Stock Level')
            ->relation('stockLevel', 'belongsTo', StockLevel::class, 'item_name')
            ->required()->filterable()->sortable();
        $builder->relation('store_id')->label('Store')
            ->relation('store', 'belongsTo', Store::class, 'name')
            ->required()->filterable()->sortable();
        $builder->relation('actor_user_id')->label('Actor')
            ->relation('actor', 'belongsTo', User::class, 'name')
            ->filterable();
        $builder->relation('source_id')->label('Source')
            ->relation('source', 'morphTo', Model::class)
            ->filterable();
        $builder->string('source_type')->label('Source Type')->filterable()->groupable();
        $builder->integer('transaction_type')->label('Transaction Type')->filterable()->sortable()->groupable();
        $builder->datetime('transaction_at')->label('Transaction At')->filterable()->sortable();
        $builder->decimal('quantity')->label('Quantity')->sortable();
        $builder->decimal('quantity_before')->label('Quantity Before');
        $builder->decimal('quantity_after')->label('Quantity After');
        $builder->relation('reversal_of_id')->label('Reversal Of')
            ->relation('reversalOf', 'belongsTo', self::class, 'id')
            ->filterable();
        $builder->text('description')->label('Description')->searchable();
        $builder->boolean('manual')->label('Manual')->filterable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<StockLevel, $this>
     */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Committed stock transactions are immutable.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Committed stock transactions cannot be deleted; reverse them instead.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * @return HasOne<self, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * Exact signed quantity for this transaction's held-stock movement:
     * negative for reductions, positive for additions.
     *
     * This is the single source of truth used by both creation (increment)
     * and deletion (decrement) so the two operations are perfectly symmetrical.
     */
    public function signedQuantity(): string
    {
        /** @var TransactionType $type */
        $type = $this->transaction_type;

        return $type->signedQuantity((string) $this->quantity);
    }

    /**
     * Signed quantity formatted for display: negative for reductions,
     * positive for additions.
     */
    public function getQuantityMoveAttribute(): string
    {
        return number_format((float) $this->signedQuantity(), 1, '.', '');
    }
}
