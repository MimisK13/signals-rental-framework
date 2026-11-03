<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Assets\Database\Factories\AssetInspectionFactory;
use App\Domain\Assets\Enums\InspectionResult;
use App\Domain\Assets\Enums\InspectionStatus;
use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Members\Models\Member;
use App\Domain\Stock\Enums\StockMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $inspection_policy_id
 * @property int $asset_id
 * @property Carbon|null $due_at
 * @property Carbon|null $scheduled_start_at
 * @property Carbon|null $scheduled_end_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property InspectionStatus $status
 * @property InspectionResult|null $result
 * @property int|null $inspector_user_id
 * @property int|null $inspector_member_id
 * @property string|null $notes
 */
class AssetInspection extends Model
{
    /** @use HasFactory<AssetInspectionFactory> */
    use HasAttachments, HasFactory;

    protected static function newFactory(): AssetInspectionFactory
    {
        return AssetInspectionFactory::new();
    }

    protected $fillable = [
        'inspection_policy_id', 'asset_id', 'due_at', 'scheduled_start_at', 'scheduled_end_at',
        'started_at', 'completed_at', 'status', 'result', 'inspector_user_id', 'inspector_member_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => InspectionStatus::class,
            'result' => InspectionResult::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AssetInspection $inspection): void {
            $policy = $inspection->relationLoaded('inspectionPolicy')
                ? $inspection->getRelation('inspectionPolicy')
                : InspectionPolicy::query()->find($inspection->inspection_policy_id);
            $asset = $inspection->relationLoaded('asset')
                ? $inspection->getRelation('asset')
                : Asset::query()->find($inspection->asset_id);

            if (! $inspection->exists && ($policy === null || ! $policy->is_active)) {
                throw ValidationException::withMessages(['inspection_policy_id' => 'Inactive policies cannot create new inspections.']);
            }

            if ($policy === null || $asset === null || (int) $policy->product_id !== (int) $asset->product_id) {
                throw ValidationException::withMessages(['asset_id' => 'The inspection Asset must belong to the policy Product.']);
            }

            if ($asset->product?->stock_method !== StockMethod::Serialised) {
                throw ValidationException::withMessages(['asset_id' => 'Inspections apply only to serialized Assets.']);
            }

            if ($inspection->scheduled_start_at !== null && $inspection->scheduled_end_at !== null
                && Carbon::parse($inspection->scheduled_end_at)->lessThanOrEqualTo(Carbon::parse($inspection->scheduled_start_at))) {
                throw ValidationException::withMessages(['scheduled_end_at' => 'The scheduled inspection end must be after its start.']);
            }

            $status = $inspection->statusEnum();

            if ($status !== InspectionStatus::Completed && $inspection->result !== null) {
                throw ValidationException::withMessages(['result' => 'Only completed inspections may have a result.']);
            }

            if ($status === InspectionStatus::Completed && ($inspection->completed_at === null || $inspection->resultEnum() === null)) {
                throw ValidationException::withMessages(['result' => 'Completed inspections require a completion time and result.']);
            }

            if ($inspection->inspector_user_id !== null && $inspection->inspector_member_id !== null) {
                throw ValidationException::withMessages(['inspector_user_id' => 'Choose an internal user or an external member, not both.']);
            }

            if ($inspection->exists && $inspection->getRawOriginal('status') === InspectionStatus::Completed->value
                && $inspection->isDirty(['asset_id', 'inspection_policy_id', 'due_at', 'result', 'completed_at'])) {
                throw ValidationException::withMessages(['status' => 'Completed inspections are historical and cannot be edited.']);
            }
        });
    }

    /** @return BelongsTo<InspectionPolicy, $this> */
    public function inspectionPolicy(): BelongsTo
    {
        return $this->belongsTo(InspectionPolicy::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inspectorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function inspectorMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'inspector_member_id');
    }

    /** @return HasMany<Repair, $this> */
    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    public function statusEnum(): InspectionStatus
    {
        return $this->getAttribute('status') instanceof InspectionStatus
            ? $this->getAttribute('status')
            : InspectionStatus::from((string) $this->getAttribute('status'));
    }

    public function resultEnum(): ?InspectionResult
    {
        $result = $this->getAttribute('result');

        return $result === null
            ? null
            : ($result instanceof InspectionResult ? $result : InspectionResult::from((string) $result));
    }
}
