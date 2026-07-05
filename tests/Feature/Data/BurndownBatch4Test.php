<?php

/**
 * P4-CL4b burndown batch 4 — Data DTO coverage.
 */

use App\Data\Documents\DocumentTemplateVersionData;
use App\Data\Invoices\InvoiceTaxData;
use App\Data\Opportunities\AssignItemToSectionData;
use App\Data\Opportunities\AssignSectionParentData;
use App\Data\Opportunities\CreateOpportunitySectionData;
use App\Data\Opportunities\OpportunitySectionData;
use App\Data\Opportunities\RenameOpportunitySectionData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Data\Opportunities\ReorderOpportunitySectionsData;
use App\Data\Pricing\UpdateSurchargeGroupData;
use App\Models\DocumentTemplateVersion;
use App\Models\InvoiceTax;
use App\Models\OpportunitySection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('AssignItemToSectionData constructs and exposes rules', function () {
    $dto = new AssignItemToSectionData(section_id: 42);

    expect($dto->section_id)->toBe(42)
        ->and(AssignItemToSectionData::rules())->toHaveKey('section_id');

    $cleared = new AssignItemToSectionData;

    expect($cleared->section_id)->toBeNull();
});

it('RenameOpportunitySectionData constructs and exposes rules', function () {
    $dto = new RenameOpportunitySectionData(name: 'Main stage');

    expect($dto->name)->toBe('Main stage')
        ->and(RenameOpportunitySectionData::rules()['name'])->toContain('required');
});

it('AssignSectionParentData constructs and exposes rules', function () {
    $dto = new AssignSectionParentData(parent_id: 3, sort_order: 2);

    expect($dto->parent_id)->toBe(3)
        ->and($dto->sort_order)->toBe(2)
        ->and(AssignSectionParentData::rules())->toHaveKeys(['parent_id', 'sort_order']);
});

it('ReorderOpportunityItemsData constructs and exposes rules', function () {
    $dto = new ReorderOpportunityItemsData(item_ids: [3, 1, 2]);

    expect($dto->item_ids)->toBe([3, 1, 2])
        ->and(ReorderOpportunityItemsData::rules())->toHaveKey('item_ids');
});

it('ReorderOpportunitySectionsData constructs and exposes rules', function () {
    $dto = new ReorderOpportunitySectionsData(section_ids: [10, 11]);

    expect($dto->section_ids)->toBe([10, 11])
        ->and(ReorderOpportunitySectionsData::rules())->toHaveKey('section_ids');
});

it('CreateOpportunitySectionData constructs and exposes rules', function () {
    $dto = new CreateOpportunitySectionData(name: 'Lighting', sort_order: 1, parent_id: null);

    expect($dto->name)->toBe('Lighting')
        ->and(CreateOpportunitySectionData::rules())->toHaveKeys(['name', 'sort_order', 'parent_id']);
});

it('UpdateSurchargeGroupData constructs and merges nested surcharge rules', function () {
    $dto = new UpdateSurchargeGroupData(
        name: 'Weekend uplift',
        description: 'Peak pricing',
        is_default: false,
        is_active: true,
        sort_order: 3,
        surcharges: [],
    );

    expect($dto->name)->toBe('Weekend uplift')
        ->and(UpdateSurchargeGroupData::rules())->toHaveKeys([
            'name',
            'description',
            'is_default',
            'is_active',
            'sort_order',
        ]);
});

it('OpportunitySectionData fromModel maps section fields', function () {
    $section = OpportunitySection::factory()->create([
        'name' => 'Rigging',
        'sort_order' => 4,
    ]);

    $dto = OpportunitySectionData::fromModel($section);

    expect($dto->id)->toBe($section->id)
        ->and($dto->name)->toBe('Rigging')
        ->and($dto->sort_order)->toBe(4);
});

it('InvoiceTaxData fromModel formats money columns', function () {
    $tax = InvoiceTax::factory()->create([
        'taxable_amount' => 10000,
        'tax_amount' => 2000,
        'rate_percentage' => '20.00',
        'tax_rate_name' => 'VAT',
    ]);

    $dto = InvoiceTaxData::fromModel($tax);

    expect($dto->id)->toBe($tax->id)
        ->and($dto->taxable_amount)->toBeString()
        ->and($dto->tax_amount)->toBeString()
        ->and($dto->tax_rate_name)->toBe('VAT');
});

it('DocumentTemplateVersionData fromModel serialises version metadata', function () {
    $version = DocumentTemplateVersion::factory()->create([
        'body_html' => '<p>Body</p>',
        'change_note' => 'Initial import',
    ]);

    $dto = DocumentTemplateVersionData::fromModel($version);

    expect($dto->id)->toBe($version->id)
        ->and($dto->body_html)->toBe('<p>Body</p>')
        ->and($dto->change_note)->toBe('Initial import')
        ->and($dto->created_at)->not->toBeEmpty();
});
