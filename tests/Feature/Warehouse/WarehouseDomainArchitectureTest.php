<?php

declare(strict_types=1);

arch('Warehouse domain presentation does not depend on Volt', function (): void {
    expect('App\Domain\Warehouse')->not->toUse('Livewire\Volt');
});

it('does not expose legacy container class mappings', function (): void {
    expect(class_exists('App\Models\Container'))->toBeFalse()
        ->and(class_exists('App\Models\ContainerItem'))->toBeFalse()
        ->and(class_exists('App\Models\ContainerBulkMembership'))->toBeFalse()
        ->and(class_exists('App\Models\ContainerBulkMembershipEvent'))->toBeFalse();
});
