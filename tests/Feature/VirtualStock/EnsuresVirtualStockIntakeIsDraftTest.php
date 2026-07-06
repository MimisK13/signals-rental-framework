<?php

use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\EnsuresVirtualStockIntakeIsDraft;
use Illuminate\Validation\ValidationException;

it('allows draft virtual stock intakes', function () {
    $intake = VirtualStockIntake::factory()->create(['status' => 'draft']);

    app(EnsuresVirtualStockIntakeIsDraft::class)->assert($intake);
})->throwsNoExceptions();

it('rejects confirmed virtual stock intakes', function () {
    $intake = VirtualStockIntake::factory()->confirmed()->create();

    expect(fn () => app(EnsuresVirtualStockIntakeIsDraft::class)->assert($intake))
        ->toThrow(ValidationException::class, 'Only draft virtual stock intakes can be edited');
});
