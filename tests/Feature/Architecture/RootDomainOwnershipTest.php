<?php

declare(strict_types=1);

it('keeps domain-owned application code out of root namespaces', function (): void {
    foreach ([
        'Console/Commands',
        'Jobs',
        'Services/Assets',
        'Services/Investment',
        'Services/Planning',
        'Services/Products',
        'Livewire/Planner',
        'Mail',
        'Verbs/States',
    ] as $path) {
        expect(file_exists(app_path($path)))->toBeFalse($path);
    }
});
