<?php

declare(strict_types=1);

arch('Crew presentation does not depend on Volt', function (): void {
    expect('App\Domain\Crew\Presentation')->not->toUse('Livewire\Volt');
});
