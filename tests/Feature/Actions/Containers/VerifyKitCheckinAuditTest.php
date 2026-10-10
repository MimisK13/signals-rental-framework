<?php

use App\Actions\Containers\VerifyKitCheckin;
use App\Events\AuditableEvent;
use App\Models\ActionLog;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function verificationContainer(Store $store, array $attributes = []): Container
{
    return Container::factory()->create($attributes + [
        'store_id' => $store->id,
        'checkin_verification_required_at' => CarbonImmutable::parse('2026-10-08 09:00:00', 'UTC'),
    ]);
}

it('persists the explicit verification actor, occurrence time, container and context', function (): void {
    $occurredAt = CarbonImmutable::parse('2026-10-08 10:15:00', 'UTC');
    $container = verificationContainer($this->store);
    $opportunity = Opportunity::factory()->create(['store_id' => $this->store->id]);
    $container->forceFill(['opportunity_id' => $opportunity->id])->saveQuietly();

    $this->travelTo($occurredAt);

    (new VerifyKitCheckin)($container->fresh());

    $log = ActionLog::query()
        ->where('action', 'container.checkin_verified')
        ->where('auditable_type', Container::class)
        ->where('auditable_id', $container->id)
        ->sole();

    expect($log->user_id)->toBe($this->actor->id)
        ->and($log->created_at->equalTo($occurredAt))->toBeTrue()
        ->and($log->metadata)->toMatchArray([
            'container_uuid' => $container->uuid,
            'opportunity_id' => $opportunity->id,
            'store_id' => $this->store->id,
        ]);
});

it('does not replace the historical actor when another user reads the history', function (): void {
    $container = verificationContainer($this->store);
    $occurredAt = CarbonImmutable::parse('2026-10-08 11:00:00', 'UTC');

    $this->travelTo($occurredAt);
    (new VerifyKitCheckin)($container->fresh());

    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $log = ActionLog::query()
        ->where('action', 'container.checkin_verified')
        ->where('auditable_id', $container->id)
        ->sole();

    expect($log->user_id)->toBe($this->actor->id)
        ->and($log->created_at->equalTo($occurredAt))->toBeTrue();
});

it('retains verification history across multiple container cycles', function (): void {
    $container = verificationContainer($this->store);
    $firstAt = CarbonImmutable::parse('2026-10-08 12:00:00', 'UTC');

    $this->travelTo($firstAt);
    (new VerifyKitCheckin)($container->fresh());

    $secondActor = User::factory()->owner()->create();
    $container->forceFill([
        'checkin_verification_required_at' => CarbonImmutable::parse('2026-10-09 09:00:00', 'UTC'),
    ])->saveQuietly();

    $secondAt = CarbonImmutable::parse('2026-10-09 09:30:00', 'UTC');
    $this->actingAs($secondActor)->travelTo($secondAt);
    (new VerifyKitCheckin)($container->fresh());

    $logs = ActionLog::query()
        ->where('action', 'container.checkin_verified')
        ->where('auditable_id', $container->id)
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->user_id)->toBe($this->actor->id)
        ->and($logs[0]->created_at->equalTo($firstAt))->toBeTrue()
        ->and($logs[1]->user_id)->toBe($secondActor->id)
        ->and($logs[1]->created_at->equalTo($secondAt))->toBeTrue();
});

it('does not create verification history when authorization or validation fails', function (): void {
    $container = verificationContainer($this->store);
    $logCount = ActionLog::query()->where('action', 'container.checkin_verified')->count();

    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized);

    expect(fn () => (new VerifyKitCheckin)($container->fresh()))
        ->toThrow(AuthorizationException::class);

    expect($container->fresh()->checkin_verification_required_at)->not->toBeNull()
        ->and(ActionLog::query()->where('action', 'container.checkin_verified')->count())->toBe($logCount);

    $this->actingAs($this->actor);
    $container->forceFill(['checkin_verification_required_at' => null])->saveQuietly();

    expect(fn () => (new VerifyKitCheckin)($container->fresh()))
        ->toThrow(ValidationException::class);

    expect(ActionLog::query()->where('action', 'container.checkin_verified')->count())->toBe($logCount);
});

it('rejects sequential duplicate verification without a second audit fact', function (): void {
    $container = verificationContainer($this->store);

    (new VerifyKitCheckin)($container->fresh());

    expect(fn () => (new VerifyKitCheckin)($container->fresh()))
        ->toThrow(ValidationException::class);

    expect(ActionLog::query()
        ->where('action', 'container.checkin_verified')
        ->where('auditable_id', $container->id)
        ->count())->toBe(1);
});

it('rolls back the verification state when its audit persistence fails', function (): void {
    $container = verificationContainer($this->store);

    ActionLog::creating(function (ActionLog $log): void {
        if ($log->action === 'container.checkin_verified') {
            throw new RuntimeException('audit persistence failed');
        }
    });

    expect(fn () => (new VerifyKitCheckin)($container->fresh()))
        ->toThrow(RuntimeException::class, 'audit persistence failed');

    expect($container->fresh()->checkin_verification_required_at)->not->toBeNull()
        ->and(ActionLog::query()
            ->where('action', 'container.checkin_verified')
            ->where('auditable_id', $container->id)
            ->count())->toBe(0);
});

it('keeps explicit event attribution stable when the live user differs', function (): void {
    $container = verificationContainer($this->store);
    $historicalActor = $this->actor;
    $currentUser = User::factory()->create();
    $occurredAt = CarbonImmutable::parse('2026-10-08 13:00:00', 'UTC');

    $this->actingAs($currentUser);
    event(new AuditableEvent(
        $container,
        'container.checkin_verified',
        metadata: ['container_uuid' => $container->uuid],
        userId: $historicalActor->id,
        occurredAt: $occurredAt,
    ));

    $log = ActionLog::query()
        ->where('action', 'container.checkin_verified')
        ->where('auditable_id', $container->id)
        ->sole();

    expect($log->user_id)->toBe($historicalActor->id)
        ->and($log->created_at->toDateTimeString())->toBe($occurredAt->toDateTimeString());
});
