<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentDataResolver;
use App\Services\FieldBuilder;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Return-list documents render against an opportunity with check-in oriented fields.
 *
 * Core merge data is delegated to {@see OpportunityDataResolver}; this resolver
 * adds return-specific item columns for condition and damage notes.
 */
class ReturnListDataResolver implements DocumentDataResolver
{
    public function __construct(
        private readonly OpportunityDataResolver $opportunityResolver,
    ) {}

    public function type(): string
    {
        return 'return_list';
    }

    public function label(): string
    {
        return 'Return List';
    }

    public function description(): string
    {
        return 'Expected returns with quantity and space for condition notes.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $this->opportunityResolver->registerFields($builder);

        $this->documentField($builder->string('return_item.condition_notes'))->label('Condition Notes')->group('Return Items');
        $this->documentField($builder->boolean('return_item.is_damaged'))->label('Damaged')->group('Return Items');
        $this->documentField($builder->collection('return_items'))->label('Return Items (loop)')->group('Return Items');
    }

    private function documentField(FieldBuilder $field): FieldBuilder
    {
        return $field
            ->source('document')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
    }

    public function loadSource(int $sourceId): Model
    {
        return $this->opportunityResolver->loadSource($sourceId);
    }

    public function resolve(Model $source): array
    {
        $data = $this->opportunityResolver->resolve($source);
        $data['return_items'] = $this->returnItems($data['items'] ?? []);

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function returnItems(array $items): array
    {
        return array_map(
            fn (array $item): array => [
                'name' => $item['name'] ?? null,
                'product_name' => $item['product_name'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'description' => $item['description'] ?? null,
                'condition_notes' => null,
                'is_damaged' => false,
            ],
            $items,
        );
    }
}
