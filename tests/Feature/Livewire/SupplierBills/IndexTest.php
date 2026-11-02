<?php

use App\Domain\SupplierBills\Presentation\Livewire\Index;
use Livewire\Livewire;

it('can render', function () {
    $component = Livewire::test(Index::class);

    $component->assertSee('');
});
