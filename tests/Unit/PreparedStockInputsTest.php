<?php

use App\Data\Opportunities\PreparedStockInputs;

it('keeps product and store stock keys distinct', function (): void {
    $inputs = new PreparedStockInputs([
        PreparedStockInputs::key(25, 1) => 12,
        PreparedStockInputs::key(25, 2) => 7,
    ]);

    expect($inputs->required(25, 1))->toBe(12)
        ->and($inputs->required(25, 2))->toBe(7)
        ->and($inputs->has(25, 3))->toBeFalse();
});

it('fails explicitly when a prepared stock key is missing', function (): void {
    $inputs = new PreparedStockInputs([]);

    expect(fn (): int => $inputs->required(25, 1))
        ->toThrow(LogicException::class);
});
