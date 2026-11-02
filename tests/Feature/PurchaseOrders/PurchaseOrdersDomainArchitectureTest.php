<?php

declare(strict_types=1);

use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\PurchaseOrders\Models\PurchaseOrderItem;
use App\Domain\PurchaseOrders\Presentation\Livewire\Form;
use App\Domain\PurchaseOrders\Presentation\Livewire\Index;
use App\Domain\PurchaseOrders\Presentation\Livewire\Show;

it('owns canonical models, routes, and native presentation', function (): void {
    expect(class_exists(PurchaseOrder::class))->toBeTrue()
        ->and(class_exists(PurchaseOrderItem::class))->toBeTrue()
        ->and(class_exists(Index::class))->toBeTrue()
        ->and(class_exists(Form::class))->toBeTrue()
        ->and(class_exists(Show::class))->toBeTrue()
        ->and(class_exists('App\\Models\\PurchaseOrder'))->toBeFalse();

    expect(route('purchase-orders.index'))->toEndWith('/purchase-orders')
        ->and(route('api.v1.purchase_orders.index'))->toEndWith('/api/v1/purchase_orders');
});

arch('PurchaseOrders presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\PurchaseOrders\\Presentation')->not->toUse('Livewire\\Volt');
});
