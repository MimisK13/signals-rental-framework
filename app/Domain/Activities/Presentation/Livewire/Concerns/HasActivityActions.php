<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Concerns;

use App\Domain\Activities\Actions\CompleteActivity;
use App\Domain\Activities\Actions\DeleteActivity;
use App\Domain\Activities\Models\Activity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

trait HasActivityActions
{
    public function completeActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new CompleteActivity)($activity);
            $this->dispatch('activity-completed');
        } catch (ModelNotFoundException) {
            $this->dispatch('toast', type: 'success', message: 'Activity was already removed.');
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', message: 'You do not have permission to complete this activity.');
        } catch (\Throwable $e) {
            Log::error('Failed to complete activity', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast', type: 'error', message: 'Something went wrong. Please try again.');
        }
    }

    public function deleteActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new DeleteActivity)($activity);
            $this->dispatch('activity-deleted');
        } catch (ModelNotFoundException) {
            $this->dispatch('toast', type: 'success', message: 'Activity was already removed.');
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', message: 'You do not have permission to delete this activity.');
        } catch (\Throwable $e) {
            Log::error('Failed to delete activity', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast', type: 'error', message: 'Something went wrong. Please try again.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function activityColumns(): array
    {
        return [
            ['key' => 'type_id', 'label' => 'Type', 'sortable' => true, 'view' => 'activities::partials.column-type'],
            ['key' => 'subject', 'label' => 'Subject', 'sortable' => true],
            ['key' => 'owner', 'label' => 'Owner', 'view' => 'activities::partials.column-owner'],
            ['key' => 'starts_at', 'label' => 'Starts', 'sortable' => true],
            ['key' => 'priority', 'label' => 'Priority', 'sortable' => true, 'view' => 'activities::partials.column-priority'],
            ['key' => 'status_id', 'label' => 'Status', 'sortable' => true, 'view' => 'activities::partials.column-status'],
            ['key' => 'actions', 'type' => 'actions'],
        ];
    }
}
