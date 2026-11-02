<?php

declare(strict_types=1);

use App\Domain\SupplierBills\Models\SupplierBill;
use App\Domain\SupplierBills\Models\SupplierBillItem;
use App\Domain\SupplierBills\Presentation\Livewire\Form;
use App\Domain\SupplierBills\Presentation\Livewire\Index;
use App\Domain\SupplierBills\Presentation\Livewire\Show;

it('owns canonical models, routes, and native presentation', function (): void {
    expect(class_exists(SupplierBill::class))->toBeTrue()
        ->and(class_exists(SupplierBillItem::class))->toBeTrue()
        ->and(class_exists(Index::class))->toBeTrue()
        ->and(class_exists(Form::class))->toBeTrue()
        ->and(class_exists(Show::class))->toBeTrue()
        ->and(class_exists('App\\Models\\SupplierBill'))->toBeFalse();

    expect(route('supplier-bills.index'))->toEndWith('/supplier-bills');
});

arch('SupplierBills presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\SupplierBills\\Presentation')->not->toUse('Livewire\\Volt');
});
