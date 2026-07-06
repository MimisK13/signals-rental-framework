<?php

use App\Data\Containers\ContainerDispatchAmendmentData;
use App\Data\Documents\GenerateDocumentData;
use App\Data\Documents\UpdateDocumentSequenceData;
use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Invoices\GenerateProformaInvoiceData;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Data\VirtualStock\UpdateDraftVirtualStockIntakeData;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\Opportunity;
use App\ValueObjects\Tax\DocumentTaxLineAllocation;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;
use Tests\TestCase;

// GenerateDocumentData::from() resolves the Spatie Data pipeline (config) from
// the container, so these DTO tests need the Laravel app booted.
uses(TestCase::class);

describe('FrozenInvoiceLineData::grossTotal', function () {
    it('sums net and tax minor units', function () {
        $line = new FrozenInvoiceLineData(
            invoice_item_id: 1,
            line_key: 'line-1',
            source_type: 'opportunity_item',
            description: 'Speaker hire',
            quantity: '1.00',
            unit_price: 5000,
            discount: 0,
            net_total: 5000,
            tax: 1000,
            product_tax_class_id: 1,
            tax_treatment: 'standard',
        );

        expect($line->grossTotal())->toBe(6000);
    });
});

describe('GenerateDocumentData::resolvedDocumentableClass', function () {
    it('resolves member, payment, and opportunity aliases', function () {
        expect(GenerateDocumentData::from(['documentable_type' => 'member'])->resolvedDocumentableClass())
            ->toBe(Member::class)
            ->and(GenerateDocumentData::from(['documentable_type' => 'payment'])->resolvedDocumentableClass())
            ->toBe(InvoicePayment::class)
            ->and(GenerateDocumentData::from(['documentable_type' => 'opportunity'])->resolvedDocumentableClass())
            ->toBe(Opportunity::class);
    });
});

describe('UpdateDocumentSequenceData::rules', function () {
    it('allows any next_number when padding is absent from the validation context', function () {
        $context = new ValidationContext(
            payload: [],
            fullPayload: [],
            path: ValidationPath::create(),
        );

        $rules = UpdateDocumentSequenceData::rules($context);
        $closure = $rules['next_number'][3];

        $messages = [];
        $closure('next_number', 999, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        expect($messages)->toBeEmpty();
    });

    it('rejects a next_number that exceeds padding via the inline closure', function () {
        $context = new ValidationContext(
            payload: ['padding' => 2],
            fullPayload: ['padding' => 2],
            path: ValidationPath::create(),
        );

        $rules = UpdateDocumentSequenceData::rules($context);
        $closure = $rules['next_number'][3];

        $messages = [];
        $closure('next_number', 999, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        expect($messages)->not->toBeEmpty();
    });
});

describe('ContainerDispatchAmendmentData::rules', function () {
    it('returns validation rules for dispatch amendments', function () {
        expect(ContainerDispatchAmendmentData::rules())
            ->toHaveKey('product_id')
            ->and(ContainerDispatchAmendmentData::rules()['action'])->toContain('in:add,increase');
    });
});

describe('GenerateProformaInvoiceData::rules', function () {
    it('returns proforma invoice validation rules', function () {
        $context = new ValidationContext([], [], ValidationPath::create());

        expect(GenerateProformaInvoiceData::rules($context))
            ->toHaveKey('opportunity_id')
            ->and(GenerateProformaInvoiceData::rules($context)['tag_list.*'])->toContain('max:100');
    });
});

describe('CreateVirtualStockIntakeData and UpdateDraftVirtualStockIntakeData::rules', function () {
    it('returns create intake validation rules', function () {
        $context = new ValidationContext([], [], ValidationPath::create());

        expect(CreateVirtualStockIntakeData::rules($context))
            ->toHaveKey('product_id')
            ->and(CreateVirtualStockIntakeData::rules($context)['quantity'])->toContain('min:1');
    });

    it('delegates update draft rules to the create intake rules', function () {
        $context = new ValidationContext([], [], ValidationPath::create());

        expect(UpdateDraftVirtualStockIntakeData::rules($context))
            ->toBe(CreateVirtualStockIntakeData::rules($context))
            ->and(UpdateDraftVirtualStockIntakeData::rules($context)['quantity'])->toContain('min:1');
    });
});

describe('DocumentTaxLineAllocation::grossMinor', function () {
    it('adds net and tax minor units', function () {
        $allocation = new DocumentTaxLineAllocation(
            lineKey: 'line-1',
            netMinor: 2500,
            taxMinor: 500,
            productTaxClassKey: 3,
        );

        expect($allocation->grossMinor())->toBe(3000);
    });
});
