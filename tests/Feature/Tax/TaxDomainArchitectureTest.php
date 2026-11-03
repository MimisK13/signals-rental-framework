<?php

declare(strict_types=1);

arch('Tax domain presentation does not depend on Volt', function (): void {
    expect('App\Domain\Tax')->not->toUse('Livewire\Volt');
});

it('does not expose legacy tax class mappings', function (): void {
    expect(class_exists('App\Models\TaxRate'))->toBeFalse()
        ->and(class_exists('App\Models\TaxRule'))->toBeFalse()
        ->and(class_exists('App\Models\TaxZone'))->toBeFalse()
        ->and(class_exists('App\Models\TaxCategory'))->toBeFalse()
        ->and(class_exists('App\Domain\Products\Models\ProductTaxClass'))->toBeFalse()
        ->and(class_exists('App\Models\OrganisationTaxClass'))->toBeFalse();
});
