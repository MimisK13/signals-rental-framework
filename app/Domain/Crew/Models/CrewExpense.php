<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Crew\Database\Factories\CrewExpenseFactory;
use App\Domain\Crew\Enums\CrewExpenseCategory;
use App\Domain\Crew\Enums\CrewExpenseStatus;
use App\Domain\Projects\Models\Project;
use App\Models\Store;
use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasStoreScoping;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $store_id
 * @property int $project_id
 * @property int $crew_member_id
 * @property int|null $crew_assignment_id
 * @property CrewExpenseCategory $category
 * @property CrewExpenseStatus $status
 * @property Carbon $incurred_on
 * @property string $description
 * @property int $amount_minor
 * @property string $currency_code
 * @property string $exchange_rate
 * @property int $amount_base_minor
 * @property bool $billable_to_client
 * @property string|null $markup_percent
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property string|null $rejected_reason
 * @property Carbon|null $reimbursed_at
 * @property string|null $finance_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CrewAssignment|null $assignment
 * @property-read User|null $submittedBy
 * @property-read User|null $approvedBy
 */
class CrewExpense extends Model
{
    /** @use HasFactory<CrewExpenseFactory> */
    use FormatsMoney, HasAttachments, HasFactory, HasStoreScoping, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'project_id',
        'crew_member_id',
        'crew_assignment_id',
        'category',
        'status',
        'incurred_on',
        'description',
        'amount_minor',
        'currency_code',
        'exchange_rate',
        'amount_base_minor',
        'billable_to_client',
        'markup_percent',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_reason',
        'reimbursed_at',
        'finance_reference',
    ];

    protected static function booted(): void
    {
        static::updating(static function (self $expense): void {
            if (in_array($expense->getRawOriginal('status'), ['approved', 'reimbursed'], true) && $expense->isDirty([
                'store_id', 'project_id', 'crew_member_id', 'crew_assignment_id', 'category', 'incurred_on',
                'description', 'amount_minor', 'currency_code', 'exchange_rate', 'amount_base_minor',
                'billable_to_client', 'markup_percent', 'approved_at', 'approved_by',
            ])) {
                throw ValidationException::withMessages(['expense' => 'Approved expense amounts and identity are immutable.']);
            }
            if ($expense->getRawOriginal('status') === 'approved' && $expense->isDirty('status') && $expense->status !== CrewExpenseStatus::Reimbursed) {
                throw ValidationException::withMessages(['status' => 'An approved expense can only be reimbursed.']);
            }
            if ($expense->getRawOriginal('status') === 'reimbursed' && $expense->isDirty('status')) {
                throw ValidationException::withMessages(['status' => 'A reimbursed expense cannot be reopened.']);
            }
        });
        static::deleting(static function (self $expense): void {
            if (in_array($expense->status, [CrewExpenseStatus::Approved, CrewExpenseStatus::Reimbursed], true)) {
                throw ValidationException::withMessages(['expense' => 'Approved expenses must be retained.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'category' => CrewExpenseCategory::class,
            'status' => CrewExpenseStatus::class,
            'incurred_on' => 'date',
            'amount_minor' => 'integer',
            'exchange_rate' => 'decimal:6',
            'amount_base_minor' => 'integer',
            'billable_to_client' => 'boolean',
            'markup_percent' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'reimbursed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<CrewMember, $this> */
    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class);
    }

    /** @return BelongsTo<CrewAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CrewAssignment::class, 'crew_assignment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        return $code !== '' ? $code : $this->baseFormattingCurrency();
    }
}
