<?php

declare(strict_types=1);

use App\Domain\Repairs\Models\Repair;
use App\Domain\Repairs\Presentation\Livewire\Form;
use App\Domain\Repairs\Presentation\Livewire\Index;
use App\Domain\Repairs\Presentation\Livewire\Show;

it('owns canonical repair model, routes, and native presentation', function (): void {
    expect(class_exists(Repair::class))->toBeTrue()
        ->and(class_exists(Index::class))->toBeTrue()
        ->and(class_exists(Form::class))->toBeTrue()
        ->and(class_exists(Show::class))->toBeTrue()
        ->and(class_exists('App\\Models\\Repair'))->toBeFalse();

    expect(route('repairs.index'))->toEndWith('/repairs');
});

arch('Repairs presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\Repairs\\Presentation')->not->toUse('Livewire\\Volt');
});
