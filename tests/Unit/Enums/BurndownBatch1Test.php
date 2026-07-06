<?php

use App\Enums\BillingCommunicationKind;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerDispatchVerdict;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerUnsealReason;
use App\Enums\DealPriceMode;
use App\Enums\DemandDateSource;
use App\Enums\InvoiceType;
use App\Enums\KitComponentBinding;
use App\Enums\OpportunityCostType;
use App\Enums\PartialInvoiceTaxPolicy;
use App\Enums\TaxBuyerType;
use App\Enums\TaxUnresolvedZoneFallback;
use App\Enums\WaitlistMonitorStatus;

describe('ContainerAvailabilityMode::holdsContentsIndefinitely', function () {
    it('is true only for kit mode', function () {
        expect(ContainerAvailabilityMode::Kit->holdsContentsIndefinitely())->toBeTrue()
            ->and(ContainerAvailabilityMode::Transport->holdsContentsIndefinitely())->toBeFalse()
            ->and(ContainerAvailabilityMode::Hybrid->holdsContentsIndefinitely())->toBeFalse();
    });
});

describe('OpportunityCostType::label', function () {
    it('labels every cost type including virtual stock', function () {
        expect(OpportunityCostType::VirtualStock->label())->toBe('Virtual Stock')
            ->and(OpportunityCostType::Subhire->label())->toBe('Sub-hire');
    });
});

describe('TaxBuyerType::coerce', function () {
    it('returns the original value for unknown aliases', function () {
        expect(TaxBuyerType::coerce('government'))->toBe('government');
    });
});

describe('BillingCommunicationKind::resolverType', function () {
    it('maps every kind to its resolver type', function () {
        expect(BillingCommunicationKind::Invoice->resolverType())->toBe('invoice')
            ->and(BillingCommunicationKind::CreditNote->resolverType())->toBe('credit_note')
            ->and(BillingCommunicationKind::Receipt->resolverType())->toBe('receipt')
            ->and(BillingCommunicationKind::Statement->resolverType())->toBe('statement');
    });
});

describe('InvoiceType::isPosting', function () {
    it('treats proforma as non-posting and other types as posting', function () {
        expect(InvoiceType::Proforma->isPosting())->toBeFalse()
            ->and(InvoiceType::Standard->isPosting())->toBeTrue()
            ->and(InvoiceType::Deposit->isPosting())->toBeTrue();
    });
});

describe('enum label coverage batch 1', function () {
    it('labels TaxUnresolvedZoneFallback', function () {
        expect(TaxUnresolvedZoneFallback::SellerDomestic->label())->toBe('Treat as seller domestic');
    });

    it('labels DealPriceMode cases', function () {
        expect(DealPriceMode::FlatHeadline->label())->toBe('Flat headline override')
            ->and(DealPriceMode::Proportional->label())->toBe('Proportional line distribution');
    });

    it('labels DemandDateSource cases', function () {
        expect(DemandDateSource::Operational->label())->toBe('Operational dates')
            ->and(DemandDateSource::Charge->label())->toBe('Charge dates');
    });

    it('labels KitComponentBinding cases', function () {
        expect(KitComponentBinding::Fixed->label())->toBe('Fixed')
            ->and(KitComponentBinding::Pool->label())->toBe('Pool');
    });

    it('labels PartialInvoiceTaxPolicy cases', function () {
        expect(PartialInvoiceTaxPolicy::Proportional->label())->toBe('Proportional')
            ->and(PartialInvoiceTaxPolicy::Full->label())->toBe('Full tax up-front');
    });

    it('labels ContainerUnsealReason cases', function () {
        expect(ContainerUnsealReason::Manual->label())->toBe('Manual')
            ->and(ContainerUnsealReason::TransferTriggered->label())->toBe('Transfer triggered');
    });

    it('labels ContainerItemUnpackReason cases', function () {
        expect(ContainerItemUnpackReason::Dissolved->label())->toBe('Dissolved')
            ->and(ContainerItemUnpackReason::Manual->label())->toBe('Manual')
            ->and(ContainerItemUnpackReason::Transferred->label())->toBe('Transferred');
    });

    it('labels ContainerDispatchVerdict cases', function () {
        expect(ContainerDispatchVerdict::Pass->label())->toBe('Pass')
            ->and(ContainerDispatchVerdict::WarehouseAutoAdd->label())->toBe('Warehouse auto-add')
            ->and(ContainerDispatchVerdict::Prompt->label())->toBe('Prompt')
            ->and(ContainerDispatchVerdict::Fail->label())->toBe('Fail');
    });

    it('labels WaitlistMonitorStatus cases', function () {
        expect(WaitlistMonitorStatus::Active->label())->toBe('Active')
            ->and(WaitlistMonitorStatus::Matched->label())->toBe('Matched')
            ->and(WaitlistMonitorStatus::Expired->label())->toBe('Expired')
            ->and(WaitlistMonitorStatus::Cancelled->label())->toBe('Cancelled');
    });
});
