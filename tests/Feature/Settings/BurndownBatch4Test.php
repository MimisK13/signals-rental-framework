<?php

/**
 * P4-CL4b burndown batch 4 — settings definition coverage.
 */

use App\Settings\InvoiceSettings;
use App\Settings\TaxSettings;

it('TaxSettings rules and types expose validation metadata', function () {
    $settings = new TaxSettings;

    expect($settings->rules())->toHaveKeys([
        'unresolved_zone_fallback',
        'default_product_tax_class_id',
    ])->and($settings->types())->toBe([
        'unresolved_zone_fallback' => 'string',
        'default_product_tax_class_id' => 'integer',
    ]);
});

it('InvoiceSettings rules and types expose invoice defaults metadata', function () {
    $settings = new InvoiceSettings;

    expect($settings->rules())->toHaveKeys([
        'source_states',
        'fallback_number_prefix',
        'due_days',
        'lock_opportunity_on_issue',
        'auto_email_on_issue',
        'auto_email_on_credit_note',
        'auto_email_on_payment',
    ])->and($settings->types())->toHaveKeys([
        'source_states',
        'fallback_number_prefix',
        'due_days',
        'lock_opportunity_on_issue',
        'auto_email_on_issue',
        'auto_email_on_credit_note',
        'auto_email_on_payment',
    ]);
});
