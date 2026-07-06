<?php

use App\Enums\DemandPhase;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Enums\ReleasePoint;

it('maps each status to its owning state', function (OpportunityStatus $status, OpportunityState $state) {
    expect($status->state())->toBe($state);
})->with([
    'draft open' => [OpportunityStatus::DraftOpen, OpportunityState::Draft],
    'quotation provisional' => [OpportunityStatus::QuotationProvisional, OpportunityState::Quotation],
    'quotation reserved' => [OpportunityStatus::QuotationReserved, OpportunityState::Quotation],
    'order active' => [OpportunityStatus::OrderActive, OpportunityState::Order],
    'order on hire' => [OpportunityStatus::OrderOnHire, OpportunityState::Order],
]);

it('exposes the per-state integer stored in the projection', function () {
    expect(OpportunityStatus::DraftOpen->statusValue())->toBe(0)
        ->and(OpportunityStatus::QuotationProvisional->statusValue())->toBe(0)
        ->and(OpportunityStatus::QuotationReserved->statusValue())->toBe(1)
        ->and(OpportunityStatus::OrderActive->statusValue())->toBe(0)
        ->and(OpportunityStatus::OrderCancelled->statusValue())->toBe(6);
});

it('assigns a distinct hex colour to every opportunity status', function () {
    foreach (OpportunityStatus::cases() as $status) {
        expect($status->colour())->toMatch('/^#[0-9a-f]{6}$/i');
    }
});

it('rebuilds a status from the two persisted columns', function () {
    expect(OpportunityStatus::fromStateAndStatus(OpportunityState::Quotation, 1))
        ->toBe(OpportunityStatus::QuotationReserved)
        ->and(OpportunityStatus::fromStateAndStatus(OpportunityState::Order, 0))
        ->toBe(OpportunityStatus::OrderActive);
});

it('maps statuses to their demand phase', function (OpportunityStatus $status, DemandPhase $phase) {
    expect($status->phase())->toBe($phase);
})->with([
    'draft → draft' => [OpportunityStatus::DraftOpen, DemandPhase::Draft],
    'provisional → draft' => [OpportunityStatus::QuotationProvisional, DemandPhase::Draft],
    'postponed → held' => [OpportunityStatus::QuotationPostponed, DemandPhase::Held],
    'reserved → committed' => [OpportunityStatus::QuotationReserved, DemandPhase::Committed],
    'active → committed' => [OpportunityStatus::OrderActive, DemandPhase::Committed],
    'dispatched → operational' => [OpportunityStatus::OrderDispatched, DemandPhase::Operational],
    'on hire → operational' => [OpportunityStatus::OrderOnHire, DemandPhase::Operational],
    'returned → closed' => [OpportunityStatus::OrderReturned, DemandPhase::Closed],
    'checked → closed' => [OpportunityStatus::OrderChecked, DemandPhase::Closed],
    'complete → closed' => [OpportunityStatus::OrderComplete, DemandPhase::Closed],
    'cancelled → void' => [OpportunityStatus::OrderCancelled, DemandPhase::Void],
    'lost → void' => [OpportunityStatus::QuotationLost, DemandPhase::Void],
    'dead → void' => [OpportunityStatus::QuotationDead, DemandPhase::Void],
]);

it('reports which demand phases are active', function () {
    expect(DemandPhase::Committed->isActive())->toBeTrue()
        ->and(DemandPhase::Operational->isActive())->toBeTrue()
        ->and(DemandPhase::Draft->isActive())->toBeFalse()
        ->and(DemandPhase::Closed->isActive())->toBeFalse()
        ->and(DemandPhase::Void->isActive())->toBeFalse();
});

it('reports closed statuses', function () {
    expect(OpportunityStatus::QuotationLost->isClosed())->toBeTrue()
        ->and(OpportunityStatus::QuotationDead->isClosed())->toBeTrue()
        ->and(OpportunityStatus::OrderComplete->isClosed())->toBeTrue()
        ->and(OpportunityStatus::OrderCancelled->isClosed())->toBeTrue()
        ->and(OpportunityStatus::DraftOpen->isClosed())->toBeFalse()
        ->and(OpportunityStatus::OrderActive->isClosed())->toBeFalse();
});

it('resolves the default open status for each state', function () {
    expect(OpportunityState::Draft->defaultStatus())->toBe(OpportunityStatus::DraftOpen)
        ->and(OpportunityState::Quotation->defaultStatus())->toBe(OpportunityStatus::QuotationProvisional)
        ->and(OpportunityState::Order->defaultStatus())->toBe(OpportunityStatus::OrderActive);
});

it('reports reinstatable statuses via void and held phases', function (OpportunityStatus $status, bool $reinstatable) {
    expect($status->isReinstatable())->toBe($reinstatable);
})->with([
    'lost' => [OpportunityStatus::QuotationLost, true],
    'dead' => [OpportunityStatus::QuotationDead, true],
    'cancelled' => [OpportunityStatus::OrderCancelled, true],
    'postponed' => [OpportunityStatus::QuotationPostponed, true],
    'active order' => [OpportunityStatus::OrderActive, false],
]);

it('reports revertible-to-draft only for provisional quotations', function (OpportunityStatus $status, bool $revertible) {
    expect($status->isRevertibleToDraft())->toBe($revertible);
})->with([
    'provisional' => [OpportunityStatus::QuotationProvisional, true],
    'reserved' => [OpportunityStatus::QuotationReserved, false],
    'order active' => [OpportunityStatus::OrderActive, false],
]);

it('reports terminal complete only for completed orders', function (OpportunityStatus $status, bool $terminal) {
    expect($status->isTerminalComplete())->toBe($terminal);
})->with([
    'complete' => [OpportunityStatus::OrderComplete, true],
    'cancelled' => [OpportunityStatus::OrderCancelled, false],
    'lost' => [OpportunityStatus::QuotationLost, false],
    'checked' => [OpportunityStatus::OrderChecked, false],
]);

it('maps returned orders to operational demand when release point is checked', function () {
    expect(OpportunityStatus::OrderReturned->phase(ReleasePoint::Checked))
        ->toBe(DemandPhase::Operational)
        ->and(OpportunityStatus::OrderReturned->phase(ReleasePoint::Returned))
        ->toBe(DemandPhase::Closed);
});

it('exposes stable labels and colours for every status case', function (OpportunityStatus $status, string $label, string $colour) {
    expect($status->label())->toBe($label)
        ->and($status->colour())->toBe($colour);
})->with([
    'draft open' => [OpportunityStatus::DraftOpen, 'Open', '#64748b'],
    'quotation provisional' => [OpportunityStatus::QuotationProvisional, 'Provisional', '#f59e0b'],
    'quotation reserved' => [OpportunityStatus::QuotationReserved, 'Reserved', '#2563eb'],
    'quotation lost' => [OpportunityStatus::QuotationLost, 'Lost', '#71717a'],
    'quotation dead' => [OpportunityStatus::QuotationDead, 'Dead', '#52525b'],
    'quotation postponed' => [OpportunityStatus::QuotationPostponed, 'Postponed', '#0891b2'],
    'order active' => [OpportunityStatus::OrderActive, 'Active', '#c0392b'],
    'order dispatched' => [OpportunityStatus::OrderDispatched, 'Dispatched', '#e67e22'],
    'order on hire' => [OpportunityStatus::OrderOnHire, 'On Hire', '#d35400'],
    'order returned' => [OpportunityStatus::OrderReturned, 'Returned', '#3498db'],
    'order checked' => [OpportunityStatus::OrderChecked, 'Checked', '#1d4ed8'],
    'order complete' => [OpportunityStatus::OrderComplete, 'Complete', '#27ae60'],
    'order cancelled' => [OpportunityStatus::OrderCancelled, 'Cancelled', '#dc2626'],
]);
