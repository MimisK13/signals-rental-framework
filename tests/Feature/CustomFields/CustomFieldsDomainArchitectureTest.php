<?php

declare(strict_types=1);

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Domain\CustomFields\Presentation\Livewire\Form;
use App\Domain\CustomFields\Presentation\Livewire\Index;

it('owns the canonical custom field models, routes, and native presentation', function (): void {
    expect(class_exists(CustomField::class))->toBeTrue()
        ->and(class_exists(CustomFieldGroup::class))->toBeTrue()
        ->and(class_exists(CustomFieldValue::class))->toBeTrue()
        ->and(class_exists(Index::class))->toBeTrue()
        ->and(class_exists(Form::class))->toBeTrue()
        ->and(class_exists('App\\Models\\CustomField'))->toBeFalse();

    expect(route('admin.settings.custom-fields'))->toEndWith('/admin/settings/custom-fields')
        ->and(route('api.v1.custom_fields.index'))->toEndWith('/api/v1/custom_fields');
});

arch('CustomFields presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\CustomFields\\Presentation')->not->toUse('Livewire\\Volt');
});
