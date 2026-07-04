<?php

it('supervises every application queue in horizon defaults', function () {
    $expectedQueues = [
        'default',
        'availability',
        'webhooks',
        'notifications',
        'communications',
        'exports',
        'imports',
        'mail',
    ];

    $supervisedQueues = collect(config('horizon.defaults'))
        ->pluck('queue')
        ->flatten()
        ->unique()
        ->values()
        ->all();

    expect($supervisedQueues)->toEqualCanonicalizing($expectedQueues);
});

it('defines production and local overrides for every horizon supervisor', function () {
    $supervisorNames = array_keys(config('horizon.defaults'));

    foreach (['production', 'local'] as $environment) {
        expect(array_keys(config("horizon.environments.{$environment}")))
            ->toEqualCanonicalizing($supervisorNames);
    }
});
