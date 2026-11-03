<?php

use App\Domain\Activities\Presentation\Columns\ActivityColumnRegistry;
use App\Domain\Availability\Shortages\Views\ShortageResolutionColumnRegistry;
use App\Domain\Members\Presentation\Columns\MemberColumnRegistry;
use App\Domain\Products\Presentation\Columns\ProductColumnRegistry;
use App\Domain\Products\Presentation\Columns\ProductGroupColumnRegistry;
use App\Domain\Products\Presentation\Columns\SerialisedComponentColumnRegistry;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityColumnRegistry;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityCostColumnRegistry;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityItemColumnRegistry;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityVersionColumnRegistry;
use App\Domain\Rates\Presentation\Columns\ProductRateColumnRegistry;
use App\Domain\Rates\Presentation\Columns\RateDefinitionColumnRegistry;
use App\Domain\Stock\Presentation\Tables\StockLevelColumnRegistry;
use App\Domain\Warehouse\Containers\Presentation\Columns\ContainerColumnRegistry;
use App\Views\ColumnRegistry;

dataset('column registries', [
    'products' => [ProductColumnRegistry::class, 'products', 'name'],
    'product_groups' => [ProductGroupColumnRegistry::class, 'product_groups', 'name'],
    'product_rates' => [ProductRateColumnRegistry::class, 'product_rates', 'product_id'],
    'members' => [MemberColumnRegistry::class, 'members', 'name'],
    'activities' => [ActivityColumnRegistry::class, 'activities', 'subject'],
    'containers' => [ContainerColumnRegistry::class, 'containers', 'name'],
    'stock_levels' => [StockLevelColumnRegistry::class, 'stock_levels', 'stock_category'],
    'serialised_components' => [SerialisedComponentColumnRegistry::class, 'serialised_components', 'component_product_id'],
    'rate_definitions' => [RateDefinitionColumnRegistry::class, 'rate_definitions', 'name'],
    'shortage_resolutions' => [ShortageResolutionColumnRegistry::class, 'shortage_resolutions', 'status'],
    'opportunities' => [OpportunityColumnRegistry::class, 'opportunities', 'subject'],
    'opportunity_items' => [OpportunityItemColumnRegistry::class, 'opportunity_items', 'name'],
    'opportunity_costs' => [OpportunityCostColumnRegistry::class, 'opportunity_costs', 'description'],
    'opportunity_versions' => [OpportunityVersionColumnRegistry::class, 'opportunity_versions', 'version_number'],
]);

it('exposes a non-empty column registry with expected entity metadata', function (
    string $registryClass,
    string $entityType,
    string $expectedKey,
) {
    /** @var ColumnRegistry $registry */
    $registry = new $registryClass;
    $columns = $registry->allColumns();

    expect($registry->entityType())->toBe($entityType)
        ->and($columns)->not->toBeEmpty()
        ->and($columns)->toHaveKey($expectedKey)
        ->and($registry->defaultColumns())->not->toBeEmpty();

    foreach ($registry->defaultColumns() as $key) {
        expect($columns)->toHaveKey($key);
    }
})->with('column registries');
