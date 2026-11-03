<?php

declare(strict_types=1);

namespace App\Domain\Activities\Actions;

use App\Domain\Activities\Data\ActivityData;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CompleteActivity
{
    public function __invoke(Activity $activity): ActivityData
    {
        // Authorization also checked in API controller — retained as defense-in-depth
        Gate::authorize('activities.complete');

        return DB::transaction(function () use ($activity): ActivityData {
            $activity->update([
                'status_id' => ActivityStatus::Completed,
                'completed' => true,
            ]);

            event(new AuditableEvent($activity, 'activity.completed'));

            $activity->load(['owner', 'participants.member']);

            app(WebhookService::class)->dispatch('activity.completed', [
                'activity' => ActivityData::fromModel($activity)->toArray(),
            ]);

            return ActivityData::fromModel($activity);
        });
    }
}
