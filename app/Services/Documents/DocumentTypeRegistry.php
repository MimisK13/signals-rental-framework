<?php

namespace App\Services\Documents;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\PurchaseOrder;
use Illuminate\Support\Str;
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

    public function label(string $documentType): string
    {
        return $this->get($documentType)['label'];
    }

    public function filenameSlug(string $documentType): string
    {
        return Str::slug(strtolower($this->label($documentType)), '-');
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
            'delivery_note' => 'Delivery Note',
            'pick_list' => 'Pick List',
            'return_list' => 'Return List',
            'damage_report' => 'Damage Report', // Staged: no source model yet — renders via opportunity resolver until asset inspection ships.
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
            'invoice',
            'invoice',
            Invoice::class,
            'Invoice',
            'Issued invoice with frozen line items and tax summary.',
        );

        $this->register(
            'credit_note',
            'credit_note',
            CreditNote::class,
            'Credit Note',
            'Credit note with negated mirror of the original invoice.',
        );

        $this->register(
            'pro_forma',
            'invoice',
            Invoice::class,
            'Pro Forma Invoice',
            'Pro forma invoice rendered from the invoice projection.',
        );

        $this->register(
            'statement',
            'statement',
            Member::class,
            'Statement',
            'Customer account statement summarising outstanding balances.',
        );

        $this->register(
            'receipt',
            'receipt',
            InvoicePayment::class,
            'Payment Receipt',
            'Receipt for a payment recorded against an invoice.',
        );

        $this->register(
            'deposit',
            'invoice',
            Invoice::class,
            'Deposit Invoice',
            'Deposit invoice rendered from the invoice projection.',
        );

        $this->register(
            'purchase_order',
            'purchase_order',
            PurchaseOrder::class,
            'Purchase Order',
            'Supplier purchase order with line items and totals.',
        );
    }
}
