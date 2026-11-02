<?php

declare(strict_types=1);

use App\Domain\CustomViews\Models\CustomView;
use App\Domain\CustomViews\Models\UserViewPreference;
use App\Domain\CustomViews\Services\ViewResolver;
use App\Models\User;

it('keeps the historical migration class name available without a morph map', function (): void {
    $view = CustomView::factory()->create();

    expect(App\Models\CustomView::query()->findOrFail($view->id))->toBeInstanceOf(CustomView::class);
    expect($view->getMorphClass())->toBe(CustomView::class);
});

it('builds user preferences with the canonical factory and relationship', function (): void {
    $preference = UserViewPreference::factory()->create();

    expect($preference->customView)->toBeInstanceOf(CustomView::class);
    expect($preference->user)->toBeInstanceOf(User::class);
    expect(app(ViewResolver::class))->toBeInstanceOf(ViewResolver::class);
});

arch('CustomViews does not depend on Volt', function (): void {
    expect('App\Domain\CustomViews')->not->toUse('Livewire\Volt');
});
