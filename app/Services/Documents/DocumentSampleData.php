<?php

namespace App\Services\Documents;

use InvalidArgumentException;

/**
 * Static sample resolver payload for document template previews.
 */
class DocumentSampleData
{
    /**
     * Sample data keyed to the resolver registry type for a template.
     *
     * @return array<string, mixed>
     */
    public static function forResolver(string $resolverType): array
    {
        return match ($resolverType) {
            'opportunity' => self::opportunity(),
            'return_list' => self::returnList(),
            'statement' => self::statement(),
            'receipt' => self::receipt(),
            'invoice' => self::invoice(),
            'credit_note' => self::creditNote(),
            'purchase_order' => self::purchaseOrder(),
            default => throw new InvalidArgumentException("No sample data registered for resolver type [{$resolverType}]."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function opportunity(): array
    {
        return [
            'opportunity' => [
                'number' => 'QUO-0001',
                'subject' => 'Sample Summer Festival',
                'reference' => 'REF-001',
                'state_label' => 'Quotation',
                'status_label' => 'Draft',
                'starts_at' => '01/07/2026',
                'ends_at' => '03/07/2026',
                'description' => 'Sample opportunity for template preview.',
                'rental_days' => '3',
                'charge_total' => '100.00',
                'tax_total' => '20.00',
                'grand_total' => '120.00',
            ],
            'member' => self::member(),
            'store' => self::store(),
            'items' => [
                [
                    'name' => 'Sample Line Item',
                    'product_name' => 'Sample Product',
                    'quantity' => '2',
                    'unit_price' => '50.00',
                    'discount' => '0.00',
                    'total' => '100.00',
                    'description' => 'Sample line description',
                    'transaction_type_label' => 'Sale',
                ],
            ],
            'costs' => [],
            'tax_summary' => [
                'total_net' => '100.00',
                'total_tax' => '20.00',
                'groups' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function returnList(): array
    {
        $data = self::opportunity();
        $data['return_items'] = [
            [
                'name' => 'Sample Line Item',
                'product_name' => 'Sample Product',
                'quantity' => '2',
                'description' => 'Sample line description',
                'condition_notes' => null,
                'is_damaged' => false,
            ],
        ];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function statement(): array
    {
        return [
            'member' => self::member(),
            'store' => self::store(),
            'statement' => [
                'period_start' => '01/06/2026',
                'period_end' => '01/07/2026',
                'opening_balance' => '0.00',
                'closing_balance' => '250.00',
            ],
            'invoices' => [
                [
                    'number' => 'INV-0001',
                    'issued_at' => '15/06/2026',
                    'total' => '250.00',
                    'balance' => '250.00',
                ],
            ],
            'payments' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function receipt(): array
    {
        return [
            'payment' => [
                'reference' => 'PAY-0001',
                'received_at' => '01/07/2026',
                'amount' => '120.00',
                'method_label' => 'Bank Transfer',
                'notes' => 'Sample payment receipt.',
            ],
            'invoice' => [
                'number' => 'INV-0001',
                'issued_at' => '01/07/2026',
                'total' => '120.00',
                'amount_paid' => '120.00',
                'amount_outstanding' => '0.00',
                'status_label' => 'Paid',
            ],
            'member' => self::member(),
            'store' => self::store(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invoice(): array
    {
        return [
            'invoice' => [
                'number' => 'INV-0001',
                'type_label' => 'Invoice',
                'status_label' => 'Issued',
                'issued_at' => '01/07/2026',
                'due_at' => '15/07/2026',
                'reference' => 'REF-001',
                'notes' => 'Sample invoice for template preview.',
                'subtotal' => '100.00',
                'tax_total' => '20.00',
                'total' => '120.00',
                'amount_paid' => '0.00',
                'amount_outstanding' => '120.00',
                'credited_total' => '0.00',
            ],
            'opportunity' => [
                'number' => 'QUO-0001',
                'subject' => 'Sample Summer Festival',
            ],
            'lines' => [
                [
                    'description' => 'Sample Line Item',
                    'quantity' => '2',
                    'unit_price' => '50.00',
                    'discount' => '0.00',
                    'tax' => '20.00',
                    'total' => '100.00',
                    'tax_treatment_label' => 'Standard',
                ],
            ],
            'tax_summary' => [
                'total_net' => '100.00',
                'total_tax' => '20.00',
                'groups' => [
                    [
                        'tax_rate_name' => 'Standard VAT',
                        'rate_percentage' => '20.0000',
                        'net_total' => '100.00',
                        'tax_total' => '20.00',
                        'result_type' => 'Tax',
                        'legal_text' => null,
                    ],
                ],
            ],
            'payments' => [],
            'member' => self::member(),
            'store' => self::store(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function creditNote(): array
    {
        return [
            'credit_note' => [
                'number' => 'CRN-0001',
                'issued_at' => '01/07/2026',
                'notes' => 'Sample credit note for template preview.',
                'subtotal' => '100.00',
                'tax_total' => '20.00',
                'total' => '120.00',
            ],
            'invoice' => [
                'number' => 'INV-0001',
                'issued_at' => '01/07/2026',
                'total' => '120.00',
            ],
            'lines' => [
                [
                    'description' => 'Sample credited line',
                    'quantity' => '1',
                    'unit_price' => '100.00',
                    'discount' => '0.00',
                    'tax' => '20.00',
                    'total' => '100.00',
                ],
            ],
            'tax_summary' => [
                'total_net' => '100.00',
                'total_tax' => '20.00',
                'groups' => [],
            ],
            'member' => self::member(),
            'store' => self::store(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function purchaseOrder(): array
    {
        return [
            'purchase_order' => [
                'number' => 'PO-0001',
                'po_type_label' => 'Sub-hire',
                'status_label' => 'Draft',
                'expected_at' => '01/07/2026',
                'starts_at' => '01/07/2026',
                'ends_at' => '03/07/2026',
                'reference' => 'REF-001',
                'notes' => 'Sample purchase order for template preview.',
                'supplier_reference' => 'SUP-001',
                'subtotal' => '100.00',
                'tax_total' => '20.00',
                'total' => '120.00',
            ],
            'opportunity' => [
                'number' => 'QUO-0001',
                'subject' => 'Sample Summer Festival',
            ],
            'lines' => [
                [
                    'description' => 'Sample PO line',
                    'quantity' => '2',
                    'unit_cost' => '50.00',
                    'tax' => '20.00',
                    'total' => '100.00',
                ],
            ],
            'supplier' => [
                'name' => 'Sample Supplier',
                'company_name' => 'Sample Suppliers Ltd',
                'email' => 'supplier@example.com',
                'phone' => '01234 567890',
            ],
            'store' => self::store(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function member(): array
    {
        return [
            'name' => 'Alex Sample',
            'company_name' => 'Sample Events Ltd',
            'account_number' => 'ACC-001',
            'email' => 'alex@example.com',
            'phone' => '01234 567890',
            'vat_number' => 'GB123456789',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function store(): array
    {
        return [
            'name' => 'Sample Store',
            'address' => "1 High Street\nSampletown\nST1 2AB",
            'phone' => '01234 567890',
            'email' => 'store@example.com',
        ];
    }
}
