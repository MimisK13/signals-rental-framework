<?php

declare(strict_types=1);

arch('member contact presentation does not depend on Volt', function (): void {
    expect('App\Livewire\Members')->not->toUse('Livewire\Volt');
});
