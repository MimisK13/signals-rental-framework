<?php

declare(strict_types=1);

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Models\StockTransaction;
use App\Domain\Stock\Presentation\Livewire\Form;
use App\Domain\Stock\Presentation\Livewire\Index;
use App\Domain\Stock\Presentation\Livewire\Show;

it('owns canonical stock models, routes, and native presentation', function (): void {
    expect(class_exists(StockLevel::class))->toBeTrue()
        ->and(class_exists(StockTransaction::class))->toBeTrue()
        ->and(class_exists(Index::class))->toBeTrue()
        ->and(class_exists(Form::class))->toBeTrue()
        ->and(class_exists(Show::class))->toBeTrue()
        ->and(class_exists('App\\Models\\StockLevel'))->toBeFalse();

    expect(route('stock-levels.index'))->toEndWith('/stock-levels')
        ->and(route('api.v1.stock_levels.index'))->toEndWith('/api/v1/stock_levels');
});

arch('Stock presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\Stock\\Presentation')->not->toUse('Livewire\\Volt');
});
