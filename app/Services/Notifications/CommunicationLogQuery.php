<?php

namespace App\Services\Notifications;

use App\Models\CommunicationLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Store-scoped communication log queries (P29).
 */
class CommunicationLogQuery
{
    /**
     * @return Builder<CommunicationLog>
     */
    public function forUser(?User $user): Builder
    {
        $query = CommunicationLog::query();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $storeIds = $user->accessibleStoreIds();

        if ($storeIds === null) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($storeIds): void {
            $builder
                ->whereHasMorph(
                    'subject',
                    [Opportunity::class],
                    fn (Builder $subjectQuery) => $subjectQuery->whereIn('store_id', $storeIds),
                )
                ->orWhereNot('subject_type', Opportunity::class);
        });
    }

    /**
     * @return Builder<CommunicationLog>
     */
    public function forSubject(string $subjectType, int $subjectId, User $user): Builder
    {
        return $this->forUser($user)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId);
    }
}
