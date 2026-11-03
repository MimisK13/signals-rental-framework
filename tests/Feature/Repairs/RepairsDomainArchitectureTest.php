<?php

declare(strict_types=1);

use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\Assets\Repairs\Presentation\Livewire\Form;
use App\Domain\Assets\Repairs\Presentation\Livewire\Index;
use App\Domain\Assets\Repairs\Presentation\Livewire\Show;

it('owns canonical repair model, routes, and native presentation', function (): void {
    expect(class_exists(Repair::class))->toBeTrue()
        ->and(class_exists(Index::class))->toBeTrue()
        ->and(class_exists(Form::class))->toBeTrue()
        ->and(class_exists(Show::class))->toBeTrue()
        ->and(class_exists('App\\Models\\Repair'))->toBeFalse()
        ->and(class_exists('App\\Domain\\Repairs\\Models\\Repair'))->toBeFalse();

    expect(route('repairs.index'))->toEndWith('/repairs');
});

arch('Repairs presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\Assets\\Repairs\\Presentation')->not->toUse('Livewire\\Volt');
});
