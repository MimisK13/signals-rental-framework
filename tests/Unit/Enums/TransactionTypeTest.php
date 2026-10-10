<?php

use App\Enums\TransactionType;

it('has the correct transaction type values', function () {
    expect(TransactionType::Opening->value)->toBe(1)
        ->and(TransactionType::Buy->value)->toBe(4)
        ->and(TransactionType::Sell->value)->toBe(7)
        ->and(TransactionType::TransferIn->value)->toBe(11);
});

it('returns human-readable labels', function () {
    expect(TransactionType::Opening->label())->toBe('Opening Balance')
        ->and(TransactionType::WriteOff->label())->toBe('Write Off')
        ->and(TransactionType::TransferOut->label())->toBe('Transfer Out');
});

it('returns operator adjustment values', function () {
    $values = TransactionType::adjustmentValues();
    expect($values)->toBe([
        TransactionType::Increase->value,
        TransactionType::Decrease->value,
        TransactionType::WriteOff->value,
    ]);
});

it('returns correct quantity sign', function () {
    expect(TransactionType::Buy->quantitySign())->toBe(1)
        ->and(TransactionType::Decrease->quantitySign())->toBe(-1)
        ->and(TransactionType::Sell->quantitySign())->toBe(-1)
        ->and(TransactionType::WriteOff->quantitySign())->toBe(-1)
        ->and(TransactionType::Opening->quantitySign())->toBe(1);
});
