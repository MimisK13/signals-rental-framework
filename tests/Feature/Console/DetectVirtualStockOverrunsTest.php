<?php

use App\Events\AuditableEvent;
use App\Models\User;
use App\Models\VirtualStockIntake;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('fires virtual_stock.overrun once for intakes past available_until', function () {
    Event::fake([AuditableEvent::class]);

    $intake = VirtualStockIntake::factory()->received()->create([
        'available_until' => Carbon::now('UTC')->subDay(),
        'returned_at' => null,
        'cancelled_at' => null,
        'overrun_notified_at' => null,
    ]);

    $this->artisan('virtual-stock:detect-overruns')->assertSuccessful();

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'virtual_stock.overrun');

    expect($intake->fresh()->overrun_notified_at)->not->toBeNull()
        ->and($intake->fresh()->isOverrun())->toBeTrue();

    Event::fake([AuditableEvent::class]);

    $this->artisan('virtual-stock:detect-overruns')->assertSuccessful();

    Event::assertNotDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'virtual_stock.overrun');
});

it('skips returned and cancelled intakes', function () {
    Event::fake([AuditableEvent::class]);

    VirtualStockIntake::factory()->received()->create([
        'available_until' => Carbon::now('UTC')->subDay(),
        'returned_at' => Carbon::now('UTC'),
        'overrun_notified_at' => null,
    ]);

    VirtualStockIntake::factory()->create([
        'status' => 'cancelled',
        'available_until' => Carbon::now('UTC')->subDay(),
        'cancelled_at' => Carbon::now('UTC'),
        'overrun_notified_at' => null,
    ]);

    $this->artisan('virtual-stock:detect-overruns')->assertSuccessful();

    Event::assertNotDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'virtual_stock.overrun');
});
