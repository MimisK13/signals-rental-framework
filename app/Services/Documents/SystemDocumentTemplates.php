<?php

namespace App\Services\Documents;

use App\Models\Member;
use App\Models\Opportunity;

/**
 * Canonical system document template definitions seeded per store.
 */
class SystemDocumentTemplates
{
    /** @var list<string> */
    public const CORE_TYPES = [
        'quote',
        'invoice',
        'credit_note',
        'delivery_note',
        'pick_list',
        'return_list',
        'pro_forma',
        'statement',
    ];

    /**
     * @return list<array{
     *     document_type: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     model_type: string,
     *     header_html: string,
     *     body_html: string,
     *     footer_html: string,
     *     styles_css: string,
     *     page_config: array<string, mixed>
     * }>
     */
    public static function definitions(): array
    {
        return array_map(
            fn (string $type): array => self::definition($type),
            self::CORE_TYPES,
        );
    }

    /**
     * @return array{
     *     document_type: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     model_type: string,
     *     header_html: string,
     *     body_html: string,
     *     footer_html: string,
     *     styles_css: string,
     *     page_config: array<string, mixed>
     * }
     */
    public static function definition(string $documentType): array
    {
        return match ($documentType) {
            'quote' => self::baseDefinition(
                type: 'quote',
                name: 'Standard Quote',
                title: 'Quote',
                intro: 'Please find below our quotation for your event.',
            ),
            'invoice' => self::baseDefinition(
                type: 'invoice',
                name: 'Standard Invoice',
                title: 'Invoice',
                intro: 'Please find below your invoice for the services provided.',
            ),
            'credit_note' => self::baseDefinition(
                type: 'credit_note',
                name: 'Standard Credit Note',
                title: 'Credit Note',
                intro: 'This credit note adjusts the account balance shown below.',
            ),
            'delivery_note' => self::warehouseDefinition(
                type: 'delivery_note',
                name: 'Standard Delivery Note',
                title: 'Delivery Note',
                intro: 'Equipment dispatched as listed below.',
            ),
            'pick_list' => self::warehouseDefinition(
                type: 'pick_list',
                name: 'Standard Pick List',
                title: 'Pick List',
                intro: 'Warehouse pick list — check each line as items are collected.',
            ),
            'return_list' => self::returnListDefinition(),
            'pro_forma' => self::baseDefinition(
                type: 'pro_forma',
                name: 'Standard Pro Forma',
                title: 'Pro Forma Invoice',
                intro: 'This pro forma invoice is provided for approval and is not a tax invoice.',
            ),
            'statement' => self::statementDefinition(),
            default => throw new \InvalidArgumentException("Unsupported system document type [{$documentType}]."),
        };
    }

    /**
     * @return array{
     *     document_type: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     model_type: string,
     *     header_html: string,
     *     body_html: string,
     *     footer_html: string,
     *     styles_css: string,
     *     page_config: array<string, mixed>
     * }
     */
    private static function baseDefinition(
        string $type,
        string $name,
        string $title,
        string $intro,
    ): array {
        return [
            'document_type' => $type,
            'slug' => 'system-'.$type,
            'name' => $name,
            'description' => 'System default '.$name.' template.',
            'model_type' => Opportunity::class,
            'header_html' => self::headerHtml($title),
            'body_html' => self::commercialBodyHtml($intro),
            'footer_html' => self::footerHtml(),
            'styles_css' => self::commercialStyles(),
            'page_config' => self::defaultPageConfig(),
        ];
    }

    /**
     * @return array{
     *     document_type: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     model_type: string,
     *     header_html: string,
     *     body_html: string,
     *     footer_html: string,
     *     styles_css: string,
     *     page_config: array<string, mixed>
     * }
     */
    private static function warehouseDefinition(
        string $type,
        string $name,
        string $title,
        string $intro,
    ): array {
        return [
            'document_type' => $type,
            'slug' => 'system-'.$type,
            'name' => $name,
            'description' => 'System default '.$name.' template.',
            'model_type' => Opportunity::class,
            'header_html' => self::headerHtml($title),
            'body_html' => self::warehouseBodyHtml($intro),
            'footer_html' => self::footerHtml(),
            'styles_css' => self::warehouseStyles(),
            'page_config' => self::defaultPageConfig(),
        ];
    }

    private static function headerHtml(string $title): string
    {
        return <<<HTML
<div class="doc-header">
    <div class="doc-brand">
        <h1 class="doc-store">{{ \$store->name }}</h1>
        <p class="doc-type">{$title}</p>
    </div>
    <div class="doc-meta-block">
        <p><strong>Ref:</strong> {{ \$opportunity->number }}</p>
        <p><strong>Subject:</strong> {{ \$opportunity->subject }}</p>
    </div>
</div>
HTML;
    }

    private static function commercialBodyHtml(string $intro): string
    {
        return <<<HTML
<p class="doc-intro">{$intro}</p>

<div class="doc-addresses">
    <div class="doc-address">
        <h2>From</h2>
        <p>{{ \$store->name }}</p>
        <p>{{ \$store->address }}</p>
        <p>{{ \$store->phone }}</p>
        <p>{{ \$store->email }}</p>
    </div>
    <div class="doc-address">
        <h2>To</h2>
        <p>{{ \$member->company_name ?? \$member->name }}</p>
        <p>{{ \$member->email }}</p>
        <p>{{ \$member->phone }}</p>
    </div>
</div>

<div class="doc-event">
    <p><strong>Event:</strong> {{ \$opportunity->subject }}</p>
    <p><strong>Dates:</strong> {{ \$opportunity->starts_at }} – {{ \$opportunity->ends_at }}</p>
    <p><strong>Rental days:</strong> {{ \$opportunity->rental_days }}</p>
</div>

<table class="doc-items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="num">Qty</th>
            <th class="num">Unit</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach (\$items as \$item)
            <tr>
                <td>{{ \$item->name }}</td>
                <td class="num">{{ \$item->quantity }}</td>
                <td class="num">{{ \$item->unit_price }}</td>
                <td class="num">{{ \$item->total }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="doc-totals">
    <tr><td>Subtotal</td><td class="num">{{ \$opportunity->charge_total }}</td></tr>
    <tr><td>Tax</td><td class="num">{{ \$opportunity->tax_total }}</td></tr>
    <tr class="grand"><td>Total</td><td class="num">{{ \$opportunity->grand_total }}</td></tr>
</table>
HTML;
    }

    private static function warehouseBodyHtml(string $intro): string
    {
        return <<<HTML
<p class="doc-intro">{$intro}</p>

<div class="doc-event">
    <p><strong>Job:</strong> {{ \$opportunity->subject }}</p>
    <p><strong>Customer:</strong> {{ \$member->company_name ?? \$member->name }}</p>
    <p><strong>Dates:</strong> {{ \$opportunity->starts_at }} – {{ \$opportunity->ends_at }}</p>
</div>

<table class="doc-items">
    <thead>
        <tr>
            <th>Item</th>
            <th class="num">Qty</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        @foreach (\$items as \$item)
            <tr>
                <td>{{ \$item->name }}</td>
                <td class="num">{{ \$item->quantity }}</td>
                <td>{{ \$item->description }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p class="doc-warehouse-note">Use the warehouse system to view serial numbers, locations, and check-off status for each line.</p>
HTML;
    }

    private static function footerHtml(): string
    {
        return <<<'HTML'
<div class="doc-footer">
    <p>{{ $store->name }} · {{ $store->email }} · {{ $store->phone }}</p>
</div>
HTML;
    }

    private static function commercialStyles(): string
    {
        return <<<'CSS'
.doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid var(--primary-colour);
    padding-bottom: 12px;
    margin-bottom: 18px;
}
.doc-store {
    margin: 0;
    color: var(--primary-colour);
    font-family: var(--font-family), sans-serif;
    font-size: 22px;
}
.doc-type {
    margin: 4px 0 0;
    color: var(--secondary-colour);
    font-size: 14px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.doc-meta-block p {
    margin: 0 0 4px;
    font-size: 12px;
}
.doc-intro {
    color: var(--secondary-colour);
    margin-bottom: 16px;
}
.doc-addresses {
    display: flex;
    gap: 24px;
    margin-bottom: 16px;
}
.doc-address {
    flex: 1;
}
.doc-address h2 {
    margin: 0 0 6px;
    font-size: 13px;
    color: var(--primary-colour);
}
.doc-address p {
    margin: 0 0 3px;
    font-size: 12px;
}
.doc-event {
    background: #f9fafb;
    border-left: 4px solid var(--primary-colour);
    padding: 10px 12px;
    margin-bottom: 16px;
    font-size: 12px;
}
.doc-event p {
    margin: 0 0 4px;
}
.doc-items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
    font-size: 12px;
}
.doc-items th {
    background: var(--primary-colour);
    color: #ffffff;
    text-align: left;
    padding: 8px;
}
.doc-items td {
    border-bottom: 1px solid #e5e7eb;
    padding: 8px;
}
.doc-items .num {
    text-align: right;
    white-space: nowrap;
}
.doc-totals {
    width: 280px;
    margin-left: auto;
    border-collapse: collapse;
    font-size: 12px;
}
.doc-totals td {
    padding: 6px 8px;
    border-bottom: 1px solid #e5e7eb;
}
.doc-totals .num {
    text-align: right;
}
.doc-totals .grand td {
    font-weight: bold;
    color: var(--primary-colour);
    border-top: 2px solid var(--primary-colour);
}
.doc-footer {
    margin-top: 24px;
    padding-top: 10px;
    border-top: 1px solid #e5e7eb;
    font-size: 11px;
    color: var(--secondary-colour);
    text-align: center;
}
.doc-warehouse-note {
    margin-top: 16px;
    font-size: 13px;
    color: var(--secondary-colour);
}
CSS;
    }

    private static function warehouseStyles(): string
    {
        return self::commercialStyles();
    }

    /**
     * @return array{
     *     document_type: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     model_type: string,
     *     header_html: string,
     *     body_html: string,
     *     footer_html: string,
     *     styles_css: string,
     *     page_config: array<string, mixed>
     * }
     */
    private static function returnListDefinition(): array
    {
        return [
            'document_type' => 'return_list',
            'slug' => 'system-return_list',
            'name' => 'Standard Return List',
            'description' => 'System default Return List template.',
            'model_type' => Opportunity::class,
            'header_html' => self::headerHtml('Return List'),
            'body_html' => self::returnListBodyHtml(),
            'footer_html' => self::footerHtml(),
            'styles_css' => self::warehouseStyles(),
            'page_config' => self::defaultPageConfig(),
        ];
    }

    /**
     * @return array{
     *     document_type: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     model_type: string,
     *     header_html: string,
     *     body_html: string,
     *     footer_html: string,
     *     styles_css: string,
     *     page_config: array<string, mixed>
     * }
     */
    private static function statementDefinition(): array
    {
        return [
            'document_type' => 'statement',
            'slug' => 'system-statement',
            'name' => 'Standard Statement',
            'description' => 'System default customer statement template.',
            'model_type' => Member::class,
            'header_html' => self::statementHeaderHtml(),
            'body_html' => self::statementBodyHtml(),
            'footer_html' => self::footerHtml(),
            'styles_css' => self::commercialStyles(),
            'page_config' => self::defaultPageConfig(),
        ];
    }

    private static function returnListBodyHtml(): string
    {
        return <<<'HTML'
<p class="doc-intro">Check-in list — verify each returned item and note any damage.</p>

<div class="doc-event">
    <p><strong>Job:</strong> {{ $opportunity->subject }}</p>
    <p><strong>Customer:</strong> {{ $member->company_name ?? $member->name }}</p>
    <p><strong>Dates:</strong> {{ $opportunity->starts_at }} – {{ $opportunity->ends_at }}</p>
</div>

<table class="doc-items">
    <thead>
        <tr>
            <th>Item</th>
            <th class="num">Qty</th>
            <th>Condition</th>
            <th>Damaged</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($return_items as $item)
            <tr>
                <td>{{ $item->name }}</td>
                <td class="num">{{ $item->quantity }}</td>
                <td>{{ $item->condition_notes ?? '' }}</td>
                <td>{{ ($item->is_damaged ?? false) ? 'Yes' : 'No' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
HTML;
    }

    private static function statementHeaderHtml(): string
    {
        return <<<'HTML'
<div class="doc-header">
    <div class="doc-brand">
        <h1 class="doc-store">{{ $store->name }}</h1>
        <p class="doc-type">Account Statement</p>
    </div>
    <div class="doc-meta-block">
        <p><strong>Account:</strong> {{ $member->account_number ?? $member->name }}</p>
        <p><strong>Period:</strong> {{ $statement->period_start }} – {{ $statement->period_end }}</p>
    </div>
</div>
HTML;
    }

    private static function statementBodyHtml(): string
    {
        return <<<'HTML'
<div class="doc-addresses">
    <div class="doc-address">
        <h2>From</h2>
        <p>{{ $store->name }}</p>
        <p>{{ $store->address }}</p>
        <p>{{ $store->phone }}</p>
        <p>{{ $store->email }}</p>
    </div>
    <div class="doc-address">
        <h2>To</h2>
        <p>{{ $member->company_name ?? $member->name }}</p>
        <p>{{ $member->email }}</p>
        <p>{{ $member->phone }}</p>
    </div>
</div>

<table class="doc-totals">
    <tr><td>Opening Balance</td><td class="num">{{ $statement->opening_balance }}</td></tr>
    <tr class="grand"><td>Closing Balance</td><td class="num">{{ $statement->closing_balance }}</td></tr>
</table>

<table class="doc-items">
    <thead>
        <tr>
            <th>Invoice</th>
            <th>Issued</th>
            <th class="num">Total</th>
            <th class="num">Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->number ?? '' }}</td>
                <td>{{ $invoice->issued_at ?? '' }}</td>
                <td class="num">{{ $invoice->total ?? '' }}</td>
                <td class="num">{{ $invoice->balance ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultPageConfig(): array
    {
        return [
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
        ];
    }
}
