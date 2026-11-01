<?php

test('the development worker consumes every application queue', function () {
    $composer = json_decode(
        file_get_contents(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $developmentCommand = collect($composer['scripts']['dev'])
        ->first(fn (string $command): bool => str_contains($command, 'queue:listen'));

    expect($developmentCommand)
        ->toContain('--queue=default,availability,webhooks,notifications,communications,exports,imports,mail');
});

test('horizon is not an application dependency', function () {
    $composer = json_decode(
        file_get_contents(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['require'])->not->toHaveKey('laravel/horizon');
});
