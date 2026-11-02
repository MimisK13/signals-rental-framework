<?php

declare(strict_types=1);

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetInspection;
use App\Domain\Assets\Models\InspectionPolicy;
use App\Domain\Assets\Presentation\Livewire\Investment;
use App\Domain\Assets\Presentation\Livewire\Show;
use App\Domain\Assets\Presentation\Livewire\Utilization;

it('owns canonical asset models, routes, and native presentation', function (): void {
    expect(class_exists(Asset::class))->toBeTrue()
        ->and(class_exists(AssetInspection::class))->toBeTrue()
        ->and(class_exists(InspectionPolicy::class))->toBeTrue()
        ->and(class_exists(Show::class))->toBeTrue()
        ->and(class_exists(Utilization::class))->toBeTrue()
        ->and(class_exists(Investment::class))->toBeTrue()
        ->and(class_exists('App\\Models\\Asset'))->toBeFalse();

    expect(route('assets.show', 1))->toEndWith('/assets/1');
});

arch('Assets presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\Assets\\Presentation')->not->toUse('Livewire\\Volt');
});
