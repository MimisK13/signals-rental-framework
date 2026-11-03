<?php

declare(strict_types=1);

namespace App\Domain\Activities\Actions;

use App\Domain\Activities\Models\Activity;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteActivity
{
    public function __invoke(Activity $activity): void
    {
        Gate::authorize('activities.delete');

        DB::transaction(function () use ($activity): void {
            event(new AuditableEvent($activity, 'activity.deleted'));

            // Delete webhooks send only the ID as the resource no longer exists.
            app(WebhookService::class)->dispatch('activity.deleted', [
                'id' => $activity->id,
            ]);

            $activity->delete();
        });
    }
}
