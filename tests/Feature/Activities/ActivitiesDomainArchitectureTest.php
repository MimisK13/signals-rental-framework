<?php

declare(strict_types=1);

arch('Activities domain presentation does not depend on Volt', function (): void {
    expect('App\Domain\Activities')->not->toUse('Livewire\Volt');
});
