<?php

declare(strict_types=1);

use App\Domain\Availability\AvailabilityServiceProvider;
use App\Domain\Availability\Models\AvailabilityDailySummary;
use App\Domain\Availability\Models\AvailabilityEvent;
use App\Domain\Availability\Models\AvailabilitySnapshot;
use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Shortages\Models\ShortageAcknowledgement;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Models\ShortageResolutionItem;
use App\Domain\Availability\Shortages\Models\ShortageWaitlistMonitor;

it('registers the Availability bounded context and its routes', function (): void {
    expect(app()->providerIsLoaded(AvailabilityServiceProvider::class))->toBeTrue()
        ->and(route('availability.index'))->toContain('/availability')
        ->and(route('api.v1.availability.index'))->toContain('/api/v1/availability')
        ->and(route('api.v1.opportunities.shortages.index', 1))->toContain('/api/v1/opportunities/1/shortages');
});

it('resolves Availability and Shortages domain factories', function (): void {
    $models = [
        Demand::factory()->create(),
        AvailabilitySnapshot::factory()->create(),
        AvailabilityEvent::factory()->create(),
        AvailabilityDailySummary::factory()->create(),
        ShortageResolution::factory()->create(),
        ShortageResolutionItem::factory()->create(),
        ShortageAcknowledgement::factory()->create(),
        ShortageWaitlistMonitor::factory()->create(),
    ];

    foreach ($models as $model) {
        $this->assertModelExists($model);
    }
});

it('does not expose legacy Availability or Shortages class identities', function (): void {
    expect(class_exists('App\\Models\\Demand'))->toBeFalse()
        ->and(class_exists('App\\Models\\AvailabilitySnapshot'))->toBeFalse()
        ->and(class_exists('App\\Models\\ShortageResolution'))->toBeFalse()
        ->and(class_exists('App\\Services\\AvailabilityService'))->toBeFalse()
        ->and(class_exists('App\\Services\\Shortages\\ShortageDetector'))->toBeFalse()
        ->and(enum_exists('App\\Enums\\DemandPhase'))->toBeFalse()
        ->and(enum_exists('App\\Enums\\ShortageResolutionStatus'))->toBeFalse();
});

arch('Availability presentation does not depend on Volt', function (): void {
    expect('App\Domain\Availability\Presentation')
        ->not->toUse('Livewire\Volt');
});
