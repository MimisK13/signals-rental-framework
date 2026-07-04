<?php

namespace App\Services\Dashboard;

use App\Enums\AssetAssignmentStatus;
use App\Enums\InvoiceType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Aggregate queries backing the dashboard KPI stat cards.
 *
 * All counts respect the authenticated user's store access. Invoice queries
 * scoped metrics apply an explicit store filter here.
 */
class DashboardKpiService
{
    /**
     * @return array{
     *     active_opportunities: array{total: int, quotations: int, orders: int},
     *     revenue: array{
     *         current_minor: int,
     *         previous_minor: int,
     *         change_percent: float|null,
     *         currency_code: string,
     *     },
     *     on_hire: array{units: int, active_jobs: int},
     *     overdue: array{
     *         total_outstanding_minor: int,
     *         invoice_count: int,
     *         average_days_overdue: float|null,
     *         currency_code: string,
     *     },
     * }
     */
    public function metrics(?User $user = null): array
    {
        $user ??= auth()->user();
        $now = Carbon::now('UTC');
        $monthStart = $now->copy()->startOfMonth();
        $previousMonthStart = $monthStart->copy()->subMonth();
        $previousMonthEnd = $monthStart->copy()->subSecond();

        $quotations = $this->activeOpportunitiesQuery($user)
            ->ofState(OpportunityState::Quotation)
            ->count();

        $orders = $this->activeOpportunitiesQuery($user)
            ->ofState(OpportunityState::Order)
            ->count();

        $currentRevenue = $this->revenueForPeriod($user, $monthStart, $now);
        $previousRevenue = $this->revenueForPeriod($user, $previousMonthStart, $previousMonthEnd);

        $currencyCode = settings('company.base_currency') ?: 'GBP';

        return [
            'active_opportunities' => [
                'total' => $quotations + $orders,
                'quotations' => $quotations,
                'orders' => $orders,
            ],
            'revenue' => [
                'current_minor' => $currentRevenue,
                'previous_minor' => $previousRevenue,
                'change_percent' => $this->percentChange($previousRevenue, $currentRevenue),
                'currency_code' => $currencyCode,
            ],
            'on_hire' => $this->onHireMetrics($user),
            'overdue' => $this->overdueMetrics($user, $now, $currencyCode),
        ];
    }

    /**
     * @return Builder<Opportunity>
     */
    private function activeOpportunitiesQuery(?User $user): Builder
    {
        $query = Opportunity::query()
            ->whereIn('state', [
                OpportunityState::Quotation->value,
                OpportunityState::Order->value,
            ])
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query->where('state', OpportunityState::Quotation->value)
                            ->whereNotIn('status', [
                                OpportunityStatus::QuotationLost->statusValue(),
                                OpportunityStatus::QuotationDead->statusValue(),
                            ]);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('state', OpportunityState::Order->value)
                            ->whereNotIn('status', [
                                OpportunityStatus::OrderComplete->statusValue(),
                                OpportunityStatus::OrderCancelled->statusValue(),
                            ]);
                    });
            });

        return $this->applyStoreScope($query, 'store_id', $user);
    }

    private function revenueForPeriod(?User $user, Carbon $startsAt, Carbon $endsAt): int
    {
        $invoiceTotal = (int) Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$startsAt, $endsAt])
            ->where('type', '!=', InvoiceType::Proforma->value)
            ->where('status', '!=', 'void')
            ->when(
                ($storeIds = $this->storeIdsForUser($user)) !== null,
                function (Builder $query) use ($storeIds): void {
                    if ($storeIds === []) {
                        $query->whereRaw('0 = 1');

                        return;
                    }

                    $query->whereIn('store_id', $storeIds);
                },
            )
            ->sum('total');

        $creditTotal = (int) CreditNote::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$startsAt, $endsAt])
            ->where('status', '!=', 'void')
            ->when(
                ($storeIds = $this->storeIdsForUser($user)) !== null,
                function (Builder $query) use ($storeIds): void {
                    if ($storeIds === []) {
                        $query->whereRaw('0 = 1');

                        return;
                    }

                    $query->whereHas(
                        'invoice',
                        fn (Builder $invoiceQuery) => $invoiceQuery->whereIn('store_id', $storeIds),
                    );
                },
            )
            ->selectRaw('COALESCE(SUM(ABS(total)), 0) as credit_total')
            ->value('credit_total');

        return max(0, $invoiceTotal - $creditTotal);
    }

    /**
     * @return array{units: int, active_jobs: int}
     */
    private function onHireMetrics(?User $user): array
    {
        $outStatuses = [
            AssetAssignmentStatus::Dispatched->value,
            AssetAssignmentStatus::OnHire->value,
        ];

        $serialisedUnits = OpportunityItemAsset::query()
            ->whereIn('status', $outStatuses)
            ->whereHas('item.opportunity', function (Builder $query) use ($user): void {
                $this->applyStoreScope($query, 'store_id', $user);
            })
            ->count();

        $bulkUnits = (int) OpportunityItem::query()
            ->whereHas('opportunity', function (Builder $query) use ($user): void {
                $this->applyStoreScope($query, 'store_id', $user);
            })
            ->whereRaw('dispatched_quantity > returned_quantity')
            ->selectRaw('COALESCE(SUM(CAST(dispatched_quantity AS NUMERIC) - CAST(returned_quantity AS NUMERIC)), 0) as on_hire_units')
            ->value('on_hire_units');

        $activeJobs = $this->applyStoreScope(Opportunity::query(), 'store_id', $user)
            ->where(function (Builder $query) use ($outStatuses): void {
                $query
                    ->whereHas('allItems.assets', function (Builder $query) use ($outStatuses): void {
                        $query->whereIn('status', $outStatuses);
                    })
                    ->orWhereHas('allItems', function (Builder $query): void {
                        $query->whereRaw('dispatched_quantity > returned_quantity');
                    });
            })
            ->count();

        return [
            'units' => $serialisedUnits + $bulkUnits,
            'active_jobs' => $activeJobs,
        ];
    }

    /**
     * @return array{
     *     total_outstanding_minor: int,
     *     invoice_count: int,
     *     average_days_overdue: float|null,
     *     currency_code: string,
     * }
     */
    private function overdueMetrics(?User $user, Carbon $now, string $currencyCode): array
    {
        $overdueQuery = Invoice::query()
            ->overdue($now)
            ->when(
                ($storeIds = $this->storeIdsForUser($user)) !== null,
                function (Builder $query) use ($storeIds): void {
                    if ($storeIds === []) {
                        $query->whereRaw('0 = 1');

                        return;
                    }

                    $query->whereIn('store_id', $storeIds);
                },
            );

        $invoiceCount = (int) (clone $overdueQuery)->count();
        $totalOutstanding = (int) (clone $overdueQuery)->sum('amount_outstanding');

        $averageDays = null;

        if ($invoiceCount > 0) {
            $averageDays = (float) (clone $overdueQuery)
                ->pluck('due_at')
                ->filter()
                ->avg(fn ($dueAt) => Carbon::parse($dueAt)->diffInDays($now));
        }

        return [
            'total_outstanding_minor' => $totalOutstanding,
            'invoice_count' => $invoiceCount,
            'average_days_overdue' => $averageDays,
            'currency_code' => $currencyCode,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyStoreScope(Builder $query, string $column, ?User $user): Builder
    {
        $storeIds = $this->storeIdsForUser($user);

        if ($storeIds === null) {
            return $query;
        }

        if ($storeIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn($column, $storeIds);
    }

    /**
     * @return list<int>|null Null means unrestricted access.
     */
    private function storeIdsForUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return $user->accessibleStoreIds();
    }

    private function percentChange(int $previous, int $current): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
