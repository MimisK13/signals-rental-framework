<?php

declare(strict_types=1);

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityCost;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityVersion;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Index;
use App\Models\User;

it('uses canonical Opportunity module models and factories', function (): void {
    $models = [
        Opportunity::factory()->create(),
        OpportunityItem::factory()->create(),
        OpportunityCost::factory()->create(),
        OpportunityVersion::factory()->create(),
    ];

    foreach ($models as $model) {
        $this->assertModelExists($model);
    }

    expect($models[0]->getMorphClass())->toBe(Opportunity::class)
        ->and(class_exists('App\\Models\\Opportunity'))->toBeFalse()
        ->and(class_exists('App\\Actions\\Opportunities\\CreateOpportunity'))->toBeFalse()
        ->and(enum_exists('App\\Enums\\OpportunityState'))->toBeFalse();
});

it('serves Opportunity routes from native Projects module Presentation', function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->withoutVite()
        ->get(route('opportunities.index'))
        ->assertOk()
        ->assertSeeLivewire(Index::class);
});

arch('Projects Opportunity module does not depend on Volt', function (): void {
    expect('App\Domain\Projects\Opportunities\Presentation')->not->toUse('Livewire\Volt');
});
