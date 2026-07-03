<?php

namespace App\Services\Documents;

/**
 * Static sample resolver payload for document template previews.
 */
class DocumentSampleData
{
    /**
     * @return array<string, mixed>
     */
    public static function opportunity(): array
    {
        return [
            'opportunity' => (object) [
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
            'member' => (object) [
                'name' => 'Alex Sample',
                'company_name' => 'Sample Events Ltd',
                'email' => 'alex@example.com',
                'phone' => '01234 567890',
                'vat_number' => 'GB123456789',
            ],
            'store' => (object) [
                'name' => 'Sample Store',
                'address' => "1 High Street\nSampletown\nST1 2AB",
                'phone' => '01234 567890',
                'email' => 'store@example.com',
            ],
            'items' => [
                (object) [
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
            'tax_summary' => (object) [
                'total_net' => '100.00',
                'total_tax' => '20.00',
                'groups' => [],
            ],
        ];
    }
}
