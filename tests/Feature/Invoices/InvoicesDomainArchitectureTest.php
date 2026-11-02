<?php

declare(strict_types=1);

use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoicePayment;
use App\Domain\Invoices\Presentation\Livewire\Index;
use App\Domain\Invoices\Presentation\Livewire\Show;

it('owns canonical billing models, routes, and native presentation', function (): void {
    expect(class_exists(Invoice::class))->toBeTrue()
        ->and(class_exists(InvoicePayment::class))->toBeTrue()
        ->and(class_exists(CreditNote::class))->toBeTrue()
        ->and(class_exists(Index::class))->toBeTrue()
        ->and(class_exists(Show::class))->toBeTrue()
        ->and(class_exists('App\\Models\\Invoice'))->toBeFalse();

    expect(route('invoices.index'))->toEndWith('/invoices')
        ->and(route('api.v1.invoices.index'))->toEndWith('/api/v1/invoices');
});

arch('Invoices presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\Invoices\\Presentation')->not->toUse('Livewire\\Volt');
});
