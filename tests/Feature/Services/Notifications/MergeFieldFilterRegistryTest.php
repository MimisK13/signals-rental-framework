<?php

use App\Services\Notifications\MergeFieldFilterRegistry;
use App\Services\Notifications\MergeFieldRenderContext;
use App\Services\Plugins\PluginRegistrar;

it('registers custom filters via plugin registrar', function () {
    $registry = app(MergeFieldFilterRegistry::class);

    app(PluginRegistrar::class)->registerFilter('reverse', function (mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed {
        if ($value === null) {
            return $value;
        }

        return strrev((string) $value);
    });

    expect($registry->has('reverse'))->toBeTrue();
    expect($registry->apply('reverse', 'abc', null, MergeFieldRenderContext::defaults()))->toBe('cba');
});

it('exposes all core filters', function () {
    $registry = app(MergeFieldFilterRegistry::class);

    foreach (['date', 'time', 'currency', 'number', 'upper', 'lower', 'title', 'default', 'truncate', 'plural'] as $filter) {
        expect($registry->has($filter))->toBeTrue("Missing core filter: {$filter}");
    }

    expect($registry->all())->not->toBeEmpty();
});

it('returns the original value when the filter is unknown', function () {
    $registry = app(MergeFieldFilterRegistry::class);

    expect($registry->apply('missing', 'keep-me', null, MergeFieldRenderContext::defaults()))
        ->toBe('keep-me');
});

it('core filters register through service provider boot', function () {
    expect(app(MergeFieldFilterRegistry::class)->has('currency'))->toBeTrue();
    expect(app(MergeFieldFilterRegistry::class)->apply('currency', 100, null, MergeFieldRenderContext::defaults()))
        ->toBeString();
});
