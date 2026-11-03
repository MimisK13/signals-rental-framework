<?php

use App\Domain\Activities\Actions\CompleteActivity;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('marks an activity as completed', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create(['completed' => false]);

    $result = (new CompleteActivity)($activity);

    expect($result->completed)->toBeTrue();
    expect($result->status_id)->toBe(ActivityStatus::Completed->value);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'activity.completed';
    });
});

it('records an action_logs row when an activity is completed', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create(['completed' => false]);

    (new CompleteActivity)($activity);

    assertActionLogged('activity.completed', (new Activity)->getMorphClass(), $activity->id, $user->id);
});

it('can complete an already-completed activity without error', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->completed()->create();

    $result = (new CompleteActivity)($activity);

    expect($result->completed)->toBeTrue();
    expect($result->status_id)->toBe(ActivityStatus::Completed->value);

    Event::assertDispatched(AuditableEvent::class);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    (new CompleteActivity)($activity);
})->throws(AuthorizationException::class);
