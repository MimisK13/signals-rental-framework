<?php

namespace App\Services\Documents;

use App\Models\Member;
use App\Models\Opportunity;
use InvalidArgumentException;

/**
 * Maps document types to their resolver key and source model class.
 *
 * Core types ship here; plugins register additional types via
 * {@see App\Services\Plugins\PluginRegistrar}.
 */
class DocumentTypeRegistry
{
    /**
     * @var array<string, array{
     *     resolver_type: string,
     *     model_type: class-string,
     *     label: string,
     *     description: string
     * }>
     */
    private array $types = [];

    public function __construct()
    {
        $this->registerCoreTypes();
    }

    /**
     * @param  class-string  $modelType
     */
    public function register(
        string $documentType,
        string $resolverType,
        string $modelType,
        string $label,
        string $description = '',
    ): void {
        $this->types[$documentType] = [
            'resolver_type' => $resolverType,
            'model_type' => $modelType,
            'label' => $label,
            'description' => $description,
        ];
    }

    public function has(string $documentType): bool
    {
        return isset($this->types[$documentType]);
    }

    /**
     * @return array{
     *     resolver_type: string,
     *     model_type: class-string,
     *     label: string,
     *     description: string
     * }
     */
    public function get(string $documentType): array
    {
        return $this->types[$documentType]
            ?? throw new InvalidArgumentException("Unknown document type [{$documentType}].");
    }

    public function resolverType(string $documentType): string
    {
        return $this->get($documentType)['resolver_type'];
    }

    /**
     * @return class-string
     */
    public function modelType(string $documentType): string
    {
        return $this->get($documentType)['model_type'];
    }

    /**
     * @return list<array{type: string, label: string, description: string, model_type: string}>
     */
    public function all(): array
    {
        return array_map(
            fn (string $type, array $definition): array => [
                'type' => $type,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'model_type' => $definition['model_type'],
            ],
            array_keys($this->types),
            $this->types,
        );
    }

    private function registerCoreTypes(): void
    {
        $opportunityTypes = [
            'quote' => 'Quote',
            'invoice' => 'Invoice',
            'credit_note' => 'Credit Note',
            'delivery_note' => 'Delivery Note',
            'pick_list' => 'Pick List',
            'return_list' => 'Return List',
            'pro_forma' => 'Pro Forma Invoice',
            'damage_report' => 'Damage Report',
            'purchase_order' => 'Purchase Order',
        ];

        foreach ($opportunityTypes as $type => $label) {
            $resolver = $type === 'return_list' ? 'return_list' : 'opportunity';

            $this->register(
                $type,
                $resolver,
                Opportunity::class,
                $label,
                "Renders against an opportunity ({$label}).",
            );
        }

        $this->register(
            'statement',
            'statement',
            Member::class,
            'Statement',
            'Customer account statement summarising outstanding balances.',
        );

        // TODO(P33): Receipt resolves against Payment — register when Payment model ships.
    }
}
