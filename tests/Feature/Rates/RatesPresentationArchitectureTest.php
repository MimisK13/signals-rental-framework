<?php

declare(strict_types=1);

arch('Rates presentation does not depend on Volt', function (): void {
    expect('App\Domain\Rates\Presentation')->not->toUse('Livewire\Volt');
});
