<?php

declare(strict_types=1);

arch('Communications domain presentation does not depend on Volt', function (): void {
    expect('App\Domain\Communications')->not->toUse('Livewire\Volt');
});

it('does not expose legacy communication class mappings', function (): void {
    expect(class_exists('App\Models\CommunicationLog'))->toBeFalse()
        ->and(class_exists('App\Models\CommunicationTemplate'))->toBeFalse()
        ->and(class_exists('App\Domain\Members\Models\EmailTemplate'))->toBeFalse()
        ->and(class_exists('App\Models\ChannelProvider'))->toBeFalse();
});
